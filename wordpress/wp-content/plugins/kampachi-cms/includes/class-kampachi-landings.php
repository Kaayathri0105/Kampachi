<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared WordPress page editor controls for non-homepage module landings.
 */
final class Kampachi_Landings
{
    public const PAGES = [
        'promotion' => 'Promotions',
        'sake' => 'Sake',
        'catering-events' => 'Catering & Events',
        'about-us' => 'About Us',
        'contact-us' => 'Contact Us',
        'careers' => 'Careers',
        'privacy-policy' => 'Privacy Policy',
        'outlets' => 'Outlets',
    ];

    public static function boot(): void
    {
        add_action('add_meta_boxes_page', [self::class, 'register_box']);
        add_action('save_post_page', [self::class, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_menu', [self::class, 'register_menus'], 30);
    }

    public static function register_menus(): void
    {
        foreach (['kc_offer' => 'promotion', 'kc_outlet' => 'outlets'] as $type => $slug) {
            add_submenu_page(
                'edit.php?post_type=' . $type,
                'Landing Page',
                'Landing Page',
                'edit_pages',
                'kampachi-landing-' . $slug,
                static function () use ($slug): void {
                    $page = get_page_by_path($slug);
                    echo '<div class="wrap kc-home-admin kc-shared-admin kc-module-admin"><h1>' . esc_html(self::PAGES[$slug]) . ' Landing Page</h1><section class="kc-module-panel"><h2>Landing Page Content</h2>';
                    if ($page instanceof WP_Post) {
                        echo '<p><a class="button button-primary" href="' . esc_url(get_edit_post_link($page->ID, 'raw')) . '">Edit landing page &amp; hero</a></p>';
                        echo '<p><a class="button" href="' . esc_url(get_permalink($page)) . '">View landing page</a></p>';
                    } else {
                        echo '<p>Please activate the Kampachi CMS plugin again or create the page with slug <code>' . esc_html($slug) . '</code>.</p>';
                    }
                    echo '</section></div>';
                }
            );
        }
    }

    public static function register_box(WP_Post $post): void
    {
        if (! in_array($post->post_name, array_keys(self::PAGES), true)) {
            return;
        }

        add_meta_box(
            'kampachi_landing_sections',
            $post->post_name === 'catering-events'
                ? 'Section 1 — Catering Introduction Text'
                : ($post->post_name === 'sake' ? 'Section 1 — Sake Introduction Text' : 'Kampachi Page Introduction'),
            [self::class, 'render'],
            'page',
            'normal',
            'high'
        );
    }

    public static function assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $postId = isset($_GET['post']) ? absint($_GET['post']) : 0;
        $post = $postId > 0 ? get_post($postId) : null;

        if (! $post instanceof WP_Post || $post->post_type !== 'page' || ! isset(self::PAGES[$post->post_name])) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'kampachi-landings-admin',
            plugins_url('../assets/landings-admin.js', __FILE__),
            ['jquery'],
            '2.3.0',
            true
        );
    }

