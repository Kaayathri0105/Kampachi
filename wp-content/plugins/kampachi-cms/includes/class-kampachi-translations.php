<?php

declare(strict_types=1);

use Kampachi\CMS\Awards;
use Kampachi\CMS\Press;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Optional English-master translation provisioning for public CMS posts.
 * Language copies are independent posts; media assets are inherited from English.
 */
final class Kampachi_Translations
{
    private const JOB = 'kc_translate_post_language';

    private static bool $creating = false;

    private const TYPES = [
        'page', Kampachi_Landings::POST_TYPE, 'kc_offer', 'kc_sake', 'kc_outlet', 'kc_job', 'kc_prefecture', 'kc_event',
        Press::POST_TYPE, Awards::POST_TYPE,
    ];

    private const MEDIA_KEYS = [
        '_kampachi_hero_image_ids',
        '_kampachi_hero_video_id',
        '_kampachi_about_image_ids',
        '_kampachi_values_image_ids',
        '_kampachi_outlet_hero_ids',
        '_kampachi_sake_image_ids',
        '_kampachi_sake_hero_media_ids',
        '_kampachi_catering_hero_media_ids',
        '_kampachi_event_image_ids',
        '_kampachi_source_image_url',
        '_kampachi_event_fallback_image',
        '_kampachi_sake_outlet_ids',
        '_kampachi_event_outlet_ids',
        '_kampachi_offer_hero_ids',
        '_kampachi_offer_outlet_ids',
        '_kampachi_offer_locations_saved',
    ];

    public static function boot(): void
    {
        add_action('add_meta_boxes', [self::class, 'metabox'], 10, 2);
        add_action('edit_form_top', [self::class, 'render_editor_tabs']);
        add_action('wp_after_insert_post', [self::class, 'on_save'], 999, 2);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('wp_ajax_' . self::JOB, [self::class, 'translate_ajax']);
        add_action(self::JOB, [self::class, 'translate_job'], 10, 2);
        add_filter('body_class', [self::class, 'body_class']);
        add_filter('language_attributes', [self::class, 'html_language']);
        add_action('wp_head', [self::class, 'hreflang'], 5);
        add_action('pre_get_posts', [self::class, 'filter_archives']);
    }

    /** @param array<int, string> $classes @return array<int, string> */
    public static function body_class(array $classes): array
    {
        if (count(self::links(get_queried_object_id())) > 1) {
            $classes[] = 'kc-has-translations';
        }
        return $classes;
    }

    public static function html_language(string $attributes): string
    {
        $language = (string) get_post_meta(get_queried_object_id(), '_kc_language', true);
        if ($language === '' || ! isset(Kampachi_Master_Settings::languages()[$language])) {
            return $attributes;
        }
        $locales = ['en' => 'en-US', 'ms' => 'ms-MY', 'zh' => 'zh-Hans', 'zh-hant' => 'zh-Hant', 'ja' => 'ja-JP', 'ta' => 'ta-IN', 'vi' => 'vi-VN', 'fr' => 'fr-FR', 'de' => 'de-DE'];
        $replacement = 'lang="' . esc_attr($locales[$language] ?? $language) . '"';
        if (preg_match('/lang="[^"]+"/', $attributes)) {
            return (string) preg_replace('/lang="[^"]+"/', $replacement, $attributes, 1);
        }
        return trim($attributes . ' ' . $replacement);
    }

    public static function hreflang(): void
    {
        foreach (self::links(get_queried_object_id()) as $code => $entry) {
            $tag = match ($code) { 'zh' => 'zh-Hans', 'zh-hant' => 'zh-Hant', default => $code };
            echo '<link rel="alternate" hreflang="' . esc_attr($tag) . '" href="' . esc_url($entry['url']) . '">' . "\n";
        }
    }

    public static function filter_archives(WP_Query $query): void
    {
        if (is_admin() || $query->is_singular() || $query->get('kc_include_translations')) {
            return;
        }
        $metaQuery = $query->get('meta_query');
        $metaQuery = is_array($metaQuery) ? $metaQuery : [];
        foreach ($metaQuery as $condition) {
            if (is_array($condition) && ($condition['key'] ?? '') === '_kc_translation_of') {
                return;
            }
        }
        $metaQuery[] = ['key' => '_kc_translation_of', 'compare' => 'NOT EXISTS'];
        $query->set('meta_query', $metaQuery);
    }

