<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Editable metadata for every landing page and public content module.
 */
final class Seo
{
    private const TITLE_KEY = '_kampachi_seo_title';

    private const DESCRIPTION_KEY = '_kampachi_seo_description';

    private const HOMEPAGE_OPTION = 'kampachi_homepage_seo';

    private const FIELDS = ['title' => self::TITLE_KEY, 'description' => self::DESCRIPTION_KEY];

    private const LANDING_DEFAULTS = [
        'sake' => [
            'title' => 'Japanese Sake Collection | Kampachi Malaysia',
            'description' => 'Explore Kampachi Malaysia’s Japanese sake collection. Discover independent breweries across Japan and browse the bottles featured at Kampachi.',
        ],
        'catering-events' => [
            'title' => 'Japanese Catering & Private Dining | Kampachi Malaysia',
            'description' => 'Plan private dining, celebrations and corporate events with Kampachi Malaysia. Explore Japanese catering and enquire about your event.',
        ],
    ];

    private const POST_TYPES = [
        'page', 'post', 'kc_offer', 'kc_sake', 'kc_outlet',
        'kc_event', 'kc_job', 'kc_prefecture', Press::POST_TYPE,
        Awards::POST_TYPE,
    ];

    public static function boot(): void
    {
        add_action('add_meta_boxes', [self::class, 'registerBoxes']);
        add_action('save_post', [self::class, 'savePost'], 30, 2);
        add_action('wp_head', [self::class, 'renderDescription'], 5);
        add_filter('pre_get_document_title', [self::class, 'filterDocumentTitle'], 99);

        foreach (['wpseo_title', 'rank_math/frontend/title', 'aioseo_title'] as $filter) {
            add_filter($filter, [self::class, 'filterTitle'], 99);
        }

        foreach (['wpseo_metadesc', 'rank_math/frontend/description', 'aioseo_description'] as $filter) {
            add_filter($filter, [self::class, 'filterDescription'], 99);
        }
    }

