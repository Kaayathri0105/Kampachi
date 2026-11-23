<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;
use WP_Query;
use WP_Screen;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Catering landing-page sections, editable event posts and ordering.
 */
final class Catering
{
    public const POST_TYPE = 'kc_event';

    private const PAGE_SLUG = 'catering-events';

    private const HERO_META_KEY = '_kampachi_catering_hero_media_ids';

    /** @return array<int, array{title: string, description: string}> */
    public static function default_values(): array
    {
        return [];
    }

    public static function boot(): void
    {
        add_action('init', [self::class, 'register_type']);
        add_action('admin_menu', [self::class, 'register_menus'], 20);
        add_action('add_meta_boxes', [self::class, 'register_boxes']);
        add_action('save_post_page', [self::class, 'save_landing'], 20, 2);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save_event'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
        add_action('admin_post_kampachi_order_events', [self::class, 'save_order']);
        add_action('admin_post_kampachi_save_catering_dashboard', [self::class, 'save_dashboard']);
        add_action('admin_init', [self::class, 'upgrade']);
        add_action('current_screen', [self::class, 'configure_page_editor']);
    }

    public static function configure_page_editor(WP_Screen $screen): void
    {
        if ($screen->base !== 'post' || $screen->post_type !== 'page') {
            return;
        }

        $postId = isset($_GET['post']) ? absint($_GET['post']) : 0;
        $page = $postId > 0 ? get_post($postId) : null;
        if ($page instanceof WP_Post && $page->post_name === self::PAGE_SLUG) {
            // This page is composed entirely of the structured Section 1/2/3 editors.
            remove_post_type_support('page', 'editor');
        }
    }