    public static function metabox(string $postType, WP_Post $post): void
    {
        if (in_array($postType, self::TYPES, true) && use_block_editor_for_post($post)) {
            add_meta_box(
                'kc-translation-tabs',
                'Content languages',
                [self::class, 'render_editor_tabs'],
                $postType,
                'normal',
                'high'
            );
        }
    }

    public static function admin_assets(): void
    {
        $screen = get_current_screen();
        $dashboard = $_GET['page'] ?? '';
        $dashboard = is_string($dashboard) ? sanitize_key(wp_unslash($dashboard)) : '';
        if (
            ! ($screen !== null && $screen->base === 'post' && in_array($screen->post_type, self::TYPES, true))
            && ! str_starts_with($dashboard, 'kampachi-landing-')
            && ! in_array($dashboard, ['kampachi-homepage-module', 'kampachi-catering-module', 'kampachi-careers-landing'], true)
        ) {
            return;
        }

        $directory = dirname(__DIR__) . '/assets/';
        if (! is_file($directory . 'post-translations.js') || ! is_file($directory . 'post-translation-tabs.css')) {
            return;
        }

        wp_enqueue_script(
            'kc-post-translations',
            plugins_url('../assets/post-translations.js', __FILE__),
            [],
            (string) filemtime($directory . 'post-translations.js'),
            true
        );
        wp_enqueue_style(
            'kc-post-translation-tabs',
            plugins_url('../assets/post-translation-tabs.css', __FILE__),
            [],
            (string) filemtime($directory . 'post-translation-tabs.css')
        );
    }

    public static function dashboard_page(?WP_Post $master, mixed $requested): ?WP_Post
    {
        $language = is_string($requested) ? sanitize_key(wp_unslash($requested)) : '';
        if ($requested === null || $requested === '' || $language === 'en') {
            return $master;
        }

        if ($master !== null && isset(Kampachi_Master_Settings::languages()[$language])) {
            $postId = self::translated_post_id($master->ID, $language);
            if ($postId > 0 && current_user_can('edit_post', $master->ID) && current_user_can('edit_post', $postId)) {
                $translated = get_post($postId);
                if ($translated instanceof WP_Post && $translated->post_type === $master->post_type) {
                    return $translated;
                }
            }
        }

        wp_die('This language version has not been created or is unavailable.', '', ['response' => 403]);
    }

    public static function post_language(WP_Post $post): string
    {
        $language = (string) get_post_meta($post->ID, '_kc_language', true);

        return isset(Kampachi_Master_Settings::languages()[$language]) ? $language : 'en';
    }

    public static function dashboard_url(WP_Post $post, string $url): string
    {
        $language = self::post_language($post);

        return $language === 'en' ? $url : add_query_arg('kc_language', $language, $url);
    }

    public static function render_dashboard_language(WP_Post $post): void
    {
        echo '<input type="hidden" name="kc_language" value="' . esc_attr(self::post_language($post)) . '">';
    }

    public static function render_editor_tabs(WP_Post $post): void
    {
        if (in_array($post->post_type, self::TYPES, true)) {
            self::render_tabs($post);
        }
    }