    public static function registerBoxes(): void
    {
        foreach (self::POST_TYPES as $postType) {
            add_meta_box(
                'kampachi-seo',
                __('SEO Meta Title / Description', 'kampachi-cms'),
                [self::class, 'renderBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    public static function renderBox(WP_Post $post): void
    {
        $defaults = self::landingDefaults($post);
        wp_nonce_field('kampachi_save_seo', 'kampachi_seo_nonce');
        echo '<p><label for="kampachi-seo-title"><strong>';
        echo esc_html__('SEO meta title', 'kampachi-cms');
        echo '</strong></label></p>';
        echo '<input class="widefat" id="kampachi-seo-title" name="kampachi_seo[title]" type="text" value="';
        echo esc_attr(self::meta($post->ID, self::TITLE_KEY));
        echo '" placeholder="' . esc_attr($defaults['title'] ?? get_the_title($post)) . '">';
        echo '<p class="description">' . esc_html($defaults !== []
            ? 'Leave blank to use the suggested title.'
            : 'Leave blank to use the WordPress page title.') . '</p>';
        echo '<p><label for="kampachi-seo-description"><strong>';
        echo esc_html__('SEO meta description', 'kampachi-cms');
        echo '</strong></label></p>';
        echo '<textarea class="widefat" id="kampachi-seo-description" name="kampachi_seo[description]" rows="4"';
        if ($defaults !== []) {
            echo ' placeholder="' . esc_attr($defaults['description']) . '"';
        }
        echo '>';
        echo esc_textarea(self::meta($post->ID, self::DESCRIPTION_KEY));
        echo '</textarea><p class="description">' . esc_html($defaults !== []
            ? 'Leave blank to use the suggested description.'
            : 'Leave blank to derive a description from this page’s CMS content.') . ' ';
        echo 'Each translated page has its own SEO fields.</p>';
    }

    public static function savePost(int $postId, WP_Post $post): void
    {
        if (
            ! in_array($post->post_type, self::POST_TYPES, true)
            || wp_is_post_revision($postId)
            || wp_is_post_autosave($postId)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || ! current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $nonce = $_POST['kampachi_seo_nonce'] ?? null;
        if (
            ! is_string($nonce)
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'kampachi_save_seo')
        ) {
            return;
        }

        $fields = $_POST['kampachi_seo'] ?? null;
        if (! is_array($fields)) {
            return;
        }

        self::saveFields($postId, self::sanitizeFields(wp_unslash($fields)));
    }

    /**
     * A static homepage shares the same SEO values as its Pages editor.
     *
     * @return array{title: string, description: string}|null
     */
    public static function homepageFields(): ?array
    {
        $postId = self::homepagePostId();
        if ($postId === 0) {
            return self::homepageOptions();
        }
        if (! current_user_can('edit_post', $postId)) {
            return null;
        }

        return [
            'title' => self::meta($postId, self::TITLE_KEY),
            'description' => self::meta($postId, self::DESCRIPTION_KEY),
        ];
    }

    /** The Homepage editor checks its own nonce before calling this method. */
    public static function saveHomepage(mixed $input): void
    {
        if (! is_array($input) || ! current_user_can('edit_pages')) {
            return;
        }

        $fields = self::sanitizeFields($input);
        if ($fields === []) {
            return;
        }

        $postId = self::homepagePostId();
        if ($postId === 0) {
            update_option(self::HOMEPAGE_OPTION, array_replace(self::homepageOptions(), $fields), false);
        } elseif (current_user_can('edit_post', $postId)) {
            self::saveFields($postId, $fields);
        }
    }

    private static function homepagePostId(): int
    {
        if (get_option('show_on_front') !== 'page') {
            return 0;
        }

        $postId = absint(get_option('page_on_front'));

        return $postId > 0 && get_post_type($postId) === 'page' ? $postId : 0;
    }

    /** @return array{title: string, description: string} */
    private static function homepageOptions(): array
    {
        $fields = get_option(self::HOMEPAGE_OPTION, []);
        $fields = is_array($fields) ? $fields : [];

        return [
            'title' => is_string($fields['title'] ?? null) ? trim($fields['title']) : '',
            'description' => is_string($fields['description'] ?? null) ? trim($fields['description']) : '',
        ];
    }

    /** @return array<string, string> */
    private static function sanitizeFields(array $fields): array
    {
        $clean = [];
        foreach (self::FIELDS as $field => $key) {
            $value = $fields[$field] ?? null;
            if (is_string($value)) {
                $clean[$field] = $key === self::TITLE_KEY ? sanitize_text_field($value) : sanitize_textarea_field($value);
            }
        }

        return $clean;
    }

    /** @param array<string, string> $fields */
    private static function saveFields(int $postId, array $fields): void
    {
        foreach ($fields as $field => $value) {
            update_post_meta($postId, self::FIELDS[$field], wp_slash($value));
        }
    }

    public static function filterDocumentTitle(string $title): string
    {
        $custom = self::filterTitle('');

        return $custom !== '' ? esc_html($custom) : $title;
    }

    public static function filterTitle(string $title): string
    {
        $custom = '';
        $postId = self::requestPostId();
        if ($postId > 0) {
            $post = get_post($postId);
            if ($post instanceof WP_Post) {
                $custom = self::postTitle($post);
            }
        } elseif (is_front_page()) {
            $custom = self::homepageOptions()['title'];
        }

        return $custom !== '' ? $custom : $title;
    }

    public static function filterDescription(string $description): string
    {
        $custom = self::requestDescription();

        return $custom !== '' ? $custom : $description;
    }

    public static function renderDescription(): void
    {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || function_exists('aioseo')) {
            return;
        }

        $description = self::requestDescription();
        if ($description !== '') {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
    }

    private static function requestDescription(): string
    {
        $postId = self::requestPostId();
        if ($postId > 0) {
            return self::description($postId);
        }
        if (! is_front_page()) {
            return '';
        }

        $custom = self::homepageOptions()['description'];
        if ($custom !== '') {
            return $custom;
        }

        $settings = get_option('kampachi_homepage_sections', []);
        $intro = is_array($settings) ? ($settings['intro_description'] ?? '') : '';
        $description = is_string($intro) ? self::excerpt($intro) : '';

        return $description !== '' ? $description : get_bloginfo('description');
    }

    private static function requestPostId(): int
    {
        if (is_singular(self::POST_TYPES)) {
            return get_queried_object_id();
        }

        return is_post_type_archive('kc_offer') && class_exists(OffersLanding::class)
            ? OffersLanding::queriedPageId()
            : 0;
    }

    public static function description(int $postId): string
    {
        $custom = self::meta($postId, self::DESCRIPTION_KEY);
        if ($custom !== '') {
            return $custom;
        }

        $post = get_post($postId);

        if (! $post instanceof WP_Post) {
            return '';
        }

        return self::landingDefaults($post)['description'] ?? self::excerpt(self::sourceDescription($post));
    }

    private static function postTitle(WP_Post $post): string
    {
        $custom = self::meta($post->ID, self::TITLE_KEY);

        return $custom !== '' ? $custom : (self::landingDefaults($post)['title'] ?? '');
    }

    /** @return array{title: string, description: string}|array{} */
    private static function landingDefaults(WP_Post $post): array
    {
        if ($post->post_type !== 'page' || (int) $post->post_parent !== 0) {
            return [];
        }

        return self::LANDING_DEFAULTS[$post->post_name] ?? [];
    }

    private static function excerpt(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes($text))) ?? '');

        return wp_html_excerpt($text, 160, '…');
    }

    private static function sourceDescription(WP_Post $post): string
    {
        $key = match ($post->post_type) {
            'page' => '_kampachi_intro_description',
            'kc_offer' => '_kampachi_offer_introduction',
            'kc_sake' => '_kampachi_sake_description',
            'kc_outlet' => '_kampachi_outlet_intro',
            'kc_event' => '_kampachi_event_description',
            default => '',
        };

        foreach ([$post->post_excerpt, $key !== '' ? self::meta($post->ID, $key) : '', self::landingDescription($post)] as $text) {
            if (trim(wp_strip_all_tags($text)) !== '') {
                return $text;
            }
        }

        return $post->post_content;
    }

    private static function landingDescription(WP_Post $post): string
    {
        if ($post->post_type !== 'page') {
            return '';
        }

        $sourceId = absint(get_post_meta($post->ID, '_kc_translation_of', true));
        $slug = $sourceId > 0 ? get_post_field('post_name', $sourceId) : $post->post_name;
        $option = match ($slug) {
            'home' => 'kampachi_homepage_sections',
            '47in27' => 'kampachi_47in27_sections',
            default => (int) get_option('page_on_front') === $post->ID ? 'kampachi_homepage_sections' : '',
        };
        $settings = $option !== '' ? get_option($option, []) : [];
        $value = is_array($settings) ? ($settings['intro_description'] ?? '') : '';

        return is_string($value) ? $value : '';
    }

    public static function dashboard(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You cannot edit SEO metadata.', 'kampachi-cms'), '', ['response' => 403]);
        }

        $posts = get_posts([
            'post_type' => self::POST_TYPES,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby' => ['post_type' => 'ASC', 'title' => 'ASC'],
            'suppress_filters' => false,
        ]);
        echo '<div class="wrap kc-home-admin kc-shared-admin"><h1>SEO</h1>';
        echo '<p>Open a landing page or module post and edit its SEO Meta Title / Description box. ';
        echo 'Blank fields use the page’s current CMS content.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Page / Post</th><th>Module</th>';
        echo '<th>SEO title</th><th>Meta description</th></tr></thead><tbody>';

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post || ! current_user_can('edit_post', $post->ID)) {
                continue;
            }

            $type = get_post_type_object($post->post_type);
            $title = self::postTitle($post);
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($post->ID, 'raw')) . '">';
            echo esc_html(get_the_title($post)) . '</a> <small>' . esc_html($post->post_status) . '</small></td>';
            echo '<td>' . esc_html($type !== null ? $type->labels->singular_name : $post->post_type) . '</td>';
            echo '<td>' . esc_html($title !== '' ? $title : get_the_title($post)) . '</td>';
            echo '<td>' . esc_html(self::description($post->ID)) . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    private static function meta(int $postId, string $key): string
    {
        $value = get_post_meta($postId, $key, true);

        return is_string($value) ? trim($value) : '';
    }
}