    public static function render(WP_Post $post): void
    {
        wp_nonce_field('kampachi_landing_save', 'kampachi_landing_nonce');
        $fields = [
            'intro_subtitle' => ['Section subtitle', 'text'],
            'intro_title' => ['Section title (optional; defaults to page title)', 'text'],
            'intro_description' => ['Introduction text', 'textarea'],
        ];

        if (in_array($post->post_name, ['catering-events', 'sake'], true)) {
            echo '<p>This page uses its own module editor for hero images and videos. Edit hero media in the Sake or Catering landing page dashboard.</p>';
        } else {
            echo '<p>Choose images for this landing page below. Saved images take priority over a previously selected video. Clear both to hide the hero. Each image can have its own Ken Burns direction.</p>';
        }

        foreach ($fields as $key => [$label, $type]) {
            $value = get_post_meta($post->ID, '_kampachi_' . $key, true);
            $value = is_string($value) ? $value : '';
            echo '<p><label for="kc_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label></p>';

            if ($type === 'textarea') {
                echo '<textarea class="large-text" rows="4" id="kc_' . esc_attr($key) . '" name="kampachi_landing[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="large-text" type="text" id="kc_' . esc_attr($key) . '" name="kampachi_landing[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
            }
        }

        if (in_array($post->post_name, ['catering-events', 'sake'], true)) {
            return;
        }

        $videoId = absint(get_post_meta($post->ID, '_kampachi_hero_video_id', true));
        $slideIds = get_post_meta($post->ID, '_kampachi_hero_image_ids', true);
        $slideIds = is_array($slideIds) ? array_map('absint', $slideIds) : [];
        echo '<div class="kc-landing-media">';
        echo '<p><strong>Hero video (MP4)</strong></p>';
        echo '<input type="hidden" id="kc-landing-video" name="kampachi_landing[hero_video_id]" value="' . esc_attr((string) $videoId) . '">';
        echo '<button class="button kc-landing-media-picker" type="button" data-field="kc-landing-video" data-type="video">Choose video</button> ';
        echo '<button class="button kc-landing-media-clear" type="button" data-field="kc-landing-video">Clear</button>';
        echo '<p id="kc-landing-video-preview" class="description">' . esc_html($videoId ? (string) get_the_title($videoId) : 'No video selected') . '</p>';
        echo '<p><strong>Hero images (multi-select; recommended 2560 × 1440 WebP)</strong></p>';
        echo '<input type="hidden" id="kc-landing-images" name="kampachi_landing[hero_image_ids]" value="' . esc_attr(implode(',', $slideIds)) . '">';
        echo '<button class="button kc-landing-media-picker" type="button" data-field="kc-landing-images" data-type="image" data-multiple="1">Choose hero images</button> ';
        echo '<button class="button kc-landing-media-clear" type="button" data-field="kc-landing-images">Clear</button>';
        echo '<p id="kc-landing-images-preview" class="description">' . esc_html($slideIds ? count($slideIds) . ' selected images' : 'No hero images selected') . '</p>';
        \Kampachi\CMS\HeroMotion::editor($post->ID, 'kampachi_landing[hero_image_ids]');
        echo '</div>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if (! isset(self::PAGES[$post->post_name]) || ! current_user_can('edit_post', $postId)) {
            return;
        }

        if (wp_is_post_revision($postId) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (! isset($_POST['kampachi_landing_nonce']) || ! is_string($_POST['kampachi_landing_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['kampachi_landing_nonce']));

        if (! wp_verify_nonce($nonce, 'kampachi_landing_save')) {
            return;
        }

        $raw = isset($_POST['kampachi_landing']) && is_array($_POST['kampachi_landing'])
            ? wp_unslash($_POST['kampachi_landing'])
            : [];

        foreach (['intro_subtitle', 'intro_title', 'intro_description'] as $field) {
            $input = $raw[$field] ?? '';
            update_post_meta(
                $postId,
                '_kampachi_' . $field,
                is_string($input) ? sanitize_textarea_field($input) : ''
            );
        }

        if (in_array($post->post_name, ['catering-events', 'sake'], true)) {
            return;
        }

        $videoId = absint($raw['hero_video_id'] ?? 0);
        $mime = $videoId > 0 ? get_post_mime_type($videoId) : '';
        update_post_meta(
            $postId,
            '_kampachi_hero_video_id',
            is_string($mime) && str_starts_with($mime, 'video/') ? $videoId : 0
        );

        $inputIds = $raw['hero_image_ids'] ?? '';
        $ids = is_string($inputIds) ? explode(',', $inputIds) : [];
        $images = array_values(array_unique(array_filter(array_map('absint', $ids), 'wp_attachment_is_image')));
        update_post_meta($postId, '_kampachi_hero_image_ids', array_slice($images, 0, 20));
    }
}