    public static function render_tabs(?WP_Post $post, string $dashboard = '', bool $homepage = false): void
    {
        $languages = Kampachi_Master_Settings::languages();
        $canEdit = $post === null ? $homepage && current_user_can('edit_pages') : current_user_can('edit_post', $post->ID);
        if (count($languages) < 2 || ! $canEdit) {
            return;
        }

        $origin = $post !== null ? (absint(get_post_meta($post->ID, '_kc_translation_of', true)) ?: $post->ID) : 0;
        $source = $origin > 0 ? get_post($origin) : null;
        $canTranslate = self::provider() !== '' && ($post === null || (
            $source instanceof WP_Post && self::is_source($source) && current_user_can('edit_post', $origin)
        ));
        $language = $post !== null ? self::post_language($post) : 'en';
        $automatic = $origin > 0 && $language === 'en' && self::automatic_enabled();
        $currentLabel = self::tab_label($language, $languages[$language]);

        echo '<section class="kc-post-translations" data-kc-translations data-source="' . esc_attr((string) $origin) . '"';
        echo ' data-url="' . esc_url(admin_url('admin-ajax.php')) . '"';
        echo ' data-nonce="' . esc_attr(wp_create_nonce(self::JOB . '_' . $origin)) . '">';
        echo '<nav class="nav-tab-wrapper kc-language-tabs" aria-label="Content languages">';

        foreach ($languages as $code => $name) {
            $label = self::tab_label($code, $name);
            $target = $code === 'en' || $origin === 0 ? $origin : self::translated_post_id($origin, $code);
            $editorUrl = $target > 0 ? get_edit_post_link($target, 'raw') : '';
            $tabUrl = $dashboard !== '' ? add_query_arg('kc_language', $code, $dashboard) : '';
            echo '<div class="kc-language-tab-item">';
            if ($code === $language) {
                echo '<span class="nav-tab nav-tab-active" aria-current="page">' . esc_html($label) . '</span>';
            } elseif ($editorUrl) {
                echo '<a class="nav-tab" href="' . esc_url($tabUrl ?: $editorUrl) . '">' . esc_html($label) . '</a>';
            } elseif ($canTranslate && $target === 0 && $code !== 'en') {
                $pending = $automatic && wp_next_scheduled(self::JOB, [$origin, $code]) !== false;
                self::render_action($origin, $code, $label, 0, $pending, $tabUrl, 'nav-tab');
            } else {
                echo '<span class="nav-tab" aria-disabled="true">' . esc_html($label) . '</span>';
            }
            echo '</div>';
        }

        echo '</nav><div class="kc-language-notice">';
        echo '<strong>Editing language:</strong> ' . esc_html($currentLabel) . '. ';
        echo $post?->post_status === 'auto-draft'
            ? 'Save the English content to start translating.'
            : 'Each language saves separately. Select an available language tab to edit or create its version.';

        if ($post !== null && $dashboard !== '' && $language !== 'en') {
            $postUrl = get_edit_post_link($post->ID, 'raw');
            $postLabel = $post->post_status === 'publish' ? 'Open WordPress post' : 'Review and publish draft';
            echo '<p><a href="' . esc_url($postUrl ?: '') . '">' . esc_html($postLabel) . '</a></p>';
        }

        if ($post !== null && $canTranslate && $language !== 'en') {
            echo '<p>';
            self::render_action($origin, $language, $currentLabel, $post->ID, false, '', 'button button-small');
            echo '</p>';
        }
        echo '</div></section>';
    }

    public static function tab_label(string $language, string $label): string
    {
        return match ($language) {
            'en' => '🇬🇧 English',
            'ja' => '🇯🇵 日本語',
            'zh' => '🇨🇳 简体中文',
            'zh-hant' => '繁體中文',
            default => $label,
        };
    }

    private static function render_action(
        int $origin,
        string $language,
        string $label,
        int $target,
        bool $pending,
        string $tabUrl,
        string $class
    ): void {
        $button = $target > 0 ? 'Retranslate from English' : $label;
        echo '<button type="button" class="' . esc_attr($class) . '"';
        echo ' data-kc-language="' . esc_attr($language) . '" data-label="' . esc_attr($label) . '"';
        echo ' data-target="' . esc_attr((string) $target) . '" data-tab-url="' . esc_url($tabUrl) . '"';
        echo $pending ? ' data-pending="1">' : '>';
        echo esc_html($button) . '</button>';

        $error = $origin > 0 ? (string) get_post_meta($origin, '_kc_translation_error_' . $language, true) : '';
        echo '<span class="kc-language-status" data-kc-translation-status role="status">' . esc_html($error) . '</span>';
    }

    private static function provider(): string
    {
        $provider = (string) Kampachi_Master_Settings::get('language_provider');

        return in_array($provider, ['azure', 'deepl'], true) ? $provider : '';
    }

    public static function automatic_enabled(): bool
    {
        return Kampachi_Master_Settings::get('auto_translate') === '1'
            && self::provider() !== ''
            && count(Kampachi_Master_Settings::languages()) > 1;
    }

    private static function is_source(WP_Post $post): bool
    {
        return in_array($post->post_type, self::TYPES, true)
            && in_array($post->post_status, ['draft', 'pending', 'publish', 'private', 'future'], true)
            && absint(get_post_meta($post->ID, '_kc_translation_of', true)) === 0
            && in_array((string) get_post_meta($post->ID, '_kc_language', true), ['', 'en'], true);
    }

