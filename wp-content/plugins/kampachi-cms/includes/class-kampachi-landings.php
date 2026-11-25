<?php

declare(strict_types=1);

use Kampachi\CMS\Awards;
use Kampachi\CMS\AwardsLanding;
use Kampachi\CMS\HeroMotion;
use Kampachi\CMS\OffersLanding;
use Kampachi\CMS\OutletsLanding;
use Kampachi\CMS\Press;
use Kampachi\CMS\PressLanding;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared WordPress page editor controls for non-homepage module landings.
 */
final class Kampachi_Landings
{
    public const POST_TYPE = 'kc_landing';

    public const PAGES = [
        'promotion' => 'Promotions',
        'sake' => 'Sake',
        'catering-events' => 'Catering & Events',
        'about-us' => 'About Us',
        'contact-us' => 'Contact Us',
        'careers' => 'Careers',
        'privacy-policy' => 'Privacy Policy',
        'outlets' => 'Outlets',
        Press::PAGE_SLUG => 'Press & Media',
        Awards::PAGE_SLUG => Awards::PAGE_TITLE,
    ];

    public static function boot(): void
    {
        add_action('init', [self::class, 'register_type']);
        add_action('wp_loaded', [self::class, 'install']);
        add_action('parse_request', [self::class, 'route']);
        add_filter('post_type_link', [self::class, 'permalink'], 10, 4);
        add_action('add_meta_boxes_page', [self::class, 'register_box']);
        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'register_box']);
        add_action('save_post_page', [self::class, 'save'], 10, 2);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_menu', [self::class, 'register_menus'], 30);
    }

    public static function register_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => 'Module Landings', 'singular_name' => 'Module Landing'],
            'public' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'capability_type' => 'page',
            'map_meta_cap' => true,
            'rewrite' => false,
            'has_archive' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        ]);
    }

    public static function page(string $slug): ?WP_Post
    {
        $landing = get_page_by_path($slug, OBJECT, self::POST_TYPE);

        return $landing instanceof WP_Post ? $landing : null;
    }

    public static function source_slug(WP_Post $post): string
    {
        $sourceId = absint(get_post_meta($post->ID, '_kc_translation_of', true));
        $source = $sourceId > 0 ? get_post($sourceId) : null;

        return $source instanceof WP_Post ? $source->post_name : $post->post_name;
    }

    public static function permalink(string $url, WP_Post $post, bool $leaveName, bool $sample): string
    {
        if ($post->post_type !== self::POST_TYPE || get_option('permalink_structure') === '') {
            return $url;
        }
        if (! $sample && ! in_array($post->post_status, ['publish', 'private'], true)) {
            return $url;
        }

        $slug = $leaveName ? '%postname%' : $post->post_name;

        return home_url(user_trailingslashit('/' . $slug, 'single'));
    }

    public static function route(WP $request): void
    {
        $slug = $request->request;
        if (! is_string($slug) || $slug === '' || str_contains($slug, '/')) {
            return;
        }

        $landing = self::page($slug);
        if ($landing === null) {
            return;
        }

        $query = array_intersect_key($request->query_vars, array_flip(['preview', 'page', 'cpage']));
        $request->query_vars = $query + ['post_type' => self::POST_TYPE, 'p' => $landing->ID];
    }

    public static function install(): void
    {
        if (get_option('kampachi_module_landings_v1') === '1') {
            return;
        }

        $pages = self::PAGES + ['47in27' => '47in27'];
        $complete = true;
        foreach ($pages as $slug => $title) {
            if (! self::install_landing($slug, $title)) {
                $complete = false;
            }
        }
        if ($complete) {
            update_option('kampachi_module_landings_v1', '1', false);
        }
    }

    private static function install_landing(string $slug, string $title): bool
    {
        $landing = self::page($slug);
        if ($landing === null) {
            $legacy = get_page_by_path($slug, OBJECT, 'page');
            if (! $legacy instanceof WP_Post) {
                $legacy = self::trashed_page($slug);
            }

            if ($legacy !== null) {
                if ($legacy->post_status === 'trash' && ! self::restore_legacy_page($legacy->ID)) {
                    return false;
                }
                if (! self::convert_page($legacy->ID)) {
                    return false;
                }
            } else {
                $created = wp_insert_post([
                    'post_type' => self::POST_TYPE,
                    'post_status' => 'publish',
                    'post_name' => $slug,
                    'post_title' => $title,
                ], true);
                if (is_wp_error($created) || $created === 0) {
                    return false;
                }
            }
            $landing = self::page($slug);
        }
        if ($landing === null) {
            return false;
        }

        $translations = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'numberposts' => -1,
            'fields' => 'ids',
            'kc_include_translations' => true,
            'meta_key' => '_kc_translation_of',
            'meta_value' => (string) $landing->ID,
        ]);
        foreach ($translations as $id) {
            if (! self::convert_page($id)) {
                return false;
            }
        }

        return true;
    }

    private static function trashed_page(string $slug): ?WP_Post
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'trash',
            'numberposts' => 1,
            'meta_key' => '_wp_desired_post_slug',
            'meta_value' => $slug,
            'post_parent' => 0,
            'orderby' => ['modified' => 'DESC', 'ID' => 'DESC'],
        ]);

        return $pages[0] ?? null;
    }

    private static function convert_page(int $id): bool
    {
        if (! set_post_type($id, self::POST_TYPE) && get_post_type($id) !== self::POST_TYPE) {
            return false;
        }

        foreach (wp_get_associated_nav_menu_items($id, 'post_type') as $menuItem) {
            update_post_meta($menuItem, '_menu_item_object', self::POST_TYPE);
        }

        return true;
    }

    private static function restore_legacy_page(int $id): bool
    {
        $restoreStatus = static fn (string $draft, int $postId, string $previous): string =>
            $postId === $id && $previous === 'publish' ? 'publish' : $draft;

        add_filter('wp_untrash_post_status', $restoreStatus, 10, 3);
        try {
            return wp_untrash_post($id) instanceof WP_Post;
        } finally {
            remove_filter('wp_untrash_post_status', $restoreStatus, 10);
        }
    }

    public static function register_menus(): void
    {
        $landings = [
            'kc_offer' => ['promotion', 'Offers Landing', OffersLanding::class],
            'kc_outlet' => ['outlets', 'Outlets Landing', OutletsLanding::class],
            Press::POST_TYPE => [Press::PAGE_SLUG, 'Press & Media Landing', PressLanding::class],
            Awards::POST_TYPE => [Awards::PAGE_SLUG, 'Awards & Accolades Landing', AwardsLanding::class],
        ];
        foreach ($landings as $type => [$slug, $title, $editor]) {
            $hook = add_submenu_page(
                'edit.php?post_type=' . $type,
                $title,
                'Landing Page',
                'edit_pages',
                'kampachi-landing-' . $slug,
                [$editor, 'render']
            );
            if ($hook !== false) {
                add_action('load-' . $hook, [$editor, 'load']);
            }
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

        if (! $post instanceof WP_Post
            || ! in_array($post->post_type, ['page', self::POST_TYPE], true)
            || ! isset(self::PAGES[$post->post_name])
        ) {
            return;
        }

        self::enqueue_media();
    }

    public static function enqueue_media(): void
    {
        wp_enqueue_media();
        wp_enqueue_style(
            'kampachi-landings-admin',
            plugins_url('../assets/landings-admin.css', __FILE__),
            [],
            (string) filemtime(dirname(__DIR__) . '/assets/landings-admin.css')
        );
        wp_enqueue_script(
            'kampachi-landings-admin',
            plugins_url('../assets/landings-admin.js', __FILE__),
            ['jquery'],
            (string) filemtime(dirname(__DIR__) . '/assets/landings-admin.js'),
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

        if ($post->post_name === 'promotion') {
            echo '<p>Manage the hero in <a href="'
                . esc_url(admin_url('edit.php?post_type=kc_offer&page=kampachi-landing-promotion'))
                . '">Offers &amp; Events → Landing Page</a>.</p>';
        } elseif (in_array($post->post_name, ['catering-events', 'sake'], true)) {
            echo '<p>This page uses its own module editor for hero images and videos. Edit hero media in the Sake or Catering landing page dashboard.</p>';
        } else {
            echo '<p>Choose images for this landing page below. Saved images take priority over a previously selected video. Clear both to hide the hero. Each image can have its own Ken Burns direction.</p>';
        }

        foreach ($fields as $key => [$label, $type]) {
            $value = get_post_meta($post->ID, '_kampachi_' . $key, true);
            $value = is_string($value) ? $value : '';
            echo '<p><label for="kc_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label></p>';

            if ($type === 'textarea' && self::has_rich_intro($post)) {
                wp_editor($value, 'kc_landing_page_intro', [
                    'textarea_name' => 'kampachi_landing[intro_description]',
                    'textarea_rows' => 6,
                ]);
            } elseif ($type === 'textarea') {
                echo '<textarea class="large-text" rows="4" id="kc_' . esc_attr($key) . '" name="kampachi_landing[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="large-text" type="text" id="kc_' . esc_attr($key) . '" name="kampachi_landing[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
            }
        }

        if (in_array($post->post_name, ['promotion', 'catering-events', 'sake'], true)) {
            return;
        }

        self::render_hero($post);
    }

    public static function render_hero(WP_Post $post): void
    {
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
        echo '<ul id="kc-landing-images-thumbnails" class="kc-landing-image-thumbnails" role="list" aria-label="'
            . esc_attr__('Selected hero images', 'kampachi-cms') . '">';
        foreach ($slideIds as $slideId) {
            $image = wp_get_attachment_image($slideId, 'medium', false, ['alt' => '']);
            if ($image === '') {
                continue;
            }

            /* translators: %d is the attachment ID. */
            $title = get_the_title($slideId) ?: sprintf(__('Image #%d', 'kampachi-cms'), $slideId);
            echo '<li>';
            echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress.
            echo '<span>' . esc_html($title) . '</span></li>';
        }
        echo '</ul>';
        HeroMotion::editor($post->ID, 'kampachi_landing[hero_image_ids]');
        echo '</div>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if (! isset(self::PAGES[self::source_slug($post)]) || ! current_user_can('edit_post', $postId)) {
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

        self::save_fields($post, $raw);
    }

    /** Save landing metadata after the caller has checked its form nonce. */
    public static function save_fields(WP_Post $post, array $raw): void
    {
        $slug = self::source_slug($post);
        if (! isset(self::PAGES[$slug]) || ! current_user_can('edit_post', $post->ID)) {
            return;
        }

        $postId = $post->ID;
        foreach (['intro_subtitle', 'intro_title', 'intro_description'] as $field) {
            $input = $raw[$field] ?? null;
            if (! is_string($input)) {
                continue;
            }

            $value = $field === 'intro_description' && self::has_rich_intro($post)
                ? wp_kses_post($input)
                : sanitize_textarea_field($input);
            update_post_meta($postId, '_kampachi_' . $field, wp_slash($value));
        }

        if (in_array($slug, ['catering-events', 'sake'], true)) {
            return;
        }

        if (array_key_exists('hero_video_id', $raw)) {
            $videoId = absint($raw['hero_video_id']);
            $mime = $videoId > 0 ? get_post_mime_type($videoId) : '';
            update_post_meta(
                $postId,
                '_kampachi_hero_video_id',
                is_string($mime) && str_starts_with($mime, 'video/') ? $videoId : 0
            );
        }

        if (array_key_exists('hero_image_ids', $raw)) {
            $inputIds = $raw['hero_image_ids'];
            $ids = is_string($inputIds) ? explode(',', $inputIds) : [];
            $images = array_values(array_unique(array_filter(array_map('absint', $ids), 'wp_attachment_is_image')));
            update_post_meta($postId, '_kampachi_hero_image_ids', array_slice($images, 0, 20));
        }
    }

    private static function has_rich_intro(WP_Post $post): bool
    {
        return in_array(
            self::source_slug($post),
            ['promotion', 'sake', 'outlets', Press::PAGE_SLUG, Awards::PAGE_SLUG],
            true
        );
    }
}
