<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Kampachi_Homepage
{
    private const OPTION = 'kampachi_homepage_sections';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 999);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_kampachi_homepage_save', [self::class, 'save']);
        add_action('wp_enqueue_scripts', [self::class, 'frontend_assets']);
    }

    public static function register_menu(): void
    {
        remove_menu_page('kampachi-homepage-module');
        add_menu_page('Kampachi Homepage', 'Homepage', 'edit_pages', 'kampachi-homepage-module', [self::class, 'editor'], 'dashicons-admin-home', 3);
        // Old homepage settings submenu, if registered by the existing plugin.
        remove_submenu_page('kampachi-homepage-module', 'kampachi-homepage');
    }

    public static function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_kampachi-homepage-module') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_editor();
        wp_enqueue_style('kampachi-home-admin', plugins_url('../assets/admin.css', __FILE__), [], '2.8.3');
        wp_enqueue_script('kampachi-home-admin', plugins_url('../assets/admin.js', __FILE__), ['jquery', 'wp-util'], '1.0.0', true);
    }

    public static function frontend_assets(): void
    {
        if (! is_front_page()) {
            return;
        }

        wp_enqueue_style('kampachi-home-frontend', plugins_url('../assets/home.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('kampachi-home-frontend', plugins_url('../assets/home.js', __FILE__), [], '1.0.0', true);
    }

    private static function options(): array
    {
        $options = get_option(self::OPTION, []);

        return is_array($options) ? $options : [];
    }

    private static function text_editor(string $key, string $label, string $value, int $rows = 3): void
    {
        echo '<div class="kc-field"><label class="kc-label" for="kc_' . esc_attr($key) . '">' . esc_html($label) . '</label>';
        wp_editor($value, 'kc_' . $key, [
            'textarea_name' => 'home[' . $key . ']',
            'textarea_rows' => $rows,
            'media_buttons' => false,
            'teeny' => true,
            'quicktags' => true,
        ]);
        echo '</div>';
    }

    private static function media_field(string $name, int $id, string $label, string $note): void
    {
        $image = $id > 0 ? wp_get_attachment_image_url($id, 'medium') : false;
        echo '<div class="kc-field kc-media"><label class="kc-label">' . esc_html($label) . '</label>';
        echo '<div class="kc-image-preview">';
        if ($image) {
            echo '<img src="' . esc_url($image) . '" alt="">';
        }
        echo '</div><input class="kc-media-id" type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $id) . '">';
        echo '<button type="button" class="button kc-pick-media">Choose image from Media Library</button> ';
        echo '<button type="button" class="button kc-clear-media">Remove</button>';
        echo '<p class="description">' . esc_html($note) . '</p></div>';
    }

    private static function rich_repeat_field(string $name, string $label, string $value): void
    {
        echo '<div class="kc-field"><label class="kc-label">' . esc_html($label) . '</label>';
        echo '<textarea class="kc-rich" name="' . esc_attr($name) . '" rows="3">' . esc_textarea($value) . '</textarea></div>';
    }

    public static function editor(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to edit this homepage.', 'kampachi-homepage'));
        }

        $settings = self::options();
        $offers = get_posts(['post_type' => 'kc_offer', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $sakes = get_posts(['post_type' => 'kc_sake', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $outlets = get_posts(['post_type' => 'kc_outlet', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $heroSlides = is_array($settings['hero_slides'] ?? null) ? $settings['hero_slides'] : [];
        $dishes = is_array($settings['dishes'] ?? null) ? $settings['dishes'] : [];
        $selectedOffers = array_map('intval', (array) ($settings['offer_ids'] ?? []));
        $selectedSakes = array_map('intval', (array) ($settings['sake_ids'] ?? []));
        echo '<div class="wrap kc-home-admin">';
        echo '<header class="kc-page-header"><div><p class="kc-eyebrow">KAMPACHI / CONTENT MANAGEMENT</p><h1>Homepage editor</h1></div><a class="button kc-view-site" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener noreferrer">View website ↗</a></header>';
        echo '<nav class="kc-section-nav" aria-label="Homepage sections"><a href="#kc-section-layout"><span>00</span> Layout</a><a href="#kc-section-hero"><span>01</span> Hero</a><a href="#kc-section-introduction"><span>02</span> Introduction</a><a href="#kc-section-promotions"><span>03</span> Offers &amp; Events</a><a href="#kc-section-dishes"><span>04</span> Signature dishes</a><a href="#kc-section-sake"><span>05</span> Artisanal Sake</a><a href="#kc-section-outlets"><span>06</span> Restaurants</a></nav>';
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Homepage saved.</p></div>';
        }
        echo '<form id="kc-home-form" action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
        echo '<input type="hidden" name="action" value="kampachi_homepage_save">';
        wp_nonce_field('kampachi_homepage_save', 'kampachi_home_nonce');

        echo '<section id="kc-section-layout" class="kc-panel"><h2><span class="kc-section-number">00</span> Homepage layout <small>Visibility and order</small></h2>';
        echo '<p>Enable or disable each section and choose the order below the banner. The banner stays at the top when enabled. All published outlets always appear in the final section.</p>';
        echo '<div class="kc-layout-grid">';
        $visibility = is_array($settings['section_visibility'] ?? null) ? $settings['section_visibility'] : [];
        $order = is_array($settings['section_order'] ?? null) ? $settings['section_order'] : [];
        foreach (['hero' => 'Homepage Banner', 'intro' => 'Introduction', 'offers' => 'Offers & Events', 'dishes' => 'Signature Dishes', 'sake' => 'Artisanal Sake'] as $section => $label) {
            $enabled = ! array_key_exists($section, $visibility) || (bool) $visibility[$section];
            echo '<div class="kc-layout-item"><label><input type="checkbox" name="home[section_visibility][' . esc_attr($section) . ']" value="1" ' . checked($enabled, true, false) . '> ' . esc_html($label) . '</label>';
            if ($section !== 'hero') {
                $position = isset($order[$section]) ? absint($order[$section]) : (int) array_search($section, ['intro', 'offers', 'dishes', 'sake', 'outlets'], true) + 1;
                echo '<label>Order <select name="home[section_order][' . esc_attr($section) . ']">';
                for ($index = 1; $index <= 4; $index++) {
                    echo '<option value="' . $index . '" ' . selected($position, $index, false) . '>' . $index . '</option>';
                }
                echo '</select></label>';
            } else {
                echo '<span class="description">Fixed top position</span>';
            }
            echo '</div>';
        }
        echo '</div></section>';

        echo '<section id="kc-section-hero" class="kc-panel"><h2><span class="kc-section-number">01</span> Hero <small>Video or image slideshow</small></h2>';
        $videoId = absint($settings['hero_video_id'] ?? 0);
        $videoUrl = $videoId ? wp_get_attachment_url($videoId) : '';
        echo '<div class="kc-field"><label class="kc-label">Hero video <span class="kc-muted">2560 × 1440 · MP4</span></label><input class="kc-media-id kc-video-id" type="hidden" name="home[hero_video_id]" value="' . esc_attr((string) $videoId) . '">';
        echo '<span class="kc-video-name">' . esc_html($videoUrl ? wp_basename($videoUrl) : 'No video selected') . '</span> ';
        echo '<button type="button" class="button kc-pick-video">Choose video</button> <button type="button" class="button kc-clear-video">Remove</button></div>';
        echo '<p class="description">Uploaded hero images will appear instead of the video when both are selected. Remove the images to show the video.</p>';
        echo '<h3>Hero Images <small>(unlimited)</small></h3><div id="kc-hero-list" class="kc-repeat-list" data-type="hero">';
        foreach ($heroSlides as $i => $slide) {
            self::slide_row((int) $i, (array) $slide);
        }
        echo '</div><button type="button" class="button kc-add" data-target="hero">+ Add hero image</button></section>';

        echo '<section id="kc-section-introduction" class="kc-panel"><h2><span class="kc-section-number">02</span> Introduction</h2>';
        self::text_editor('intro_title', 'Title', (string) ($settings['intro_title'] ?? ''));
        self::text_editor('intro_description', 'Description', (string) ($settings['intro_description'] ?? ''), 6);
        echo '</section>';

        echo '<section id="kc-section-promotions" class="kc-panel"><h2><span class="kc-section-number">03</span> Offers &amp; Events <small>From Offers &amp; Events · maximum 3 cards</small></h2>';
        self::text_editor('offers_title', 'Title', (string) ($settings['offers_title'] ?? ''));
        self::text_editor('offers_description', 'Description', (string) ($settings['offers_description'] ?? ''), 5);
        echo '<div class="kc-choices kc-offer-choices">';
        foreach ($offers as $post) {
            echo '<label><input type="checkbox" name="home[offer_ids][]" value="' . esc_attr((string) $post->ID) . '" ' . checked(in_array($post->ID, $selectedOffers, true), true, false) . '> ' . esc_html($post->post_title) . '</label>';
        }
        if ($offers === []) {
            echo '<p>No published promotions yet. Add them in Promotions.</p>';
        }
        echo '</div><p class="description kc-offer-count">Selected: ' . count($selectedOffers) . ' / 3</p>';
        self::limit_field('offers', $settings, 3, 3);
        echo '</section>';

        echo '<section id="kc-section-dishes" class="kc-panel"><h2><span class="kc-section-number">04</span> Signature dishes <small>Unlimited items</small></h2>';
        self::text_editor('dishes_title', 'Title', (string) ($settings['dishes_title'] ?? ''));
        self::text_editor('dishes_description', 'Description', (string) ($settings['dishes_description'] ?? ''), 5);
        echo '<div id="kc-dish-list" class="kc-repeat-list" data-type="dish">';
        foreach ($dishes as $i => $dish) {
            self::dish_row((int) $i, (array) $dish);
        }
        echo '</div><button type="button" class="button kc-add" data-target="dish">+ Add signature dish</button>';
        self::limit_field('dishes', $settings, 0, 100);
        echo '</section>';

        echo '<section id="kc-section-sake" class="kc-panel"><h2><span class="kc-section-number">05</span> Artisanal Sake <small>From Sake Collection</small></h2>';
        self::text_editor('sake_title', 'Title', (string) ($settings['sake_title'] ?? ''));
        self::text_editor('sake_description', 'Description', (string) ($settings['sake_description'] ?? ''), 5);
        echo '<div class="kc-choices">';
        foreach ($sakes as $post) {
            echo '<label><input type="checkbox" name="home[sake_ids][]" value="' . esc_attr((string) $post->ID) . '" ' . checked(in_array($post->ID, $selectedSakes, true), true, false) . '> ' . esc_html($post->post_title) . '</label>';
        }
        if ($sakes === []) {
            echo '<p>No published sake yet. Add items in Sake Collection.</p>';
        }
        echo '</div>';
        self::limit_field('sake', $settings, 6, 24);
        echo '</section>';

        echo '<section id="kc-section-outlets" class="kc-panel"><h2><span class="kc-section-number">06</span> Restaurants <small>From Outlets</small></h2>';
        self::text_editor('outlets_title', 'Title', (string) ($settings['outlets_title'] ?? ''));
        self::text_editor('outlets_description', 'Description', (string) ($settings['outlets_description'] ?? ''), 5);
        echo '<p>Every published Outlet post appears automatically at the bottom of the homepage, in the outlet order. Unpublished outlets are excluded.</p>';
        echo '</section>';
        echo '<div class="kc-save-bar"><span><strong>Homepage settings</strong></span><button type="submit" class="button button-primary button-large">Save homepage changes</button></div>';
        echo '</form>';
        self::templates();
        echo '</div>';
    }

    private static function slide_row(int $index, array $data): void
    {
        echo '<div class="kc-repeat kc-slide"><div class="kc-row-head"><strong><span class="dashicons dashicons-format-image"></span> Hero image</strong><button type="button" class="button-link-delete kc-remove">Remove</button></div>';
        self::media_field('home[hero_slides][' . $index . '][image_id]', absint($data['image_id'] ?? 0), 'Hero image', 'Required: 2560 × 1440 (16:9), preferably WebP.');
        echo '<label class="kc-label">Ken Burns direction</label><select name="home[hero_slides][' . $index . '][direction]">';
        foreach (['none' => 'No animation', 'zoom-in' => 'Zoom in', 'zoom-out' => 'Zoom out', 'left' => 'Pan left', 'right' => 'Pan right', 'up' => 'Pan up', 'down' => 'Pan down'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($data['direction'] ?? 'zoom-in', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        self::rich_repeat_field('home[hero_slides][' . $index . '][title]', 'Optional overlay title (rich text)', (string) ($data['title'] ?? ''));
        echo '</div>';
    }

    private static function dish_row(int $index, array $data): void
    {
        echo '<div class="kc-repeat kc-dish"><div class="kc-row-head"><strong><span class="dashicons dashicons-food"></span> Signature dish</strong><button type="button" class="button-link-delete kc-remove">Remove</button></div>';
        self::media_field('home[dishes][' . $index . '][image_id]', absint($data['image_id'] ?? 0), 'Dish image', 'Required: 1:1 square (recommended 1200 × 1200), preferably WebP.');
        self::rich_repeat_field('home[dishes][' . $index . '][title]', 'Dish title (rich text)', (string) ($data['title'] ?? ''));
        self::rich_repeat_field('home[dishes][' . $index . '][description]', 'Dish description (rich text)', (string) ($data['description'] ?? ''));
        echo '</div>';
    }

    private static function limit_field(string $section, array $settings, int $default, int $maximum): void
    {
        $limit = isset($settings[$section . '_limit']) ? absint($settings[$section . '_limit']) : $default;
        echo '<div class="kc-field kc-limit-field"><label class="kc-label" for="kc_' . esc_attr($section) . '_limit">Items to display</label>';
        echo '<select id="kc_' . esc_attr($section) . '_limit" name="home[' . esc_attr($section) . '_limit]">';
        $minimum = $section === 'dishes' ? 0 : 1;
        for ($number = $minimum; $number <= $maximum; $number++) {
            echo '<option value="' . $number . '" ' . selected($limit, $number, false) . '>' . ($number === 0 ? 'All' : $number) . '</option>';
        }
        echo '</select></div>';
    }

    private static function templates(): void
    {
        echo '<script type="text/html" id="tmpl-kc-hero">';
        self::slide_row(987654, []);
        echo '</script><script type="text/html" id="tmpl-kc-dish">';
        self::dish_row(987654, []);
        echo '</script>';
    }

    private static function sanitize_rich(mixed $value): string
    {
        return is_string($value) ? wp_kses_post($value) : '';
    }

    private static function selected_posts(mixed $input, string $postType, int $limit = -1): array
    {
        $ids = array_values(array_unique(array_filter(array_map('absint', is_array($input) ? $input : []))));
        $valid = [];

        foreach ($ids as $id) {
            if (get_post_type($id) !== $postType || get_post_status($id) !== 'publish') {
                continue;
            }
            $valid[] = $id;
            if ($limit > 0 && count($valid) >= $limit) {
                break;
            }
        }

        return $valid;
    }

    public static function save(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die('Permission denied.', '', ['response' => 403]);
        }
        check_admin_referer('kampachi_homepage_save', 'kampachi_home_nonce');
        $raw = isset($_POST['home']) && is_array($_POST['home']) ? wp_unslash($_POST['home']) : [];
        $clean = [];
        foreach (['intro', 'offers', 'dishes', 'sake', 'outlets'] as $section) {
            foreach (['subtitle', 'title', 'description'] as $field) {
                $key = $section . '_' . $field;
                $clean[$key] = self::sanitize_rich($raw[$key] ?? '');
            }
        }
        $video = absint($raw['hero_video_id'] ?? 0);
        $clean['hero_video_id'] = $video && str_starts_with((string) get_post_mime_type($video), 'video/') ? $video : 0;
        $clean['hero_slides'] = [];
        $validDirections = ['none', 'zoom-in', 'zoom-out', 'left', 'right', 'up', 'down'];
        foreach ((array) ($raw['hero_slides'] ?? []) as $slide) {
            if (! is_array($slide)) {
                continue;
            }
            $id = absint($slide['image_id'] ?? 0);
            if (! $id || ! wp_attachment_is_image($id)) {
                continue;
            }
            $direction = is_string($slide['direction'] ?? null) ? $slide['direction'] : 'zoom-in';
            $clean['hero_slides'][] = [
                'image_id' => $id,
                'direction' => in_array($direction, $validDirections, true) ? $direction : 'zoom-in',
                'title' => self::sanitize_rich($slide['title'] ?? ''),
                'text' => self::sanitize_rich($slide['text'] ?? ''),
            ];
        }
        $clean['dishes'] = [];
        foreach ((array) ($raw['dishes'] ?? []) as $dish) {
            if (! is_array($dish)) {
                continue;
            }
            $id = absint($dish['image_id'] ?? 0);
            if (! $id || ! wp_attachment_is_image($id)) {
                continue;
            }
            $clean['dishes'][] = [
                'image_id' => $id,
                'title' => self::sanitize_rich($dish['title'] ?? ''),
                'description' => self::sanitize_rich($dish['description'] ?? ''),
            ];
        }
        $sections = ['hero', 'intro', 'offers', 'dishes', 'sake', 'outlets'];
        $rawVisibility = is_array($raw['section_visibility'] ?? null) ? $raw['section_visibility'] : [];
        $rawOrder = is_array($raw['section_order'] ?? null) ? $raw['section_order'] : [];
        $clean['section_visibility'] = [];
        $clean['section_order'] = [];
        foreach ($sections as $index => $section) {
            $clean['section_visibility'][$section] = isset($rawVisibility[$section]) && $rawVisibility[$section] === '1';
            if ($section !== 'hero') {
                $clean['section_order'][$section] = max(1, min(5, absint($rawOrder[$section] ?? $index)));
            }
        }
        foreach (['offers' => [3, 3], 'dishes' => [0, 100], 'sake' => [6, 24], 'outlets' => [6, 24]] as $section => [$default, $maximum]) {
            $rawLimit = $raw[$section . '_limit'] ?? $default;
            $clean[$section . '_limit'] = max($section === 'dishes' ? 0 : 1, min($maximum, absint($rawLimit)));
        }
        $clean['offer_ids'] = self::selected_posts($raw['offer_ids'] ?? [], 'kc_offer', 3);
        $clean['sake_ids'] = self::selected_posts($raw['sake_ids'] ?? [], 'kc_sake');
        $clean['outlet_ids'] = self::selected_posts($raw['outlet_ids'] ?? [], 'kc_outlet');
        update_option(self::OPTION, $clean, false);
        wp_safe_redirect(admin_url('admin.php?page=kampachi-homepage-module&updated=1'));
        exit;
    }

    private static function content(string $html): string
    {
        return wp_kses_post($html);
    }

    private static function heading(array $config, string $prefix): void
    {
        echo '<div class="kch-section-heading">';
        foreach (['title' => 'div', 'description' => 'div'] as $key => $tag) {
            $value = (string) ($config[$prefix . '_' . $key] ?? '');
            if ($value === '') {
                continue;
            }
            echo '<' . $tag . ' class="kch-section-' . esc_attr($key) . '">' . self::content($value) . '</' . $tag . '>';
        }
        echo '</div>';
    }

    public static function render(): bool
    {
        $config = self::options();
        if ($config === []) {
            return false;
        }

        $video = absint($config['hero_video_id'] ?? 0);
        $videoUrl = $video ? wp_get_attachment_url($video) : false;
        $slides = (array) ($config['hero_slides'] ?? []);
        echo '<main class="kch-home">';
        echo '<section class="kch-hero" aria-label="Kampachi hero">';
        if ($videoUrl) {
            echo '<video class="kch-hero-video" autoplay muted loop playsinline preload="metadata"><source src="' . esc_url($videoUrl) . '" type="' . esc_attr((string) get_post_mime_type($video)) . '"></video>';
        } else {
            foreach ($slides as $index => $slide) {
                $image = wp_get_attachment_image_url(absint($slide['image_id'] ?? 0), 'full');
                if (! $image) {
                    continue;
                }
                $direction = sanitize_html_class((string) ($slide['direction'] ?? 'zoom-in'));
                echo '<div class="kch-slide ' . ($index === 0 ? 'is-active' : '') . '"><div class="kch-slide-photo kch-' . esc_attr($direction) . '" style="background-image:url(' . esc_url($image) . ')"></div><div class="kch-slide-overlay">';
                echo '<div class="kch-slide-title">' . self::content((string) ($slide['title'] ?? '')) . '</div>';
                echo '</div></div>';
            }
        }
        echo '</section>';
        echo '<section class="kch-section kch-intro">';
        self::heading($config, 'intro');
        echo '</section>';
        echo '<section class="kch-section kch-promotions">';
        self::heading($config, 'offers');
        echo '<div class="kch-grid">';
        foreach (array_slice((array) ($config['offer_ids'] ?? []), 0, 3) as $id) {
            self::post_card(absint($id), 'offer');
        }
        echo '</div></section>';
        echo '<section class="kch-section kch-dishes">';
        self::heading($config, 'dishes');
        echo '<div class="kch-grid">';
        foreach ((array) ($config['dishes'] ?? []) as $dish) {
            $id = absint($dish['image_id'] ?? 0);
            if (! wp_attachment_is_image($id)) {
                continue;
            }
            echo '<article class="kch-card kch-dish-card">' . wp_get_attachment_image($id, 'large', false, ['loading' => 'lazy']) . '<div class="kch-card-body"><div class="kch-card-title">' . self::content((string) ($dish['title'] ?? '')) . '</div><div class="kch-card-description">' . self::content((string) ($dish['description'] ?? '')) . '</div></div></article>';
        }
        echo '</div></section>';
        echo '<section class="kch-section kch-sake">';
        self::heading($config, 'sake');
        echo '<div class="kch-grid">';
        foreach ((array) ($config['sake_ids'] ?? []) as $id) {
            self::post_card(absint($id), 'sake');
        }
        echo '</div></section>';
        echo '<section class="kch-section kch-outlets">';
        self::heading($config, 'outlets');
        echo '<div class="kch-grid">';
        foreach (get_posts(['post_type' => 'kc_outlet', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]) as $post) {
            if (! $post instanceof WP_Post || $post->post_type !== 'kc_outlet' || $post->post_status !== 'publish') {
                continue;
            }
            $phone = (string) get_post_meta($post->ID, '_kampachi_phone', true);
            $email = (string) get_post_meta($post->ID, '_kampachi_email', true);
            $location = (string) get_post_meta($post->ID, '_kampachi_address', true);
            echo '<article class="kch-card kch-outlet-card"><div class="kch-card-body"><h3><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3>';
            if ($location !== '') {
                echo '<p>' . esc_html($location) . '</p>';
            }
            if ($phone !== '') {
                echo '<p><a href="tel:' . esc_attr(preg_replace('/[^+0-9]/', '', $phone)) . '">' . esc_html($phone) . '</a></p>';
            }
            if (is_email($email)) {
                echo '<p><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
            }
            echo '</div></article>';
        }
        echo '</div></section></main>';
        return true;
    }

    private static function post_card(int $postId, string $kind): void
    {
        $post = get_post($postId);
        if (! $post instanceof WP_Post || $post->post_status !== 'publish' || $post->post_type !== 'kc_' . $kind) {
            return;
        }
        $description = get_the_excerpt($post);
        $brand = $kind === 'sake' ? (string) get_post_meta($postId, '_kampachi_brand', true) : '';
        echo '<article class="kch-card kch-' . esc_attr($kind) . '-card"><a class="kch-image-link" href="' . esc_url(get_permalink($post)) . '">';
        if (has_post_thumbnail($post)) {
            echo get_the_post_thumbnail($post, 'large', ['loading' => 'lazy']);
        }
        echo '</a><div class="kch-card-body">';
        if ($brand !== '') {
            echo '<p class="kch-brand">' . esc_html($brand) . '</p>';
        }
        echo '<h3><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3>';
        echo '<p>' . esc_html($description) . '</p><a class="kch-discover" href="' . esc_url(get_permalink($post)) . '">Discover</a></div></article>';
    }
}