    public static function translated_post_id(int $masterId, string $lang): int
    {
        $found = get_posts([
            'post_type' => self::TYPES,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_kc_translation_of', 'value' => (string) $masterId, 'compare' => '='],
                ['key' => '_kc_language', 'value' => $lang, 'compare' => '='],
            ],
        ]);

        return isset($found[0]) ? absint($found[0]) : 0;
    }

    /** @return array<string, array{label: string, url: string}> */
    public static function links(int $currentId): array
    {
        $origin = absint(get_post_meta($currentId, '_kc_translation_of', true));
        $master = $origin > 0 ? $origin : $currentId;
        $homepage = Kampachi_Homepage::master_page();
        $isHomepage = is_front_page() || ($homepage !== null && $homepage->ID === $master);
        if ($isHomepage) {
            $master = $homepage?->ID ?? 0;
        }

        $links = [];
        foreach (Kampachi_Master_Settings::languages() as $code => $name) {
            if ($code === 'en' && $isHomepage) {
                $links[$code] = ['label' => $name, 'url' => home_url('/')];
                continue;
            }
            if ($master <= 0) {
                continue;
            }
            $id = $code === 'en' ? $master : self::translated_post_id($master, $code);
            if ($id <= 0 || get_post_status($id) !== 'publish') {
                continue;
            }
            $url = get_permalink($id);
            if (is_string($url) && $url !== '') {
                $links[$code] = ['label' => $name, 'url' => $url];
            }
        }

        return $links;
    }

    public static function on_save(int $postId, WP_Post $post): void
    {
        if (self::$creating || ! self::is_source($post)
            || wp_is_post_revision($postId) || wp_is_post_autosave($postId)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        self::sync_images($postId);

        if (! self::automatic_enabled()) {
            return;
        }

        foreach (Kampachi_Master_Settings::languages() as $language => $label) {
            if ($language === 'en' || self::translated_post_id($postId, $language) > 0) {
                continue;
            }

            $args = [$postId, $language];
            if (wp_next_scheduled(self::JOB, $args) === false) {
                wp_schedule_single_event(time() + 15, self::JOB, $args);
            }
        }
    }

    public static function translate_job(int $postId, string $language): void
    {
        if (Kampachi_Master_Settings::get('auto_translate') === '1'
            && self::translated_post_id($postId, $language) === 0) {
            self::run_translation($postId, $language, 0);
        }
    }

    public static function translate_ajax(): void
    {
        $postId = absint($_POST['source'] ?? 0);
        $targetId = absint($_POST['target'] ?? 0);
        $rawLanguage = $_POST['language'] ?? '';
        $language = is_string($rawLanguage) ? sanitize_key(wp_unslash($rawLanguage)) : '';
        check_ajax_referer(self::JOB . '_' . $postId, 'nonce');

        if ($postId === 0) {
            if ($targetId > 0 || ! current_user_can('edit_pages')) {
                wp_send_json_error(['message' => 'You do not have permission to run this translation.'], 403);
            }
            if ($language === 'en' || ! isset(Kampachi_Master_Settings::languages()[$language])
                || self::provider() === '') {
                wp_send_json_error(['message' => 'Enable this language and its translation provider first.'], 409);
            }

            $source = Kampachi_Homepage::create_translation_source();
            if (is_wp_error($source)) {
                wp_send_json_error(['message' => $source->get_error_message()], 409);
            }
            $postId = $source->ID;
        }

        if (! current_user_can('edit_post', $postId)
            || ($targetId > 0 && (
                ! current_user_can('edit_post', $targetId) || ($_POST['replace'] ?? '') !== '1'
            ))) {
            wp_send_json_error(['message' => 'You do not have permission to run this translation.'], 403);
        }

        $result = self::run_translation($postId, $language, $targetId);
        if (is_wp_error($result)) {
            wp_send_json_error([
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 409);
        }

        wp_send_json_success(['url' => get_edit_post_link($result, 'raw')]);
    }

    private static function run_translation(int $postId, string $language, int $targetId): int|WP_Error
    {
        $post = get_post($postId, OBJECT, 'raw');
        $provider = self::provider();
        if (! $post instanceof WP_Post || ! self::is_source($post) || $provider === '' || $language === 'en'
            || ! isset(Kampachi_Master_Settings::languages()[$language])) {
            return new WP_Error('kc_translation_unavailable', 'Save the English post and enable this language and provider.');
        }

        $lock = 'kc_translation_lock_' . $postId . '_' . $language;
        $started = (string) time();
        $previous = (int) get_option($lock, 0);
        if ($previous > 0 && $previous + 600 < (int) $started) {
            delete_option($lock);
        }
        if (! add_option($lock, $started, '', false)) {
            return new WP_Error('kc_translation_busy', 'This language is already translating. Wait a moment and try again.');
        }

        try {
            $existing = self::translated_post_id($postId, $language);
            if ($existing !== $targetId) {
                if ($targetId === 0 && $existing > 0) {
                    return $existing;
                }
                return new WP_Error('kc_translation_changed', 'The language post changed. Reopen the editor and try again.');
            }

            $result = self::write_translation($post, $language, $provider, $targetId);
            $errorKey = '_kc_translation_error_' . $language;
            if (is_wp_error($result)) {
                update_post_meta($postId, $errorKey, $result->get_error_message());
            } else {
                delete_post_meta($postId, $errorKey);
                wp_clear_scheduled_hook(self::JOB, [$postId, $language]);
            }

            return $result;
        } finally {
            if (get_option($lock) === $started) {
                delete_option($lock);
            }
        }
    }

    private static function write_translation(
        WP_Post $source,
        string $language,
        string $provider,
        int $targetId
    ): int|WP_Error {
        $target = $targetId > 0 ? get_post($targetId, OBJECT, 'raw') : null;
        if ($targetId > 0 && (! $target instanceof WP_Post || $target->post_type !== $source->post_type)) {
            return new WP_Error('kc_translation_changed', 'This language post is unavailable.');
        }

        $fields = self::translate_fields($source, $language, $provider);
        if (is_wp_error($fields)) {
            return $fields;
        }
        $metadata = self::translated_metadata($source->ID, $language, $provider);
        if (is_wp_error($metadata)) {
            return $metadata;
        }

        $home = null;
        if (Kampachi_Homepage::master_page()?->ID === $source->ID) {
            $home = Kampachi_Homepage::prepare_translation(
                static fn (array $texts): array|WP_Error => $provider === 'azure'
                    ? self::azure($texts, $language)
                    : self::deepl($texts, $language)
            );
            if (is_wp_error($home)) {
                return $home;
            }
        }

        $savedSource = get_post($source->ID, OBJECT, 'raw');
        $savedTarget = $targetId > 0 ? get_post($targetId, OBJECT, 'raw') : null;
        if (! $savedSource instanceof WP_Post || ! self::is_source($savedSource) || $savedSource != $source
            || $savedTarget != $target || self::translated_post_id($source->ID, $language) !== $targetId) {
            return new WP_Error('kc_translation_changed', 'The source or language post changed. Save and try again.');
        }

        $content = [
            'post_title' => $fields['title'],
            'post_content' => $fields['content'],
            'post_excerpt' => $fields['excerpt'],
        ];

        self::$creating = true;
        try {
            if ($targetId === 0) {
                $result = wp_insert_post(wp_slash($content + [
                    'post_type' => $source->post_type,
                    'post_status' => 'draft',
                    'post_name' => sanitize_title(($home !== null ? 'home' : $source->post_name) . '-' . $language),
                    'post_author' => $source->post_author,
                    'menu_order' => $source->menu_order,
                    'meta_input' => ['_kc_translation_of' => $source->ID, '_kc_language' => $language],
                ]), true);
            } else {
                $result = wp_update_post(wp_slash($content + ['ID' => $targetId]), true);
            }
            if (is_wp_error($result) || $result === 0) {
                return is_wp_error($result) ? $result : new WP_Error('kc_translation_save', 'Could not save translation.');
            }
            if ($targetId === 0) {
                self::copy_custom_metadata($source->ID, $result);
            }
            foreach ($metadata as $key => $value) {
                update_post_meta($result, $key, wp_slash($value));
            }

            if ($home !== null) {
                Kampachi_Homepage::save_translation($language, $home);
            }

            self::sync_images($source->ID);

            return $result;
        } finally {
            self::$creating = false;
        }
    }

    private static function copy_custom_metadata(int $originalId, int $translatedId): void
    {
        $metadata = get_post_meta($originalId);
        foreach ($metadata as $key => $values) {
            if (! str_starts_with($key, '_kampachi_') || ! is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                add_post_meta($translatedId, $key, wp_slash(maybe_unserialize($value)));
            }
        }
        set_post_thumbnail($translatedId, get_post_thumbnail_id($originalId));
    }

    /** @return array<string, string|array>|WP_Error */
    private static function translated_metadata(int $sourceId, string $lang, string $provider): array|WP_Error
    {
        $simpleNames = [
            'subtitle', 'badge', 'terms', 'address', 'hours',
            'intro_title', 'intro_subtitle', 'intro_description',
            'values_title', 'values_subtitle', 'values_description',
            'outlet_intro', 'outlet_hours_description',
            'sake_description', 'sake_cta_label', 'sake_enquiry_label',
            'event_label', 'event_subtitle', 'event_description', 'event_cta_label',
            'offer_introduction', 'offer_cta_label',
            'award_organisation',
            'press_video_title', 'press_video_description', 'press_video_cta_label',
            'seo_title', 'seo_description',
        ];
        $structured = [
            '_kampachi_values_list' => ['title', 'description'],
            '_kampachi_privacy_sections' => ['title', 'description'],
            '_kampachi_outlet_dishes' => ['title', 'description'],
            '_kampachi_outlet_menus' => ['title'],
            '_kampachi_sake_bottles' => ['name', 'drink_type', 'size', 'description'],
            '_kampachi_offer_menus' => ['title'],
            '_kampachi_offer_details' => ['title', 'description'],
            '_kampachi_offer_ctas' => ['label'],
        ];
        $sourceValues = [];
        $pointers = [];
        $texts = [];
        foreach ($simpleNames as $key) {
            $metaKey = '_kampachi_' . $key;
            $value = get_post_meta($sourceId, $metaKey, true);
            if (! is_string($value) || ! metadata_exists('post', $sourceId, $metaKey)) {
                continue;
            }
            if (trim($value) === '') {
                $sourceValues[$metaKey] = $value;
                continue;
            }
            $texts[] = $value;
            $pointers[] = ['key' => $metaKey, 'index' => null, 'field' => null];
        }
        foreach ($structured as $metaKey => $fields) {
            $value = get_post_meta($sourceId, $metaKey, true);
            if (! is_array($value)) {
                continue;
            }
            $sourceValues[$metaKey] = $value;
            foreach ($value as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($fields as $field) {
                    if (! is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                        continue;
                    }
                    $texts[] = $row[$field];
                    $pointers[] = ['key' => $metaKey, 'index' => $index, 'field' => $field];
                }
            }
        }
        if ($texts === []) {
            return $sourceValues;
        }
        $translated = [];
        foreach (array_chunk($texts, 20) as $chunk) {
            $result = $provider === 'azure' ? self::azure($chunk, $lang) : self::deepl($chunk, $lang);
            if (is_wp_error($result)) {
                return $result;
            }
            array_push($translated, ...$result);
        }
        foreach ($pointers as $index => $pointer) {
            $text = $translated[$index] ?? null;
            if (! is_string($text)) {
                return new WP_Error('kc_translation_meta', 'Translation result missing CMS field.');
            }
            if ($pointer['index'] === null) {
                $sourceValues[$pointer['key']] = $text;
            } else {
                $sourceValues[$pointer['key']][$pointer['index']][$pointer['field']] = $text;
            }
        }
        return $sourceValues;
    }

    private static function sync_images(int $masterId): void
    {
        $languages = Kampachi_Master_Settings::languages();
        foreach ($languages as $lang => $label) {
            if ($lang === 'en') {
                continue;
            }
            $target = self::translated_post_id($masterId, $lang);
            if ($target <= 0) {
                continue;
            }
            $thumbnail = get_post_thumbnail_id($masterId);
            if ($thumbnail > 0) {
                set_post_thumbnail($target, $thumbnail);
            } else {
                delete_post_thumbnail($target);
            }
            \Kampachi\CMS\SakeBottles::syncTranslation($masterId, $target);
            foreach (['_kampachi_outlet_dishes' => 'image_id', '_kampachi_outlet_menus' => 'attachment_id'] as $key => $mediaField) {
                $sourceRows = get_post_meta($masterId, $key, true);
                if (! is_array($sourceRows)) {
                    delete_post_meta($target, $key);
                    continue;
                }
                $translatedRows = get_post_meta($target, $key, true);
                $translatedRows = is_array($translatedRows) ? $translatedRows : [];
                $byId = [];
                foreach ($translatedRows as $row) {
                    if (is_array($row) && ! empty($row[$mediaField])) {
                        $byId[(string) $row[$mediaField]] = $row;
                    }
                }
                $merged = [];
                foreach ($sourceRows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $id = (string) ($row[$mediaField] ?? '');
                    $existing = $byId[$id] ?? [];
                    $merged[] = array_merge($row, array_intersect_key($existing, array_flip(['title', 'description'])));
                }
                update_post_meta($target, $key, $merged);
            }
            foreach (self::MEDIA_KEYS as $key) {
                if (metadata_exists('post', $masterId, $key)) {
                    update_post_meta($target, $key, get_post_meta($masterId, $key, true));
                } else {
                    delete_post_meta($target, $key);
                }
            }
        }
    }

    /** @return array{title: string, excerpt: string, content: string}|WP_Error */
    private static function translate_fields(WP_Post $post, string $to, string $provider): array|WP_Error
    {
        $texts = [$post->post_title, $post->post_excerpt, $post->post_content];
        $result = match ($provider) {
            'azure' => self::azure($texts, $to),
            'deepl' => self::deepl($texts, $to),
            default => new WP_Error('kc_translation_provider', 'Unsupported API.'),
        };
        if (is_wp_error($result)) {
            return $result;
        }
        return ['title' => $result[0], 'excerpt' => $result[1], 'content' => $result[2]];
    }

    /** @param array<int, string> $texts @return array<int, string>|WP_Error */
    private static function azure(array $texts, string $to): array|WP_Error
    {
        if (! defined('KC_AZURE_TRANSLATOR_KEY') || ! defined('KC_AZURE_TRANSLATOR_REGION')) {
            return new WP_Error('kc_azure_key', 'Azure Translator key and region are missing in wp-config.php.');
        }
        $target = match ($to) {
            'zh' => 'zh-Hans',
            'zh-hant' => 'zh-Hant',
            default => $to,
        };
        $url = 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from=en&to=' . rawurlencode($target) . '&textType=html';
        $response = wp_safe_remote_post($url, [
            'headers' => [
                'Ocp-Apim-Subscription-Key' => (string) constant('KC_AZURE_TRANSLATOR_KEY'),
                'Ocp-Apim-Subscription-Region' => (string) constant('KC_AZURE_TRANSLATOR_REGION'),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(array_map(static fn (string $text): array => ['text' => $text === '' ? ' ' : $text], $texts)),
            'timeout' => 18,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('kc_azure_failure', 'Azure Translator request failed.');
        }
        $result = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($result) || count($result) !== count($texts)) {
            return new WP_Error('kc_azure_format', 'Azure Translator returned an invalid response.');
        }
        $translated = [];
        foreach ($result as $index => $item) {
            $value = $item['translations'][0]['text'] ?? null;
            if (! is_string($value)) {
                return new WP_Error('kc_azure_format', 'Missing translated text.');
            }
            $translated[] = $texts[$index] === '' ? '' : $value;
        }

        return $translated;
    }

    /** @param array<int, string> $texts @return array<int, string>|WP_Error */
    private static function deepl(array $texts, string $to): array|WP_Error
    {
        if (! defined('KC_DEEPL_AUTH_KEY') || (string) constant('KC_DEEPL_AUTH_KEY') === '') {
            return new WP_Error('kc_deepl_key', 'DeepL API key is missing in wp-config.php.');
        }
        $key = (string) constant('KC_DEEPL_AUTH_KEY');
        $endpoint = str_ends_with($key, ':fx') ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
        $target = $to === 'zh' ? 'ZH' : strtoupper($to);
        $payload = ['text' => array_map(static fn (string $text): string => $text === '' ? ' ' : $text, $texts), 'source_lang' => 'EN', 'target_lang' => $target, 'tag_handling' => 'html'];
        $response = wp_safe_remote_post($endpoint, [
            'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 18,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('kc_deepl_failure', 'DeepL Translator request failed. Check supported language and account limits.');
        }
        $result = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($result) || ! isset($result['translations']) || count($result['translations']) !== count($texts)) {
            return new WP_Error('kc_deepl_format', 'DeepL returned an invalid response.');
        }
        $translated = [];
        foreach ($result['translations'] as $index => $item) {
            if (! is_string($item['text'] ?? null)) {
                return new WP_Error('kc_deepl_format', 'Missing translated text.');
            }
            $translated[] = $texts[$index] === '' ? '' : $item['text'];
        }

        return $translated;
    }
}
