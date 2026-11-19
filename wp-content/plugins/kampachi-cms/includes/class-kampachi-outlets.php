<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The outlet post is the complete page: no outlet landing-page editor is required.
 */
final class Outlets
{
    public const POST_TYPE = 'kc_outlet';

    private const HERO_KEY = '_kampachi_outlet_hero_ids';
    private const DISHES_KEY = '_kampachi_outlet_dishes';
    private const MENUS_KEY = '_kampachi_outlet_menus';

    public static function boot(): void
    {
        add_action('add_meta_boxes', [self::class, 'register_boxes']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save_post'], 20, 2);
    }

    public static function register_boxes(): void
    {
        add_meta_box(
            'kampachi_outlet_sections',
            'Outlet Page — Hero, Signature Dishes, Hours, Menus & Map',
            [self::class, 'render_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function enqueue_admin(string $hook): void
    {
        $screen = get_current_screen();
        if (! $screen || $screen->post_type !== self::POST_TYPE
            || ! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style(
            'kampachi-outlet-editor',
            plugins_url('../assets/outlet-admin.css', __FILE__),
            [],
            '2.6.0'
        );
        wp_enqueue_script(
            'kampachi-outlet-editor',
            plugins_url('../assets/outlet-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable'],
            '2.6.0',
            true
        );
    }

    public static function render_box(WP_Post $post): void
    {
        wp_nonce_field('kampachi_save_outlet', 'kampachi_outlet_nonce');
        echo '<div class="kc-outlet-editor">';
        echo '<p>Each published outlet has its own website page. Use the WordPress post title as the outlet name. Drag items to set their website order.</p>';

        self::heading('01 / HERO & INTRODUCTION', 'Section 1 — Hero & Introduction');
        echo '<p class="description">Upload unlimited images/videos (suggested banner size: 2560 × 1440). Videos should be compressed MP4.</p>';
        echo '<input type="hidden" name="kampachi_outlet_hero_ids" value="' . esc_attr(implode(',', self::hero_ids($post->ID))) . '">';
        echo '<div class="kc-outlet-media-list" data-ko-hero-list>';
        foreach (self::hero_ids($post->ID) as $id) {
            self::render_hero_row($id);
        }
        echo '</div><p><button type="button" class="button" data-ko-add-hero>+ Add Hero Images / Videos</button></p>';
        HeroMotion::editor($post->ID, 'kampachi_outlet_hero_ids');
        self::input('subtitle', 'Subtitle', self::meta($post->ID, 'subtitle'));
        self::textarea('intro', 'Introduction', self::meta($post->ID, 'outlet_intro'));

        self::heading('02 / SIGNATURE DISHES', 'Section 2 — Signature Dishes');
        echo '<p class="description">Add unlimited dish pictures, each with its own description; optionally give each dish a title. Items without both an image and description will not be published.</p>';
        echo '<div data-ko-dishes-list class="kc-outlet-repeat-list">';
        foreach (self::dishes($post->ID) as $index => $dish) {
            self::render_dish_row($dish, $index);
        }
        echo '</div><p><button type="button" class="button" data-ko-add-dish>+ Add Signature Dish</button></p>';

        self::heading('03 / VISIT US', 'Section 3 — Visit Us');
        self::textarea('hours', 'Hours of operation', self::meta($post->ID, 'hours'));
        self::textarea('hours_description', 'Hours description / notes', self::meta($post->ID, 'outlet_hours_description'));
        self::input('phone', 'Contact number', self::meta($post->ID, 'phone'), 'tel');
        self::textarea('address', 'Outlet address', self::meta($post->ID, 'address'));
        self::input('booking_url', 'Reservation link (for header dropdown)', self::meta($post->ID, 'booking_url'), 'url');

        echo '<h4>Menus — shown inside Visit Us</h4>';
        echo '<p class="description">Add as many menus as required. Each menu needs a title and a PDF/file upload or a URL.</p>';
        echo '<div data-ko-menus-list class="kc-outlet-repeat-list">';
        foreach (self::menus($post->ID) as $index => $menu) {
            self::render_menu_row($menu, $index);
        }
        echo '</div><p><button type="button" class="button" data-ko-add-menu>+ Add Menu</button></p>';

        self::heading('04 / LOCATION MAP', 'Section 4 — Google Map');
        self::input('map_url', 'Google Maps URL or Maps embed URL', self::meta($post->ID, 'map_url'), 'url');
        echo '<p class="description">For an interactive embedded map, use the URL from Google Maps → Share → Embed a map (the URL inside the iframe src attribute). Otherwise the address is used as a map search.</p>';
        echo '</div>';
    }

    private static function heading(string $eyebrow, string $title): void
    {
        echo '<div class="kc-outlet-section-heading"><span>' . esc_html($eyebrow) . '</span><h3>' . esc_html($title) . '</h3></div>';
    }

    private static function input(string $key, string $label, string $value, string $type = 'text'): void
    {
        echo '<p class="kc-outlet-field"><label for="ko-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input class="widefat" id="ko-' . esc_attr($key) . '" name="kampachi_outlet[' . esc_attr($key) . ']" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '"></p>';
    }

    private static function textarea(string $key, string $label, string $value): void
    {
        echo '<p class="kc-outlet-field"><label for="ko-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<textarea rows="4" class="widefat" id="ko-' . esc_attr($key) . '" name="kampachi_outlet[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea></p>';
    }

    private static function render_hero_row(int $id): void
    {
        $isImage = wp_attachment_is_image($id);
        $thumb = $isImage ? wp_get_attachment_image_url($id, 'thumbnail') : false;
        echo '<div class="kc-outlet-media-row" data-ko-hero-item data-id="' . esc_attr((string) $id) . '">';
        echo '<span class="dashicons dashicons-move" aria-hidden="true"></span>';
        if (is_string($thumb)) {
            echo '<img src="' . esc_url($thumb) . '" alt="">';
        } else {
            echo '<span class="dashicons dashicons-format-video kc-outlet-video-icon" aria-hidden="true"></span>';
        }
        echo '<strong>' . esc_html(get_the_title($id)) . '</strong><small>' . esc_html($isImage ? 'Image' : 'Video') . '</small>';
        echo '<button type="button" class="button-link-delete" data-ko-remove>Remove</button></div>';
    }

    /** @param array{image_id: int, title: string, description: string} $dish */
    private static function render_dish_row(array $dish, int $index): void
    {
        $url = $dish['image_id'] > 0 ? wp_get_attachment_image_url($dish['image_id'], 'thumbnail') : false;
        echo '<div class="kc-outlet-repeat-item" data-ko-dish-item><span class="dashicons dashicons-move" aria-hidden="true"></span>';
        echo '<div class="kc-outlet-repeat-body"><div class="kc-outlet-picture">';
        echo '<img data-ko-dish-preview alt="" src="' . esc_url(is_string($url) ? $url : '') . '"' . ($url ? '' : ' hidden') . '>';
        echo '<input type="hidden" data-ko-dish-image name="kampachi_outlet[dishes][' . esc_attr((string) $index) . '][image_id]" value="' . esc_attr((string) $dish['image_id']) . '">';
        echo '<button type="button" class="button" data-ko-pick-dish>Choose Photo</button></div>';
        echo '<label>Dish title (optional)<input class="widefat" data-ko-dish-title name="kampachi_outlet[dishes][' . esc_attr((string) $index) . '][title]" value="' . esc_attr($dish['title']) . '"></label>';
        echo '<label>Photo description<textarea class="widefat" rows="3" data-ko-dish-description name="kampachi_outlet[dishes][' . esc_attr((string) $index) . '][description]">' . esc_textarea($dish['description']) . '</textarea></label></div>';
        echo '<button type="button" class="button-link-delete" data-ko-remove>Remove</button></div>';
    }

    /** @param array{title: string, attachment_id: int, url: string} $menu */
    private static function render_menu_row(array $menu, int $index): void
    {
        echo '<div class="kc-outlet-repeat-item" data-ko-menu-item><span class="dashicons dashicons-move" aria-hidden="true"></span>';
        echo '<div class="kc-outlet-repeat-body">';
        echo '<label>Menu title<input class="widefat" data-ko-menu-title name="kampachi_outlet[menus][' . esc_attr((string) $index) . '][title]" value="' . esc_attr($menu['title']) . '"></label>';
        echo '<label>Menu PDF / file URL (or external menu URL)<input type="url" class="widefat" data-ko-menu-url name="kampachi_outlet[menus][' . esc_attr((string) $index) . '][url]" value="' . esc_attr($menu['url']) . '"></label>';
        echo '<input type="hidden" data-ko-menu-attachment name="kampachi_outlet[menus][' . esc_attr((string) $index) . '][attachment_id]" value="' . esc_attr((string) $menu['attachment_id']) . '">';
        echo '<button type="button" class="button" data-ko-pick-menu>Choose PDF / File</button></div>';
        echo '<button type="button" class="button-link-delete" data-ko-remove>Remove</button></div>';
    }

    public static function save_post(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== self::POST_TYPE || ! self::can_save($postId)) {
            return;
        }

        $raw = isset($_POST['kampachi_outlet']) && is_array($_POST['kampachi_outlet'])
            ? wp_unslash($_POST['kampachi_outlet'])
            : [];

        $simple = [
            'subtitle' => '_kampachi_subtitle',
            'intro' => '_kampachi_outlet_intro',
            'hours' => '_kampachi_hours',
            'hours_description' => '_kampachi_outlet_hours_description',
            'phone' => '_kampachi_phone',
            'address' => '_kampachi_address',
            'map_url' => '_kampachi_map_url',
            'booking_url' => '_kampachi_booking_url',
        ];

        foreach ($simple as $key => $metaKey) {
            $value = $raw[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $value = match ($key) {
                'map_url', 'booking_url' => esc_url_raw($value, ['http', 'https']),
                'intro', 'hours', 'hours_description', 'address' => sanitize_textarea_field($value),
                default => sanitize_text_field($value),
            };
            update_post_meta($postId, $metaKey, $value);
        }

        $heroIds = $_POST['kampachi_outlet_hero_ids'] ?? null;
        if (is_string($heroIds)) {
            update_post_meta($postId, self::HERO_KEY, self::valid_media_ids(explode(',', wp_unslash($heroIds)), true));
        }

        update_post_meta($postId, self::DISHES_KEY, self::sanitize_dishes($raw['dishes'] ?? []));
        update_post_meta($postId, self::MENUS_KEY, self::sanitize_menus($raw['menus'] ?? []));
    }

    private static function can_save(int $postId): bool
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId)
            || ! current_user_can('edit_post', $postId)) {
            return false;
        }

        $nonce = $_POST['kampachi_outlet_nonce'] ?? null;
        return is_string($nonce)
            && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'kampachi_save_outlet') !== false;
    }

    /** @return array<int, int> */
    public static function valid_media_ids(mixed $items, bool $videos): array
    {
        if (! is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $rawId) {
            $id = absint($rawId);
            $mime = $id > 0 ? get_post_mime_type($id) : null;
            if ($id <= 0 || get_post_type($id) !== 'attachment') {
                continue;
            }
            if (wp_attachment_is_image($id) || ($videos && is_string($mime) && str_starts_with($mime, 'video/'))) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }

    /** @return array<int, int> */
    public static function hero_ids(int $postId): array
    {
        $raw = get_post_meta($postId, self::HERO_KEY, true);
        if (metadata_exists('post', $postId, self::HERO_KEY)) {
            return self::valid_media_ids($raw, true);
        }

        $id = get_post_thumbnail_id($postId);
        return $id ? self::valid_media_ids([$id], true) : [];
    }

    /** @return array<int, array{image_id: int, title: string, description: string}> */
    private static function sanitize_dishes(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = absint($row['image_id'] ?? 0);
            $title = is_string($row['title'] ?? null) ? sanitize_text_field($row['title']) : '';
            $description = is_string($row['description'] ?? null) ? sanitize_textarea_field($row['description']) : '';
            if (self::valid_media_ids([$id], false) === [] || $description === '') {
                continue;
            }
            $result[] = ['image_id' => $id, 'title' => $title, 'description' => $description];
        }
        return $result;
    }

    /** @return array<int, array{image_id: int, title: string, description: string}> */
    public static function dishes(int $postId): array
    {
        return self::sanitize_dishes(get_post_meta($postId, self::DISHES_KEY, true));
    }

    /** @return array<int, array{title: string, attachment_id: int, url: string}> */
    private static function sanitize_menus(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = is_string($row['title'] ?? null) ? sanitize_text_field($row['title']) : '';
            $id = absint($row['attachment_id'] ?? 0);
            $id = $id > 0 && get_post_type($id) === 'attachment' ? $id : 0;
            $url = $id > 0 ? wp_get_attachment_url($id) : null;
            if (! is_string($url) || $url === '') {
                $url = is_string($row['url'] ?? null) ? esc_url_raw($row['url'], ['http', 'https']) : '';
            }
            if ($title === '' || $url === '') {
                continue;
            }
            $result[] = ['title' => $title, 'attachment_id' => $id, 'url' => $url];
        }
        return $result;
    }

    /** @return array<int, array{title: string, attachment_id: int, url: string}> */
    public static function menus(int $postId): array
    {
        if (metadata_exists('post', $postId, self::MENUS_KEY)) {
            return self::sanitize_menus(get_post_meta($postId, self::MENUS_KEY, true));
        }
        $legacy = self::meta($postId, 'menu_url');
        return $legacy !== '' ? [['title' => Content::label('menus'), 'attachment_id' => 0, 'url' => $legacy]] : [];
    }

    public static function meta(int $postId, string $key): string
    {
        $value = get_post_meta($postId, '_kampachi_' . $key, true);
        return is_scalar($value) ? (string) $value : '';
    }

    public static function map_embed_url(int $postId): string
    {
        $url = self::meta($postId, 'map_url');
        if ($url !== '') {
            $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            $path = (string) wp_parse_url($url, PHP_URL_PATH);
            if (in_array($host, ['www.google.com', 'google.com', 'maps.google.com'], true)
                && str_starts_with($path, '/maps/embed')) {
                return $url;
            }
        }
        $address = self::meta($postId, 'address');
        return $address !== ''
            ? 'https://www.google.com/maps?q=' . rawurlencode($address) . '&output=embed'
            : '';
    }
}
