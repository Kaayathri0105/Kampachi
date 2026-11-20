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
    private static bool $creating = false;

    private const TYPES = [
        'page', 'kc_offer', 'kc_sake', 'kc_outlet', 'kc_job', 'kc_prefecture', 'kc_event',
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
        add_action('add_meta_boxes', [self::class, 'metabox']);
        add_action('save_post', [self::class, 'on_save'], 999, 2);
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
        $locales = ['en' => 'en-US', 'ms' => 'ms-MY', 'zh' => 'zh-CN', 'ja' => 'ja-JP', 'ta' => 'ta-IN', 'vi' => 'vi-VN', 'fr' => 'fr-FR', 'de' => 'de-DE'];
        $replacement = 'lang="' . esc_attr($locales[$language] ?? $language) . '"';
        if (preg_match('/lang="[^"]+"/', $attributes)) {
            return (string) preg_replace('/lang="[^"]+"/', $replacement, $attributes, 1);
        }
        return trim($attributes . ' ' . $replacement);
    }

    public static function hreflang(): void
    {
        foreach (self::links(get_queried_object_id()) as $code => $entry) {
            echo '<link rel="alternate" hreflang="' . esc_attr($code) . '" href="' . esc_url($entry['url']) . '">' . "\n";
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

    public static function metabox(): void
    {
        foreach (self::TYPES as $type) {
            add_meta_box('kc-translations', 'Kampachi Language', [self::class, 'render_metabox'], $type, 'side', 'default');
        }
    }

    public static function render_metabox(WP_Post $post): void
    {
        $lang = (string) get_post_meta($post->ID, '_kc_language', true);
        $source = absint(get_post_meta($post->ID, '_kc_translation_of', true));
        echo '<p><strong>Post language:</strong> ' . esc_html(self::language_name($lang !== '' ? $lang : 'en')) . '</p>';
        if ($source > 0) {
            echo '<p><strong>English master:</strong> <a href="' . esc_url(get_edit_post_link($source, 'raw') ?: '') . '">#' . $source . '</a></p>';
            echo '<p>Text is independently editable. Images inherit from the English master.</p>';
            return;
        }
        echo '<p>English master. Saving can create translations automatically if enabled in Master Settings.</p>';
        $links = self::links($post->ID);
        foreach (Kampachi_Master_Settings::languages() as $code => $name) {
            if ($code === 'en') {
                continue;
            }
            echo '<p>' . esc_html($name) . ': ';
            if (self::translated_post_id($post->ID, $code) > 0) {
                $translation = self::translated_post_id($post->ID, $code);
                echo '<a href="' . esc_url(get_edit_post_link($translation, 'raw') ?: '') . '">Edit translation</a>';
            } else {
                echo 'Not created';
            }
            echo '</p>';
        }
        $error = (string) get_post_meta($post->ID, '_kc_translation_error', true);
        if ($error !== '') {
            echo '<p style="color:#b32d2e">' . esc_html($error) . '</p>';
        }
    }

    private static function language_name(string $code): string
    {
        return Kampachi_Master_Settings::languages()[$code] ?? strtoupper($code);
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
        $links = [];
        foreach (Kampachi_Master_Settings::languages() as $code => $name) {
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
        if (self::$creating || ! in_array($post->post_type, self::TYPES, true)
            || wp_is_post_revision($postId) || wp_is_post_autosave($postId)
            || ! in_array($post->post_status, ['draft', 'pending', 'publish', 'private'], true)) {
            return;
        }
        if (absint(get_post_meta($postId, '_kc_translation_of', true)) > 0
            || (string) get_post_meta($postId, '_kc_language', true) !== ''
                && (string) get_post_meta($postId, '_kc_language', true) !== 'en') {
            return;
        }

        self::sync_images($postId);
        if (Kampachi_Master_Settings::get('auto_translate') !== '1') {
            return;
        }
        $provider = (string) Kampachi_Master_Settings::get('language_provider');
        if (! in_array($provider, ['azure', 'deepl'], true)) {
            return;
        }
        foreach (Kampachi_Master_Settings::languages() as $lang => $name) {
            if ($lang === 'en' || self::translated_post_id($postId, $lang) > 0) {
                continue;
            }
            $translated = self::translate_fields($post, $lang, $provider);
            if (is_wp_error($translated)) {
                update_post_meta($postId, '_kc_translation_error', $name . ': ' . $translated->get_error_message());
                continue;
            }
            self::$creating = true;
            try {
                $newId = wp_insert_post([
                    'post_type' => $post->post_type,
                    'post_status' => $post->post_status === 'publish' ? 'draft' : $post->post_status,
                    'post_title' => $translated['title'],
                    'post_content' => $translated['content'],
                    'post_excerpt' => $translated['excerpt'],
                    'post_name' => sanitize_title($post->post_name . '-' . $lang),
                    'post_author' => $post->post_author,
                    'menu_order' => $post->menu_order,
                ], true);
                if (is_wp_error($newId)) {
                    update_post_meta($postId, '_kc_translation_error', $name . ': ' . $newId->get_error_message());
                    continue;
                }
                update_post_meta($newId, '_kc_translation_of', $postId);
                update_post_meta($newId, '_kc_language', $lang);
                self::copy_custom_metadata($postId, $newId);
                $metaResult = self::translate_custom_metadata($postId, $newId, $lang, $provider);
                if (is_wp_error($metaResult)) {
                    update_post_meta($postId, '_kc_translation_error', $name . ' custom fields: ' . $metaResult->get_error_message());
                } else {
                    delete_post_meta($postId, '_kc_translation_error');
                }
                self::sync_images($postId);
            } finally {
                self::$creating = false;
            }
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
                add_post_meta($translatedId, $key, maybe_unserialize($value));
            }
        }
        set_post_thumbnail($translatedId, get_post_thumbnail_id($originalId));
    }

    /**
     * Translate text-based CMS fields when FIRST creating the language copy.
     * Media identifiers, URLs, dates and prices are never sent for translation.
     * Subsequent English saves synchronize media only, preserving text edits.
     */
    private static function translate_custom_metadata(int $sourceId, int $targetId, string $lang, string $provider): true|WP_Error
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
        ];
        $sourceValues = [];
        $pointers = [];
        $texts = [];
        foreach ($simpleNames as $key) {
            $metaKey = '_kampachi_' . $key;
            $value = get_post_meta($sourceId, $metaKey, true);
            if (! is_string($value) || trim($value) === '') {
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
            return true;
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
                update_post_meta($targetId, $pointer['key'], $text);
            } else {
                $sourceValues[$pointer['key']][$pointer['index']][$pointer['field']] = $text;
            }
        }
        foreach ($sourceValues as $key => $value) {
            update_post_meta($targetId, $key, wp_slash($value));
        }

        return true;
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
        $target = $to === 'zh' ? 'zh-Hans' : $to;
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
