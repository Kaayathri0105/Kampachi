<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sake landing editor, sortable brewery cards and brand details.
 * Reuses the site's existing kc_sake post type and metadata where possible.
 */
final class Sake
{
    public const POST_TYPE = 'kc_sake';

    private const PAGE_SLUG = 'sake';

    private const HERO_META_KEY = '_kampachi_sake_hero_media_ids';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 30);
        add_action('add_meta_boxes', [self::class, 'register_boxes']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save_post'], 20, 2);
        add_action('admin_post_kampachi_save_sake_landing', [self::class, 'save_landing']);
        add_action('admin_post_kampachi_order_sake', [self::class, 'save_order']);
        add_filter('enter_title_here', [self::class, 'titlePlaceholder'], 10, 2);
    }

    public static function titlePlaceholder(string $placeholder, WP_Post $post): string
    {
        return $post->post_type === self::POST_TYPE
            ? 'Brewery / brand name (e.g. Brew X)'
            : $placeholder;
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            'Sake Landing Page',
            'Landing Page',
            'edit_pages',
            'kampachi-landing-sake',
            [self::class, 'dashboard']
        );
    }

    public static function register_boxes(): void
    {
        add_meta_box(
            'kampachi_sake_details',
            'Brewery / Brand — Introduction & Images',
            [self::class, 'render_post_box'],
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

        $editingPost = $screen->post_type === self::POST_TYPE
            && in_array($hook, ['post.php', 'post-new.php'], true);
        $dashboard = str_contains($hook, 'kampachi-landing-sake');

        if (! $editingPost && ! $dashboard) {
            return;
        }

        wp_enqueue_media();
        Content::enqueueDescriptionEditor();
        wp_enqueue_script('jquery-ui-sortable');
        $assets = dirname(__DIR__) . '/assets/';
        wp_enqueue_style(
            'kampachi-sake-editor',
            plugins_url('../assets/catering-admin.css', __FILE__),
            [],
            (string) filemtime($assets . 'catering-admin.css')
        );
        wp_enqueue_script(
            'kampachi-sake-editor',
            plugins_url('../assets/catering-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable'],
            (string) filemtime($assets . 'catering-admin.js'),
            true
        );

        if ($editingPost) {
            wp_enqueue_style(
                'kampachi-sake-bottles',
                plugins_url('../assets/sake-admin.css', __FILE__),
                ['kampachi-sake-editor'],
                '3.2.0'
            );
        }
        wp_enqueue_script(
            'kampachi-sake-bottles',
            plugins_url('../assets/sake-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable', 'kampachi-sake-editor', Content::DESCRIPTION_EDITOR],
            (string) filemtime($assets . 'sake-admin.js'),
            true
        );
    }

    public static function dashboard(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You cannot edit the Sake landing page.', 'kampachi-cms'));
        }

        $page = \Kampachi_Landings::page(self::PAGE_SLUG);
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
        ]);

        echo '<div class="wrap kc-catering-admin kc-sake-admin kc-home-admin kc-shared-admin">';
        echo '<h1>Sake Breweries &amp; Brands</h1>';
        echo '<p>Edit the landing page hero and introduction, then reorder your brewery / brand posts. Published breweries appear three per row on desktop. Discover opens each brewery’s bottle collection.</p>';
        self::render_notice();
        echo '<div class="kc-catering-admin-actions">';

        if ($page instanceof WP_Post) {
            echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url(get_permalink($page)) . '">View Website Page ↗</a>';
        }

        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . self::POST_TYPE)) . '">Manage Breweries / Brands</a>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::POST_TYPE)) . '">+ Add Brewery / Brand</a>';
        echo '</div>';

        if ($page instanceof WP_Post && current_user_can('edit_post', $page->ID)) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="kampachi_save_sake_landing">';
            echo '<input type="hidden" name="section" value="seo">';
            wp_nonce_field('kampachi_save_sake_landing', 'kampachi_sake_landing_nonce');
            Seo::renderLandingPanel($page, 'Sake Landing Page SEO');
            echo '</form>';
            self::render_intro($page);
        } else {
            echo '<div class="notice notice-warning inline"><p>The Sake landing could not be loaded. Make sure you have permission to edit it.</p></div>';
        }

        self::render_order($posts);
        echo '</div>';
    }

    private static function render_notice(): void
    {
        $saved = isset($_GET['sake_saved']) && is_string($_GET['sake_saved'])
            ? sanitize_key(wp_unslash($_GET['sake_saved']))
            : '';

        $messages = [
            'intro' => 'Section 1 saved successfully.',
            'order' => 'Sake post order saved.',
            'seo' => 'Sake landing page SEO saved.',
        ];
        if (isset($messages[$saved])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html($messages[$saved]);
            echo '</p></div>';
        } elseif ($saved === 'error') {
            echo '<div class="notice notice-error"><p>Some Sake posts could not be reordered. Please try again.</p></div>';
        }
    }

    private static function render_intro(WP_Post $page): void
    {
        echo '<section id="kc-sake-intro" class="kc-catering-section">';
        echo '<div class="kc-catering-section-head"><span>01 / HERO &amp; INTRODUCTION</span>';
        echo '<h2>Section 1 — Sake Hero &amp; Introduction</h2>';
        echo '<p>Add unlimited images and videos in any order. The first item plays first.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kampachi_save_sake_landing">';
        wp_nonce_field('kampachi_save_sake_landing', 'kampachi_sake_landing_nonce');
        self::render_hero_picker($page);

        foreach ([
            'intro_subtitle' => 'Section subheading',
            'intro_title' => 'Section title',
            'intro_description' => 'Introduction',
        ] as $key => $label) {
            $value = get_post_meta($page->ID, '_kampachi_' . $key, true);
            $value = is_string($value) ? $value : '';
            echo '<div class="kc-catering-field"><label for="kc_sake_' . esc_attr($key) . '">' . esc_html($label) . '</label>';

            if ($key === 'intro_description') {
                echo '<textarea id="kc_sake_intro_description" name="kampachi_sake_landing[intro_description]" '
                    . 'class="large-text" rows="5" data-kc-sake-rich>' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input type="text" id="kc_sake_' . esc_attr($key) . '" name="kampachi_sake_landing[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text">';
            }

            echo '</div>';
        }

        submit_button('Save Section 1');
        echo '</form></section>';
    }

    private static function render_hero_picker(WP_Post $page): void
    {
        $ids = self::hero_ids($page->ID);
        echo '<div class="kc-catering-hero-editor" data-kc-hero-editor data-kc-preview-video>';
        echo '<h3>Hero banners &amp; videos</h3>';
        echo '<p class="description">Upload as many images and videos as needed. Images: ideally 2560 × 1440 WebP. Videos: compressed MP4. Drag to reorder.</p>';
        echo '<input type="hidden" name="kampachi_sake_hero_media" data-kc-hero-ids value="' . esc_attr(implode(',', $ids)) . '">';
        echo '<div class="kc-catering-hero-list" data-kc-hero-list aria-label="Sake hero media order">';

        foreach ($ids as $id) {
            $image = wp_attachment_is_image($id);
            $thumbnail = $image ? wp_get_attachment_image_url($id, 'medium') : false;
            $videoUrl = $image ? false : wp_get_attachment_url($id);
            $mime = get_post_mime_type($id);
            $title = get_the_title($id);
            echo '<div class="kc-catering-hero-item" data-kc-hero-item data-id="' . esc_attr((string) $id) . '">';
            echo '<span class="dashicons dashicons-move kc-catering-hero-handle" aria-hidden="true"></span>';

            if (is_string($videoUrl) && $videoUrl !== '') {
                echo '<video class="kc-catering-hero-preview" src="' . esc_url($videoUrl)
                    . '" controls muted playsinline preload="metadata" aria-label="'
                    . esc_attr('Preview ' . $title) . '"></video>';
            } elseif (is_string($thumbnail) && $thumbnail !== '') {
                echo '<img class="kc-catering-hero-preview" src="' . esc_url($thumbnail) . '" alt="">';
            } else {
                echo '<span class="kc-catering-hero-preview kc-catering-video-placeholder"><span class="dashicons dashicons-format-video" aria-hidden="true"></span></span>';
            }

            echo '<div class="kc-catering-hero-info"><strong>' . esc_html($title) . '</strong>';
            echo '<small>' . esc_html($image ? 'Image banner' : 'Video: ' . (string) $mime) . '</small></div>';
            echo '<button type="button" class="button-link-delete" data-kc-hero-remove>Remove</button></div>';
        }

        echo '</div><div class="kc-catering-hero-actions">';
        echo '<button type="button" class="button" data-kc-hero-add>+ Add Images / Videos</button>';
        echo '<button type="button" class="button" data-kc-hero-clear>Clear All</button></div>';
        echo '<p class="description">Images rotate automatically; muted videos play before moving to the next item.</p>';
        HeroMotion::editor($page->ID, 'kampachi_sake_hero_media');
        echo '</div>';
    }

    /** @param array<int, WP_Post> $posts */
    private static function render_order(array $posts): void
    {
        echo '<section id="kc-sake-posts" class="kc-catering-section kc-catering-order">';
        echo '<div class="kc-catering-section-head"><span>02 / BREWERIES &amp; BRANDS</span><h2>Section 2 — Brewery Cards</h2>';
        echo '<p>Each post represents one brewery / brand, with its own bottles. Drag to reorder the landing page cards; drafts and pending entries stay hidden from visitors.</p></div>';

        if ($posts === []) {
            echo '<p>No breweries yet. Add your first brewery / brand to populate this section.</p>';
        } elseif (current_user_can('edit_others_posts')) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="kampachi_order_sake">';
            wp_nonce_field('kampachi_order_sake', 'kampachi_sake_order_nonce');
            echo '<ul class="kc-catering-sort" data-kc-event-sort>';

            foreach ($posts as $post) {
                if (! $post instanceof WP_Post) {
                    continue;
                }

                echo '<li data-event-id="' . esc_attr((string) $post->ID) . '">';
                echo '<span class="dashicons dashicons-move" aria-hidden="true"></span>';
                echo '<strong>' . esc_html($post->post_title) . '</strong> <small>' . esc_html(ucfirst($post->post_status)) . '</small>';
                echo '<a href="' . esc_url(get_edit_post_link($post->ID, 'raw')) . '">Edit</a>';
                echo '<input type="hidden" name="sake_ids[]" value="' . esc_attr((string) $post->ID) . '"></li>';
            }

            echo '</ul>';
            submit_button('Save Sake Order');
            echo '</form>';
        } else {
            echo '<p>An administrator or editor can rearrange the Sake collection.</p>';
        }

        echo '</section>';
    }

    public static function render_post_box(WP_Post $post): void
    {
        wp_nonce_field('kampachi_save_sake_post', 'kampachi_sake_post_nonce');
        echo '<div class="kc-catering-metabox">';
        echo '<p>Use the WordPress post title for the brewery / brand name. Add its bottles in the Bottles section below; each bottle has its own price and outlet selection.</p>';

        foreach ([
            'subtitle' => ['Brand subheading', '_kampachi_subtitle'],
            'description' => ['Introduction', '_kampachi_sake_description'],
            'enquiry_label' => ['Enquiry button text (brand page)', '_kampachi_sake_enquiry_label'],
            'enquiry_url' => ['Enquiry link (optional; defaults to Contact Us)', '_kampachi_sake_enquiry_url'],
        ] as $key => [$label, $metaKey]) {
            $value = get_post_meta($post->ID, $metaKey, true);
            $value = is_string($value) || is_numeric($value) ? (string) $value : '';
            echo '<div class="kc-sake-meta-field"><label for="kc_sake_post_' . esc_attr($key)
                . '"><strong>' . esc_html($label) . '</strong></label>';

            if ($key === 'description') {
                echo '<textarea class="large-text" rows="5" id="kc_sake_post_description" '
                    . 'name="kampachi_sake_post[description]" data-kc-sake-rich>' . esc_textarea($value) . '</textarea>';
            } else {
                $inputType = $key === 'enquiry_url' ? 'url' : 'text';
                $placeholder = $key === 'enquiry_label' ? 'Enquire Now' : '';
                echo '<input class="widefat" type="' . esc_attr($inputType) . '" id="kc_sake_post_' . esc_attr($key) . '" name="kampachi_sake_post[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
            }

            echo '</div>';
        }

        $photos = self::valid_image_ids(get_post_meta($post->ID, '_kampachi_sake_image_ids', true));
        echo '<h3>Brewery / brand image gallery</h3>';
        echo '<p class="description">Used for the brewery card and brand page. Drag thumbnails to arrange them. If empty, the featured image is used. Add individual bottle images in the Bottles section.</p>';
        self::render_image_picker($photos);
        HeroMotion::editor($post->ID, 'kampachi_sake_images');
        echo '</div>';
    }

    /** @param array<int, int> $ids */
    private static function render_image_picker(array $ids): void
    {
        echo '<div class="kc-catering-media" data-kc-gallery>';
        echo '<input type="hidden" name="kampachi_sake_images" value="' . esc_attr(implode(',', $ids)) . '">';
        echo '<input type="hidden" name="kampachi_sake_images_changed" value="0" data-kc-media-dirty>';
        echo '<div class="kc-catering-thumbs" data-kc-preview>';

        foreach ($ids as $id) {
            $src = wp_get_attachment_image_url($id, 'thumbnail');
            if (! is_string($src)) {
                continue;
            }

            echo '<div class="kc-catering-thumb" data-id="' . esc_attr((string) $id) . '">';
            echo '<img src="' . esc_url($src) . '" alt="">';
            echo '<button type="button" class="button-link-delete" data-kc-remove-media aria-label="Remove image">×</button>';
            echo '</div>';
        }

        echo '</div><p><button type="button" class="button" data-kc-media-pick>+ Add Photos</button> ';
        echo '<button type="button" class="button" data-kc-clear-media>Clear All</button></p></div>';
    }

    public static function save_landing(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }

        check_admin_referer('kampachi_save_sake_landing', 'kampachi_sake_landing_nonce');
        $page = \Kampachi_Landings::page(self::PAGE_SLUG);

        if (! $page instanceof WP_Post || ! current_user_can('edit_post', $page->ID)) {
            wp_die('The Sake landing is missing or not editable.', '', ['response' => 403]);
        }

        if (($_POST['section'] ?? '') === 'seo') {
            Seo::saveLandingPanel($page);
            wp_safe_redirect(admin_url('edit.php?post_type=kc_sake&page=kampachi-landing-sake&sake_saved=seo#kc-section-seo'));
            exit;
        }

        $fields = isset($_POST['kampachi_sake_landing']) && is_array($_POST['kampachi_sake_landing'])
            ? wp_unslash($_POST['kampachi_sake_landing'])
            : [];

        foreach (['intro_subtitle', 'intro_title', 'intro_description'] as $key) {
            $value = $fields[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            update_post_meta(
                $page->ID,
                '_kampachi_' . $key,
                wp_slash($key === 'intro_description' ? wp_kses_post($value) : sanitize_text_field($value))
            );
        }

        $raw = $_POST['kampachi_sake_hero_media'] ?? null;
        if (is_string($raw)) {
            update_post_meta(
                $page->ID,
                self::HERO_META_KEY,
                self::valid_hero_ids(explode(',', sanitize_text_field(wp_unslash($raw))))
            );
        }

        HeroMotion::saveDashboard($page->ID, 'kampachi_sake_hero_media');

        wp_safe_redirect(admin_url('edit.php?post_type=kc_sake&page=kampachi-landing-sake&sake_saved=intro#kc-sake-intro'));
        exit;
    }

    public static function save_post(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== self::POST_TYPE || ! self::can_save_post($postId)) {
            return;
        }

        $raw = isset($_POST['kampachi_sake_post']) && is_array($_POST['kampachi_sake_post'])
            ? wp_unslash($_POST['kampachi_sake_post'])
            : [];

        foreach (['subtitle', 'description', 'enquiry_label', 'enquiry_url'] as $key) {
            $value = $raw[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $metaKey = $key === 'subtitle' ? '_kampachi_subtitle' : '_kampachi_sake_' . $key;
            $value = $key === 'enquiry_url'
                ? esc_url_raw($value)
                : ($key === 'description' ? wp_kses_post($value) : sanitize_text_field($value));
            update_post_meta($postId, $metaKey, wp_slash($value));
        }

        if (isset($_POST['kampachi_sake_images_changed']) && $_POST['kampachi_sake_images_changed'] === '1') {
            $images = $_POST['kampachi_sake_images'] ?? '';
            if (is_string($images)) {
                update_post_meta(
                    $postId,
                    '_kampachi_sake_image_ids',
                    self::valid_image_ids(explode(',', sanitize_text_field(wp_unslash($images))))
                );
            }
        }
    }

    private static function can_save_post(int $postId): bool
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId)) {
            return false;
        }

        if (! current_user_can('edit_post', $postId)) {
            return false;
        }

        $nonce = $_POST['kampachi_sake_post_nonce'] ?? null;
        return is_string($nonce)
            && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'kampachi_save_sake_post') !== false;
    }

    public static function save_order(): void
    {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }

        check_admin_referer('kampachi_order_sake', 'kampachi_sake_order_nonce');
        $raw = isset($_POST['sake_ids']) && is_array($_POST['sake_ids'])
            ? wp_unslash($_POST['sake_ids'])
            : [];
        $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
        $expected = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        $expected = array_map('absint', $expected);
        $validSet = count($ids) === count($expected)
            && array_diff($ids, $expected) === []
            && array_diff($expected, $ids) === [];
        $failed = ! $validSet;

        if ($validSet) {
            foreach ($ids as $index => $id) {
                if (! current_user_can('edit_post', $id)) {
                    $failed = true;
                    break;
                }
            }
        }

        if (! $failed) {
            foreach ($ids as $index => $id) {
                $result = wp_update_post(['ID' => $id, 'menu_order' => $index], true);
                if (is_wp_error($result) || $result === 0) {
                    $failed = true;
                }
            }
        }

        wp_safe_redirect(admin_url('edit.php?post_type=kc_sake&page=kampachi-landing-sake&sake_saved=' . ($failed ? 'error' : 'order') . '#kc-sake-posts'));
        exit;
    }

    /** @return array<int, int> */
    public static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    /** @return array<int, int> */
    private static function valid_hero_ids(array $ids): array
    {
        $valid = [];

        foreach ($ids as $value) {
            $id = absint($value);
            $mime = $id > 0 ? get_post_mime_type($id) : null;
            if ($id > 0 && get_post_type($id) === 'attachment'
                && (wp_attachment_is_image($id) || (is_string($mime) && str_starts_with($mime, 'video/')))) {
                $valid[$id] = $id;
            }
        }

        return array_values($valid);
    }

    /** @return array<int, int> */
    public static function valid_image_ids(mixed $ids): array
    {
        $validated = [];
        if (! is_array($ids)) {
            return [];
        }

        foreach ($ids as $value) {
            $id = absint($value);
            if ($id > 0 && get_post_type($id) === 'attachment' && wp_attachment_is_image($id)) {
                $validated[$id] = $id;
            }
        }

        return array_values($validated);
    }

    /** @return array<int, int> */
    public static function hero_ids(int $pageId): array
    {
        if (metadata_exists('post', $pageId, self::HERO_META_KEY)) {
            $stored = get_post_meta($pageId, self::HERO_META_KEY, true);
            return self::valid_hero_ids(is_array($stored) ? $stored : []);
        }

        $legacy = [];
        $videoId = absint(get_post_meta($pageId, '_kampachi_hero_video_id', true));
        if ($videoId > 0) {
            $legacy[] = $videoId;
        }

        $imageIds = get_post_meta($pageId, '_kampachi_hero_image_ids', true);
        if (is_array($imageIds)) {
            $legacy = array_merge($legacy, $imageIds);
        }

        return self::valid_hero_ids($legacy);
    }

    /**
     * Accept legacy 'RM 1,200.00' values while saving future prices as decimals.
     */
    private static function parse_price(mixed $value): ?float
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/^(?:RM|MYR)\s*/i', '', $value) ?? '';
        $value = str_replace(',', '', $value);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            return null;
        }

        $price = (float) $value;
        return is_finite($price) ? $price : null;
    }

    /** @return array{original: float, discounted: float, percent: float}|null */
    public static function pricing(int $postId): ?array
    {
        $original = self::parse_price(get_post_meta($postId, '_kampachi_price', true));
        if ($original === null) {
            return null;
        }
        $rawDiscount = get_post_meta($postId, '_kampachi_sake_discount', true);
        $discount = is_scalar($rawDiscount) && is_numeric($rawDiscount) ? (float) $rawDiscount : 0.0;
        $discount = max(0.0, min(100.0, $discount));
        $discounted = round($original * (1 - $discount / 100), 2);

        return [
            'original' => $original,
            'discounted' => $discounted,
            'percent' => $discount,
        ];
    }

    /** @return array<int, array{url: string, alt: string, id?: int}> */
    public static function gallery(int $postId, string $size = 'large'): array
    {
        $photos = [];
        $ids = self::valid_image_ids(get_post_meta($postId, '_kampachi_sake_image_ids', true));

        foreach ($ids as $id) {
            $url = wp_get_attachment_image_url($id, $size);
            if (is_string($url) && $url !== '') {
                $photos[] = [
                    'id' => $id,
                    'url' => $url,
                    'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
                ];
            }
        }

        if ($photos === [] && function_exists('kampachi_native_post_image')) {
            $url = kampachi_native_post_image($postId, $size);
            if ($url !== '') {
                $photos[] = ['url' => $url, 'alt' => get_the_title($postId)];
            }
        }

        return $photos;
    }

    public static function field(int $postId, string $field): string
    {
        $value = get_post_meta($postId, '_kampachi_sake_' . $field, true);
        return is_string($value) ? $value : '';
    }
}