    public static function register_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Event Posts',
                'singular_name' => 'Catering Event',
                'add_new_item' => 'Add Catering Event',
                'edit_item' => 'Edit Catering Event',
                'all_items' => 'All Event Posts',
            ],
            'public' => true,
            'show_in_rest' => true,
            'show_in_menu' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => 'private-events', 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        ]);
    }

    public static function register_menus(): void
    {
        // WordPress already registers the top-level Catering screen. Registering
        // the same callback under the identical submenu slug renders it twice.
        add_submenu_page(
            'kampachi-catering-module',
            'Catering Event Posts',
            'Event Posts',
            'edit_posts',
            'edit.php?post_type=' . self::POST_TYPE
        );
    }

    public static function dashboard(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You cannot edit this landing page.', 'kampachi-cms'));
        }

        $page = get_page_by_path(self::PAGE_SLUG);
        $events = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'suppress_filters' => false,
        ]);

        echo '<div class="wrap kc-catering-admin kc-home-admin kc-shared-admin">';
        echo '<h1>Catering &amp; Private Events</h1>';
        echo '<p>Manage your landing page introduction, published event cards, and Trust &amp; Value Highlights in one place.</p>';

        self::render_notices();
        self::render_page_actions($page);

        if (! $page instanceof WP_Post) {
            echo '<div class="notice notice-warning inline"><p>The Catering landing page is missing. Create a published WordPress page with slug <code>catering-events</code> before editing Sections 1 and 3.</p></div>';
        } elseif (current_user_can('edit_post', $page->ID)) {
            self::render_intro_section($page);
        } else {
            echo '<p>You do not have permission to edit this landing page.</p>';
        }

        self::render_event_order($events);

        if ($page instanceof WP_Post && current_user_can('edit_post', $page->ID)) {
            self::render_values_section($page);
        }

        echo '</div>';
    }

    private static function render_notices(): void
    {
        $saved = isset($_GET['saved']) && is_string($_GET['saved'])
            ? sanitize_key(wp_unslash($_GET['saved'])) : '';
        $order = isset($_GET['ordered']) && is_string($_GET['ordered'])
            ? sanitize_key(wp_unslash($_GET['ordered'])) : '';

        if (in_array($saved, ['intro', 'values'], true)) {
            $label = $saved === 'intro' ? 'Section 1' : 'Section 3';
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($label) . ' saved successfully.</p></div>';
        } elseif ($order === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Event order saved.</p></div>';
        } elseif ($order === '0') {
            echo '<div class="notice notice-error"><p>Some events could not be reordered. Please try again.</p></div>';
        }
    }

    private static function render_page_actions(?WP_Post $page): void
    {
        echo '<div class="kc-catering-admin-actions">';
        if ($page instanceof WP_Post) {
            if (current_user_can('edit_post', $page->ID)) {
                echo '<a class="button" href="' . esc_url(get_edit_post_link($page->ID, 'raw')) . '">Open WordPress Page Editor</a>';
            }
            echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url(get_permalink($page)) . '">View Website Page ↗</a>';
        }
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . self::POST_TYPE)) . '">Manage Event Posts</a>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::POST_TYPE)) . '">+ Add New Event</a>';
        echo '</div>';
    }

    private static function start_dashboard_form(string $section): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kampachi_save_catering_dashboard">';
        echo '<input type="hidden" name="section" value="' . esc_attr($section) . '">';
        wp_nonce_field('kampachi_catering_dashboard_save', 'kampachi_catering_dashboard_nonce');
    }

    private static function render_intro_section(WP_Post $page): void
    {
        echo '<section id="kc-section-intro" class="kc-catering-section">';
        echo '<div class="kc-catering-section-head"><span>01 / HERO &amp; INTRODUCTION</span><h2>Section 1 — Hero &amp; Introduction</h2>';
        echo '<p>Add unlimited image banners and videos. Drag media into the desired order, then edit the introduction below.</p></div>';
        self::start_dashboard_form('intro');
        wp_nonce_field('kampachi_landing_save', 'kampachi_landing_nonce');
        self::render_hero_media_box($page);

        foreach ([
            'intro_subtitle' => ['Section subheading', 'text'],
            'intro_title' => ['Section title', 'text'],
            'intro_description' => ['Introduction', 'textarea'],
        ] as $field => [$label, $type]) {
            $value = get_post_meta($page->ID, '_kampachi_' . $field, true);
            $value = is_string($value) ? $value : '';
            echo '<div class="kc-catering-field">';
            echo '<label for="kc_dashboard_' . esc_attr($field) . '">' . esc_html($label) . '</label>';
            if ($type === 'textarea') {
                echo '<textarea id="kc_dashboard_' . esc_attr($field) . '" name="kampachi_landing[' . esc_attr($field) . ']" class="large-text" rows="4">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input id="kc_dashboard_' . esc_attr($field) . '" name="kampachi_landing[' . esc_attr($field) . ']" type="text" class="regular-text" value="' . esc_attr($value) . '">';
            }
            echo '</div>';
        }

        echo '<div class="kc-catering-field"><h3>SEO Metadata</h3>';
        Seo::renderBox($page);
        echo '</div>';
        submit_button('Save Section 1');
        echo '</form></section>';
    }

    /** @param array<int, WP_Post> $events */
    private static function render_event_order(array $events): void
    {
        echo '<section id="kc-section-events" class="kc-catering-section kc-catering-order">';
        echo '<div class="kc-catering-section-head"><span>02 / EVENT POSTS</span><h2>Section 2 — Event Cards</h2>';
        echo '<p>Each card comes from an individual Event Post. Drag to change display order; only published events appear on the website.</p></div>';

        if ($events === []) {
            echo '<p>No event posts yet. Select <strong>Add New Event</strong> to create the first one.</p>';
        } elseif (current_user_can('edit_others_posts')) {
            echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
            echo '<input type="hidden" name="action" value="kampachi_order_events">';
            wp_nonce_field('kampachi_order_events');
            echo '<ul class="kc-catering-sort" data-kc-event-sort>';
            foreach ($events as $event) {
                if (! $event instanceof WP_Post) {
                    continue;
                }
                echo '<li data-event-id="' . esc_attr((string) $event->ID) . '">';
                echo '<span class="dashicons dashicons-move" aria-hidden="true"></span> ';
                echo '<strong>' . esc_html($event->post_title) . '</strong> <small>' . esc_html(ucfirst($event->post_status)) . '</small> ';
                echo '<a href="' . esc_url(get_edit_post_link($event->ID, 'raw')) . '">Edit</a>';
                echo '<input type="hidden" name="event_ids[]" value="' . esc_attr((string) $event->ID) . '">';
                echo '</li>';
            }
            echo '</ul>';
            submit_button('Save Event Order');
            echo '</form>';
        } else {
            echo '<p>An administrator or editor can rearrange these event posts.</p>';
        }
        echo '</section>';
    }

    private static function render_values_section(WP_Post $page): void
    {
        echo '<section id="kc-section-values" class="kc-catering-section">';
        echo '<div class="kc-catering-section-head"><span>03 / TRUST &amp; VALUE</span><h2>Section 3 — Trust &amp; Value Highlights</h2>';
        echo '<p>Customize the heading, photo gallery and as many value highlights as needed.</p></div>';
        self::start_dashboard_form('values');
        self::render_values_box($page);
        submit_button('Save Section 3');
        echo '</form></section>';
    }

    public static function save_dashboard(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You cannot edit this landing page.', 'kampachi-cms'), '', ['response' => 403]);
        }
        check_admin_referer('kampachi_catering_dashboard_save', 'kampachi_catering_dashboard_nonce');

        $page = get_page_by_path(self::PAGE_SLUG);
        if (! $page instanceof WP_Post || ! current_user_can('edit_post', $page->ID)) {
            wp_die(esc_html__('Landing page is missing or not editable.', 'kampachi-cms'), '', ['response' => 403]);
        }

        $section = isset($_POST['section']) && is_string($_POST['section'])
            ? sanitize_key(wp_unslash($_POST['section'])) : '';

        if ($section === 'intro') {
            check_admin_referer('kampachi_landing_save', 'kampachi_landing_nonce');
            \Kampachi_Landings::save($page->ID, $page);
            self::save_hero_media($page->ID);
            HeroMotion::saveDashboard($page->ID, 'kampachi_catering_hero_media');
            Seo::savePost($page->ID, $page);
        } elseif ($section === 'values') {
            check_admin_referer('kampachi_catering_landing', 'kampachi_catering_landing_nonce');
            self::save_landing($page->ID, $page);
        } else {
            wp_die(esc_html__('Invalid catering section.', 'kampachi-cms'), '', ['response' => 400]);
        }

        wp_safe_redirect(admin_url('admin.php?page=kampachi-catering-module&saved=' . $section . '#kc-section-' . $section));
        exit;
    }

    public static function register_boxes(): void
    {
        $page = isset($_GET['post']) ? get_post(absint($_GET['post'])) : null;

        if ($page instanceof WP_Post && $page->post_type === 'page' && $page->post_name === self::PAGE_SLUG) {
            add_meta_box(
                'kampachi_catering_hero',
                'Section 1 — Hero Images & Videos',
                [self::class, 'render_hero_media_box'],
                'page',
                'normal',
                'high'
            );
            add_meta_box(
                'kampachi_catering_values',
                'Section 3 — Trust & Value Highlights',
                [self::class, 'render_values_box'],
                'page',
                'normal',
                'high'
            );
        }

        add_meta_box(
            'kampachi_catering_event_details',
            'Event Card — Images, Subheading, CTA & Outlets',
            [self::class, 'render_event_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function enqueue_admin(string $hook): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $eventScreen = $screen->post_type === self::POST_TYPE && in_array($hook, ['post.php', 'post-new.php'], true);
        $landingScreen = false;

        if ($screen->post_type === 'page' && $hook === 'post.php') {
            $post = isset($_GET['post']) ? get_post(absint($_GET['post'])) : null;
            $landingScreen = $post instanceof WP_Post && $post->post_name === self::PAGE_SLUG;
        }

        $dashboard = $hook === 'toplevel_page_kampachi-catering-module';

        if (! $eventScreen && ! $landingScreen && ! $dashboard) {
            return;
        }

        wp_enqueue_style(
            'kampachi-catering-admin',
            plugins_url('../assets/catering-admin.css', __FILE__),
            [],
            '2.4.2'
        );
        wp_enqueue_script('jquery-ui-sortable');

        if ($eventScreen || $landingScreen || $dashboard) {
            wp_enqueue_media();
        }

        wp_enqueue_script(
            'kampachi-catering-admin',
            plugins_url('../assets/catering-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable'],
            '2.4.2',
            true
        );
    }

    /** @param array<int, int> $ids */
    private static function media_picker(string $field, array $ids): void
    {
        echo '<div class="kc-catering-media" data-kc-gallery>'; 
        echo '<input type="hidden" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr(implode(',', $ids)) . '">';
        echo '<input type="hidden" name="' . esc_attr($field) . '_changed" value="0" data-kc-media-dirty>'; 
        echo '<div class="kc-catering-thumbs" data-kc-preview data-field="' . esc_attr($field) . '">';

        foreach ($ids as $id) {
            $src = wp_get_attachment_image_url($id, 'thumbnail');
            if (is_string($src)) {
                echo '<div class="kc-catering-thumb" data-id="' . esc_attr((string) $id) . '">';
                echo '<img src="' . esc_url($src) . '" alt="">';
                echo '<button type="button" class="button-link-delete" data-kc-remove-media aria-label="Remove image">×</button>';
                echo '</div>';
            }
        }

        echo '</div><p><button class="button" type="button" data-kc-media-pick data-field="' . esc_attr($field) . '">Select Images</button> ';
        echo '<button class="button" type="button" data-kc-clear-media data-field="' . esc_attr($field) . '">Clear All</button></p>';
        echo '<p class="description">Select multiple images from the Media Library, then drag thumbnails to reorder.</p></div>';
    }

    /**
     * Return the ordered hero media for a Catering page, falling back to older
     * single-video/image metadata until the new editor is first saved.
     *
     * @return array<int, int>
     */
    public static function hero_media_ids(int $postId): array
    {
        if (metadata_exists('post', $postId, self::HERO_META_KEY)) {
            $stored = get_post_meta($postId, self::HERO_META_KEY, true);
            $ids = is_array($stored) ? $stored : [];

            return self::valid_hero_ids($ids);
        }

        $legacyIds = [];
        $legacyVideo = absint(get_post_meta($postId, '_kampachi_hero_video_id', true));

        if ($legacyVideo > 0) {
            $legacyIds[] = $legacyVideo;
        }

        $legacyImages = get_post_meta($postId, '_kampachi_hero_image_ids', true);

        if (is_array($legacyImages)) {
            $legacyIds = array_merge($legacyIds, $legacyImages);
        }

        return self::valid_hero_ids($legacyIds);
    }

    /**
     * Reject missing, invalid and non-media IDs while preserving chosen order.
     *
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private static function valid_hero_ids(array $ids): array
    {
        $valid = [];

        foreach ($ids as $value) {
            $id = absint($value);

            if ($id === 0 || isset($valid[$id]) || get_post_type($id) !== 'attachment') {
                continue;
            }

            $mime = get_post_mime_type($id);

            if (wp_attachment_is_image($id) || (is_string($mime) && str_starts_with($mime, 'video/'))) {
                $valid[$id] = $id;
            }
        }

        return array_values($valid);
    }

    public static function render_hero_media_box(WP_Post $post): void
    {
        wp_nonce_field('kampachi_catering_hero_save', 'kampachi_catering_hero_nonce');
        $ids = self::hero_media_ids($post->ID);

        echo '<div class="kc-catering-hero-editor" data-kc-hero-editor>';
        echo '<h3>Hero banners &amp; videos</h3>';
        echo '<p class="description">Use as many image and video slides as needed. Images and videos can be mixed and reordered. Recommended images: 2560 × 1440 WebP or JPEG; videos: optimized MP4 (H.264).</p>';
        echo '<input type="hidden" name="kampachi_catering_hero_media" value="' . esc_attr(implode(',', $ids)) . '" data-kc-hero-ids>';
        echo '<div class="kc-catering-hero-list" data-kc-hero-list aria-label="Hero media order">';

        foreach ($ids as $id) {
            $isImage = wp_attachment_is_image($id);
            $mime = get_post_mime_type($id);
            $type = $isImage ? 'image' : 'video';
            $name = get_the_title($id);
            $thumbnail = wp_get_attachment_image_url($id, 'medium');

            echo '<div class="kc-catering-hero-item" data-kc-hero-item data-id="' . esc_attr((string) $id) . '">';
            echo '<span class="dashicons dashicons-move kc-catering-hero-handle" aria-hidden="true"></span>';

            if (is_string($thumbnail) && $thumbnail !== '') {
                echo '<img class="kc-catering-hero-preview" src="' . esc_url($thumbnail) . '" alt="">';
            } else {
                echo '<span class="kc-catering-hero-preview kc-catering-video-placeholder"><span class="dashicons dashicons-format-video" aria-hidden="true"></span></span>';
            }

            echo '<div class="kc-catering-hero-info"><strong>' . esc_html($name !== '' ? $name : 'Media #' . $id) . '</strong>';
            echo '<small>' . esc_html($type === 'image' ? 'Image banner' : 'Video: ' . (string) $mime) . '</small></div>';
            echo '<button type="button" class="button-link-delete" data-kc-hero-remove aria-label="Remove ' . esc_attr($type) . '">Remove</button>';
            echo '</div>';
        }

        echo '</div>';
        echo '<div class="kc-catering-hero-actions">';
        echo '<button type="button" class="button button-secondary" data-kc-hero-add>+ Add Images / Videos</button>';
        echo '<button type="button" class="button" data-kc-hero-clear>Clear All</button>';
        echo '</div>';
        echo '<p class="description">The first item appears first. Images auto-advance; videos play muted and advance when finished. With no hero media, the introduction remains visible by itself.</p>';
        HeroMotion::editor($post->ID, 'kampachi_catering_hero_media');
        echo '</div>';
    }

    private static function save_hero_media(int $postId): void
    {
        if (! self::can_save($postId, 'kampachi_catering_hero_nonce', 'kampachi_catering_hero_save')) {
            return;
        }

        $raw = $_POST['kampachi_catering_hero_media'] ?? null;

        if (! is_string($raw)) {
            return;
        }

        $ids = explode(',', sanitize_text_field(wp_unslash($raw)));
        update_post_meta($postId, self::HERO_META_KEY, self::valid_hero_ids($ids));
    }

    public static function render_event_box(WP_Post $post): void
    {
        wp_nonce_field('kampachi_catering_event', 'kampachi_catering_event_nonce');
        $fields = [
            'label' => 'Image badge / category (optional)',
            'subtitle' => 'Card subheading',
            'description' => 'Introduction (individual event page)',
            'cta_label' => 'CTA button label',
            'cta_url' => 'CTA destination URL (optional — defaults to event detail page)',
        ];
        echo '<div class="kc-catering-metabox">';
        echo '<p>Use Short Description (Excerpt) for cards. The introduction below appears on the individual event page. Use the main editor for additional details.</p>';

        foreach ($fields as $key => $label) {
            $value = self::event_field($post->ID, $key);
            if ($key === 'cta_label' && $value === '') {
                $value = 'Discover';
            }
            echo '<p><label for="kc_event_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label></p>';
            if ($key === 'description') {
                echo '<textarea class="widefat" rows="4" id="kc_event_description" name="kampachi_catering_event[description]">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="widefat" id="kc_event_' . esc_attr($key) . '" type="' . ($key === 'cta_url' ? 'url' : 'text') . '" name="kampachi_catering_event[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
            }
        }

        echo '<h3>Event photos</h3><p>Any number of images. The first photo becomes the card cover; visitors can browse the rest.</p>';
        $ids = self::image_ids(get_post_meta($post->ID, '_kampachi_event_image_ids', true));
        self::media_picker('kampachi_catering_event_images', $ids);
        HeroMotion::editor($post->ID, 'kampachi_catering_event_images');

        $selected = self::outlet_ids($post->ID);
        $outlets = Outlets::publishedLocations();
        echo '<h3>Locations</h3><p>Select the locations for this event.</p>';
        echo '<div class="kc-catering-outlets">';
        if ($outlets === []) {
            echo '<p>No outlet posts available. Create outlets under the Outlets module first.</p>';
        }
        foreach ($outlets as $outlet) {
            if ($outlet instanceof WP_Post) {
                echo '<label><input type="checkbox" name="kampachi_catering_outlets[]" value="' . esc_attr((string) $outlet->ID) . '" ' . checked(in_array($outlet->ID, $selected, true), true, false) . '> ' . esc_html(Outlets::locationName($outlet)) . '</label>';
            }
        }
        echo '</div></div>';
    }

    public static function render_values_box(WP_Post $post): void
    {
        wp_nonce_field('kampachi_catering_landing', 'kampachi_catering_landing_nonce');
        echo '<div class="kc-catering-metabox">';
        echo '<p>Section 2 is generated automatically from published Event Posts. Edit the gallery and highlights below to control the public Trust &amp; Value section.</p>';
        foreach ([
            'values_subtitle' => 'Section subheading',
            'values_title' => 'Section title',
            'values_description' => 'Section description',
        ] as $field => $label) {
            $value = get_post_meta($post->ID, '_kampachi_' . $field, true);
            $value = is_string($value) ? $value : '';
            echo '<p><label for="kc_' . esc_attr($field) . '"><strong>' . esc_html($label) . '</strong></label></p>';
            if ($field === 'values_description') {
                echo '<textarea class="widefat" rows="4" id="kc_' . esc_attr($field) . '" name="kampachi_catering_landing[' . esc_attr($field) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="widefat" id="kc_' . esc_attr($field) . '" name="kampachi_catering_landing[' . esc_attr($field) . ']" value="' . esc_attr($value) . '">';
            }
        }

        echo '<h3>Section gallery — unlimited photos</h3>';
        self::media_picker(
            'kampachi_catering_value_images',
            self::image_ids(get_post_meta($post->ID, '_kampachi_values_image_ids', true))
        );
        $values = get_post_meta($post->ID, '_kampachi_values_list', true);
        $values = is_array($values) ? $values : [];
        if (! metadata_exists('post', $post->ID, '_kampachi_values_list')) {
            $values = self::default_values();
        }
        echo '<h3>Values &amp; Descriptions</h3>';
        echo '<p>Add as many highlights as you need, then drag them into display order.</p>';
        echo '<div data-kc-values-list class="kc-catering-values-list">';
        foreach ($values as $index => $value) {
            if (is_array($value)) {
                self::render_value_row((string) $index, $value);
            }
        }
        echo '</div>';
        echo '<template id="kc-catering-value-template">';
        self::render_value_row('__INDEX__', []);
        echo '</template><p><button class="button" type="button" data-kc-add-value>+ Add Value</button></p></div>';
    }

    /** @param array<string, mixed> $value */
    private static function render_value_row(string $index, array $value): void
    {
        $title = isset($value['title']) && is_string($value['title']) ? $value['title'] : '';
        $description = isset($value['description']) && is_string($value['description']) ? $value['description'] : '';
        echo '<div class="kc-catering-value" data-kc-value>'; 
        echo '<div class="kc-catering-value-heading"><span class="dashicons dashicons-move" aria-hidden="true"></span><strong>Value Highlight</strong>';
        echo '<button type="button" class="button-link-delete" data-kc-remove-value>Remove</button></div>';
        echo '<p><label><strong>Value title</strong></label><input class="widefat" name="kampachi_catering_values[' . esc_attr($index) . '][title]" value="' . esc_attr($title) . '"></p>';
        echo '<p><label><strong>Description</strong></label><textarea class="widefat" rows="2" name="kampachi_catering_values[' . esc_attr($index) . '][description]">' . esc_textarea($description) . '</textarea></p></div>';
    }

    public static function save_event(int $postId, WP_Post $post): void
    {
        if (! self::can_save($postId, 'kampachi_catering_event_nonce', 'kampachi_catering_event')) {
            return;
        }
        $fields = isset($_POST['kampachi_catering_event']) && is_array($_POST['kampachi_catering_event'])
            ? wp_unslash($_POST['kampachi_catering_event']) : [];

        foreach (['label', 'subtitle', 'description', 'cta_label', 'cta_url'] as $field) {
            $input = $fields[$field] ?? '';
            if (! is_string($input)) {
                continue;
            }
            $value = match ($field) {
                'cta_url' => esc_url_raw($input),
                'description' => sanitize_textarea_field($input),
                default => sanitize_text_field($input),
            };
            update_post_meta($postId, '_kampachi_event_' . $field, $value);
        }

        self::save_images($postId, '_kampachi_event_image_ids', 'kampachi_catering_event_images');

        $raw = isset($_POST['kampachi_catering_outlets']) && is_array($_POST['kampachi_catering_outlets'])
            ? wp_unslash($_POST['kampachi_catering_outlets']) : [];
        update_post_meta($postId, '_kampachi_event_outlet_ids', Outlets::locationIds($raw));
    }

    public static function save_landing(int $postId, WP_Post $post): void
    {
        if ($post->post_name !== self::PAGE_SLUG) {
            return;
        }

        self::save_hero_media($postId);

        if (! self::can_save($postId, 'kampachi_catering_landing_nonce', 'kampachi_catering_landing')) {
            return;
        }
        $fields = isset($_POST['kampachi_catering_landing']) && is_array($_POST['kampachi_catering_landing'])
            ? wp_unslash($_POST['kampachi_catering_landing']) : [];
        foreach (['values_subtitle', 'values_title', 'values_description'] as $field) {
            $value = $fields[$field] ?? '';
            if (! is_string($value)) {
                continue;
            }
            update_post_meta(
                $postId,
                '_kampachi_' . $field,
                $field === 'values_description' ? sanitize_textarea_field($value) : sanitize_text_field($value)
            );
        }
        self::save_images($postId, '_kampachi_values_image_ids', 'kampachi_catering_value_images');
        $raw = isset($_POST['kampachi_catering_values']) && is_array($_POST['kampachi_catering_values'])
            ? wp_unslash($_POST['kampachi_catering_values']) : [];
        $values = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $title = isset($entry['title']) && is_string($entry['title']) ? sanitize_text_field($entry['title']) : '';
            $description = isset($entry['description']) && is_string($entry['description'])
                ? sanitize_textarea_field($entry['description']) : '';
            if ($title === '' && $description === '') {
                continue;
            }
            $values[] = ['title' => $title, 'description' => $description];
        }
        update_post_meta($postId, '_kampachi_values_list', $values);
    }

    private static function can_save(int $postId, string $nonceKey, string $action): bool
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || ! current_user_can('edit_post', $postId)) {
            return false;
        }
        $nonce = $_POST[$nonceKey] ?? null;
        return is_string($nonce) && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), $action) !== false;
    }

    private static function save_images(int $postId, string $metaKey, string $formKey): void
    {
        $dirty = $_POST[$formKey . '_changed'] ?? '0';
        if ($dirty !== '1') {
            return;
        }
        $raw = $_POST[$formKey] ?? '';
        $ids = is_string($raw) ? explode(',', sanitize_text_field(wp_unslash($raw))) : [];
        $validated = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id && wp_attachment_is_image($id)) {
                $validated[$id] = $id;
            }
        }
        update_post_meta($postId, $metaKey, array_values($validated));
    }

    /** @return array<int, int> */
    public static function image_ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('absint', $value)));
    }

    /** @return list<int> */
    public static function outlet_ids(int $postId): array
    {
        return Outlets::locationIds(get_post_meta($postId, '_kampachi_event_outlet_ids', true));
    }

    public static function event_field(int $postId, string $key): string
    {
        $value = get_post_meta($postId, '_kampachi_event_' . $key, true);
        return is_string($value) ? $value : '';
    }

    public static function save_order(): void
    {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }
        check_admin_referer('kampachi_order_events');
        $raw = isset($_POST['event_ids']) && is_array($_POST['event_ids'])
            ? wp_unslash($_POST['event_ids']) : [];
        $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));

        $failed = false;
        foreach ($ids as $index => $id) {
            if (get_post_type($id) !== self::POST_TYPE || ! current_user_can('edit_post', $id)) {
                continue;
            }
            $result = wp_update_post(['ID' => $id, 'menu_order' => $index], true);
            if (is_wp_error($result) || $result === 0) {
                $failed = true;
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=kampachi-catering-module&ordered=' . ($failed ? '0' : '1')));
        exit;
    }

    /**
     * Update rewrite rules without inserting sample event posts.
     */
    public static function upgrade(): void
    {
        if (! current_user_can('manage_options') || get_option('kampachi_catering_schema') === '1') {
            return;
        }
        self::register_type();
        update_option('kampachi_catering_schema', '1', false);
        flush_rewrite_rules(false);
    }
}
