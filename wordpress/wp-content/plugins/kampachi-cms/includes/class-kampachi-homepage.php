<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Kampachi_Homepage
{
    private const OPTION = 'kampachi_homepage_sections';

    private const HERO_MODES = [
        'video' => 'Video only',
        'images' => 'Image slideshow with Ken Burns',
        'mixed' => 'Video and image slideshow',
    ];

    private const TEXT_TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    private const TEXT_SIZES = [12, 14, 16, 18, 20, 22, 24, 28, 32, 36, 40, 48, 56, 64];
    private const TEXT_WEIGHTS = ['400' => 'Regular', '700' => 'Bold'];

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
        wp_enqueue_style(
            'kampachi-home-admin',
            plugins_url('../assets/admin.css', __FILE__),
            [],
            (string) filemtime(dirname(__DIR__) . '/assets/admin.css')
        );
        wp_enqueue_script(
            'kampachi-home-admin',
            plugins_url('../assets/admin.js', __FILE__),
            ['jquery'],
            (string) filemtime(dirname(__DIR__) . '/assets/admin.js'),
            true
        );
    }

    public static function frontend_assets(): void
    {
        $pageId = get_queried_object_id();
        $sourceId = absint(get_post_meta($pageId, '_kc_translation_of', true));

        if (! is_front_page()
            && get_post_field('post_name', $pageId) !== 'home'
            && ($sourceId === 0 || get_post_field('post_name', $sourceId) !== 'home')
        ) {
            return;
        }

        wp_enqueue_style(
            'kampachi-home-frontend',
            plugins_url('../assets/home.css', __FILE__),
            [],
            (string) filemtime(dirname(__DIR__) . '/assets/home.css')
        );
        wp_enqueue_script(
            'kampachi-home-frontend',
            plugins_url('../assets/home.js', __FILE__),
            [],
            (string) filemtime(dirname(__DIR__) . '/assets/home.js'),
            true
        );
    }

    private static function options(): array
    {
        $options = get_option(self::OPTION, []);

        return is_array($options) ? $options : [];
    }

    public static function text_format(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $tag = is_string($input['tag'] ?? null) ? $input['tag'] : '';
        $size = filter_var($input['size'] ?? '', FILTER_VALIDATE_INT);
        $weight = is_scalar($input['weight'] ?? null) ? (string) $input['weight'] : '';

        return [
            'tag' => in_array($tag, self::TEXT_TAGS, true) ? $tag : '',
            'size' => in_array($size, self::TEXT_SIZES, true) ? $size : 0,
            'weight' => isset(self::TEXT_WEIGHTS[$weight]) ? $weight : '',
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @return array{0: string, 1: string}
     */
    public static function text_presentation(mixed $format, string $defaultTag, array $attributes = []): array
    {
        $format = self::text_format($format);
        $tag = ($format['tag'] ?? '') ?: $defaultTag;
        $styles = [];
        if (! empty($format['size'])) {
            $attributes['data-kc-home-size'] = '';
            $styles[] = '--kc-home-font-size:' . $format['size'] . 'px';
        }
        if (! empty($format['weight'])) {
            $attributes['data-kc-home-weight'] = '';
            $styles[] = '--kc-home-font-weight:' . $format['weight'];
        }
        if ($styles !== []) {
            $attributes['style'] = implode(';', $styles);
        }

        $markup = '';
        foreach ($attributes as $name => $value) {
            $markup .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
        }

        return [$tag, $markup];
    }

    public static function plain_text(string $value): string
    {
        $value = preg_replace('~<br\s*/?>\s*|</(?:p|div|li|h[1-6])>\s*~i', "\n", $value) ?? $value;

        return trim(html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Keep previously saved markup when the editor has not changed the text.
     * New text is stored as escaped text with line breaks.
     */
    private static function save_text(mixed $input, mixed $previous): string
    {
        $input = is_string($input) ? trim(str_replace(["\r\n", "\r"], "\n", $input)) : '';
        $previous = is_string($previous) ? $previous : '';

        if ($input === self::plain_text($previous)) {
            return wp_kses_post($previous);
        }

        return nl2br(esc_html(sanitize_textarea_field($input)), false);
    }

    private static function text_editor(
        string $name,
        string $formatName,
        string $label,
        string $value,
        mixed $format,
        int $rows = 3
    ): void {
        $format = self::text_format($format);
        $id = 'kc_' . sanitize_html_class(str_replace(['[', ']'], ['_', ''], $name));
        $tags = ['p' => 'Paragraph'];
        foreach (array_slice(self::TEXT_TAGS, 1) as $tag) {
            $tags[$tag] = 'Heading ' . substr($tag, 1);
        }

        echo '<div class="kc-field kc-home-text"><label class="kc-label" for="' . esc_attr($id) . '">'
            . esc_html($label) . '</label>';
        echo '<textarea class="widefat" id="' . esc_attr($id) . '" name="' . esc_attr($name)
            . '" rows="' . esc_attr((string) $rows) . '">' . esc_textarea(self::plain_text($value)) . '</textarea>';
        echo '<div class="kc-home-text-options">';
        self::format_select($formatName . '[tag]', 'Text type', $tags, $format['tag']);
        self::format_select(
            $formatName . '[size]',
            'Font size',
            array_combine(self::TEXT_SIZES, array_map(static fn (int $size): string => $size . ' px', self::TEXT_SIZES)),
            (string) ($format['size'] ?: '')
        );
        self::format_select($formatName . '[weight]', 'Weight', self::TEXT_WEIGHTS, $format['weight']);
        echo '</div></div>';
    }

    private static function format_select(string $name, string $label, array $options, string $selected): void
    {
        echo '<label>' . esc_html($label) . '<select name="' . esc_attr($name) . '">'
            . '<option value="">Default</option>';
        foreach ($options as $value => $caption) {
            echo '<option value="' . esc_attr((string) $value) . '"'
                . selected($selected, (string) $value, false) . '>' . esc_html($caption) . '</option>';
        }
        echo '</select></label>';
    }

    private static function section_value(array $settings, string $key): string
    {
        $fallback = match ($key) {
            'intro_title' => 'hero_title',
            'intro_description' => 'hero_description',
            'offers_title' => 'offers_title',
            default => '',
        };
        $value = $settings[$key] ?? '';
        if (! array_key_exists($key, $settings) && $fallback !== '' && function_exists('kampachi_setting')) {
            $value = kampachi_setting($fallback);
        }

        return is_string($value) ? $value : '';
    }

    private static function section_text(array $settings, string $key, string $label, int $rows = 3): void
    {
        self::text_editor(
            'home[' . $key . ']',
            'home[format][' . $key . ']',
            $label,
            self::section_value($settings, $key),
            $settings['format'][$key] ?? [],
            $rows
        );
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

    private static function repeat_text(string $prefix, array $data, string $key, string $label): void
    {
        self::text_editor(
            $prefix . '[' . $key . ']',
            $prefix . '[format][' . $key . ']',
            $label,
            (string) ($data[$key] ?? ''),
            $data['format'][$key] ?? []
        );
    }

    public static function hero_mode(array $settings): string
    {
        $mode = $settings['hero_mode'] ?? '';
        if (is_string($mode) && isset(self::HERO_MODES[$mode])) {
            return $mode;
        }

        return ! empty($settings['hero_slides']) ? 'images' : 'video';
    }

    public static function displayed_slides(array $settings, array $videos, array $images): array
    {
        return match ($settings['hero_mode'] ?? null) {
            'video' => $videos,
            'images' => $images,
            'mixed' => array_merge($videos, $images),
            default => $images !== [] ? $images : $videos,
        };
    }

    public static function hero_controls(int $count, bool $hasVideo): void
    {
        if ($count < 2 && ! $hasVideo) {
            return;
        }

        echo '<div class="kc-home-hero-controls" role="group" aria-label="Hero controls" hidden>';
        if ($hasVideo) {
            echo '<button type="button" data-kc-hero-sound aria-label="Turn sound on" hidden>';
            echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
            echo '<path d="M11 5 6 9H3v6h3l5 4V5Z"/>';
            echo '<path data-kc-sound-muted d="m16 10 4 4m0-4-4 4"/>';
            echo '<path data-kc-sound-audible d="M15 9a4.2 4.2 0 0 1 0 6m3-9a8.5 8.5 0 0 1 0 12"/>';
            echo '</svg></button>';
        }
        if ($count > 1) {
            $total = str_pad((string) $count, 2, '0', STR_PAD_LEFT);
            echo '<button type="button" data-kc-hero-prev aria-label="Previous slide">&#8592;</button>';
            echo '<span class="kc-home-hero-count" data-kc-hero-count aria-live="off">01 / ' . esc_html($total) . '</span>';
            echo '<button type="button" data-kc-hero-next aria-label="Next slide">&#8594;</button>';
        }
        echo '</div>';
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

        echo '<section id="kc-section-hero" class="kc-panel"><h2><span class="kc-section-number">01</span> Hero</h2>';
        echo '<div class="kc-field"><label class="kc-label" for="kc_hero_mode">Hero type</label>'
            . '<select id="kc_hero_mode" name="home[hero_mode]">';
        foreach (self::HERO_MODES as $mode => $label) {
            echo '<option value="' . esc_attr($mode) . '"'
                . selected(self::hero_mode($settings), $mode, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div>';
        $videoId = absint($settings['hero_video_id'] ?? 0);
        $videoUrl = $videoId ? wp_get_attachment_url($videoId) : false;
        echo '<div class="kc-field"><label class="kc-label">Hero video <span class="kc-muted">2560 × 1440 · MP4</span></label>';
        echo '<input class="kc-media-id kc-video-id" type="hidden" name="home[hero_video_id]" value="'
            . esc_attr((string) $videoId) . '">';
        echo '<span class="kc-video-name">' . esc_html($videoUrl ? wp_basename($videoUrl) : 'No video selected') . '</span> ';
        echo '<button type="button" class="button kc-pick-video">Choose video</button> '
            . '<button type="button" class="button kc-clear-video">Remove</button>';
        echo '<video class="kc-home-video-preview" controls playsinline preload="metadata" aria-label="Hero video preview"'
            . ($videoUrl ? ' src="' . esc_url($videoUrl) . '"' : ' hidden') . '></video></div>';
        echo '<p class="description">The selected hero type controls which media appear. Mixed slides start with the video.</p>';
        echo '<h3>Hero Images <small>(unlimited)</small></h3><div id="kc-hero-list" class="kc-repeat-list" data-type="hero">';
        foreach ($heroSlides as $i => $slide) {
            self::slide_row((int) $i, (array) $slide);
        }
        echo '</div><button type="button" class="button kc-add" data-target="hero">+ Add hero image</button></section>';

        echo '<section id="kc-section-introduction" class="kc-panel"><h2><span class="kc-section-number">02</span> Introduction</h2>';
        self::section_text($settings, 'intro_title', 'Title');
        self::section_text($settings, 'intro_description', 'Description', 6);
        echo '</section>';

        echo '<section id="kc-section-promotions" class="kc-panel"><h2><span class="kc-section-number">03</span> Offers &amp; Events <small>From Offers &amp; Events · maximum 3 cards</small></h2>';
        self::section_text($settings, 'offers_title', 'Title');
        self::section_text($settings, 'offers_description', 'Description', 5);
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
        self::section_text($settings, 'dishes_title', 'Title');
        self::section_text($settings, 'dishes_description', 'Description', 5);
        echo '<div id="kc-dish-list" class="kc-repeat-list" data-type="dish">';
        foreach ($dishes as $i => $dish) {
            self::dish_row((int) $i, (array) $dish);
        }
        echo '</div><button type="button" class="button kc-add" data-target="dish">+ Add signature dish</button>';
        self::limit_field('dishes', $settings, 0, 100);
        echo '</section>';

        echo '<section id="kc-section-sake" class="kc-panel"><h2><span class="kc-section-number">05</span> Artisanal Sake <small>From Sake Collection</small></h2>';
        self::section_text($settings, 'sake_title', 'Title');
        self::section_text($settings, 'sake_description', 'Description', 5);
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
        self::section_text($settings, 'outlets_title', 'Title');
        self::section_text($settings, 'outlets_description', 'Description', 5);
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
        self::repeat_text('home[hero_slides][' . $index . ']', $data, 'title', 'Overlay title (optional)');
        echo '</div>';
    }

    private static function dish_row(int $index, array $data): void
    {
        echo '<div class="kc-repeat kc-dish"><div class="kc-row-head"><strong><span class="dashicons dashicons-food"></span> Signature dish</strong><button type="button" class="button-link-delete kc-remove">Remove</button></div>';
        self::media_field('home[dishes][' . $index . '][image_id]', absint($data['image_id'] ?? 0), 'Dish image', 'Required: 1:1 square (recommended 1200 × 1200), preferably WebP.');
        $prefix = 'home[dishes][' . $index . ']';
        self::repeat_text($prefix, $data, 'title', 'Dish title');
        self::repeat_text($prefix, $data, 'description', 'Dish description');
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
        $previous = self::options();
        $clean = ['format' => []];
        foreach (['intro', 'offers', 'dishes', 'sake', 'outlets'] as $section) {
            $subtitle = $section . '_subtitle';
            $clean[$subtitle] = self::sanitize_rich($previous[$subtitle] ?? '');
            foreach (['title', 'description'] as $field) {
                $key = $section . '_' . $field;
                $existing = self::section_value($previous, $key);
                $clean[$key] = self::save_text($raw[$key] ?? self::plain_text($existing), $existing);
                $clean['format'][$key] = self::text_format($raw['format'][$key] ?? $previous['format'][$key] ?? []);
            }
        }
        $video = absint($raw['hero_video_id'] ?? 0);
        $clean['hero_mode'] = self::hero_mode($raw);
        $clean['hero_video_id'] = $video && str_starts_with((string) get_post_mime_type($video), 'video/') ? $video : 0;
        $clean['hero_slides'] = [];
        $validDirections = ['none', 'zoom-in', 'zoom-out', 'left', 'right', 'up', 'down'];
        foreach ((array) ($raw['hero_slides'] ?? []) as $index => $slide) {
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
                'title' => self::save_text($slide['title'] ?? '', $previous['hero_slides'][$index]['title'] ?? ''),
                'text' => self::sanitize_rich($previous['hero_slides'][$index]['text'] ?? ''),
                'format' => ['title' => self::text_format($slide['format']['title'] ?? [])],
            ];
        }
        $clean['dishes'] = [];
        foreach ((array) ($raw['dishes'] ?? []) as $index => $dish) {
            if (! is_array($dish)) {
                continue;
            }
            $id = absint($dish['image_id'] ?? 0);
            if (! $id || ! wp_attachment_is_image($id)) {
                continue;
            }
            $clean['dishes'][] = [
                'image_id' => $id,
                'title' => self::save_text($dish['title'] ?? '', $previous['dishes'][$index]['title'] ?? ''),
                'description' => self::save_text(
                    $dish['description'] ?? '',
                    $previous['dishes'][$index]['description'] ?? ''
                ),
                'format' => [
                    'title' => self::text_format($dish['format']['title'] ?? []),
                    'description' => self::text_format($dish['format']['description'] ?? []),
                ],
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
        foreach (['title', 'description'] as $key) {
            $value = (string) ($config[$prefix . '_' . $key] ?? '');
            if ($value === '') {
                continue;
            }
            echo self::formatted_text($value, $config['format'][$prefix . '_' . $key] ?? [], 'kch-section-' . $key);
        }
        echo '</div>';
    }

    private static function render_hero(array $slides): void
    {
        $count = count($slides);
        echo '<section class="kch-hero" aria-label="Kampachi hero" data-kc-home-hero>';
        foreach ($slides as $index => $slide) {
            echo '<div class="kch-slide' . ($index === 0 ? ' is-active' : '') . '" aria-hidden="'
                . ($index === 0 ? 'false' : 'true') . '" data-kc-hero-slide>';
            if (isset($slide['mime'])) {
                echo '<video class="kch-hero-video" muted playsinline preload="metadata"'
                    . ($count === 1 ? ' loop' : '') . ' aria-label="Kampachi dining video">';
                echo '<source src="' . esc_url($slide['url']) . '" type="' . esc_attr($slide['mime']) . '"></video>';
            } else {
                $direction = sanitize_html_class((string) ($slide['data']['direction'] ?? 'zoom-in'));
                echo '<div class="kch-slide-photo kch-' . esc_attr($direction)
                    . '" style="background-image:url(' . esc_url($slide['url']) . ')"></div>';
                echo '<div class="kch-slide-overlay">' . self::formatted_text(
                    (string) ($slide['data']['title'] ?? ''),
                    $slide['data']['format']['title'] ?? [],
                    'kch-slide-title'
                ) . '</div>';
            }
            echo '</div>';
        }
        self::hero_controls($count, isset($slides[0]['mime']));
        echo '</section>';
    }

    private static function formatted_text(string $value, mixed $format, string $class): string
    {
        [$tag, $attributes] = self::text_presentation($format, 'div', ['class' => $class]);
        $content = $tag === 'div' ? self::content($value) : nl2br(esc_html(self::plain_text($value)), false);

        return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
    }

    public static function render(): bool
    {
        $config = self::options();
        if ($config === []) {
            return false;
        }

        $videoId = absint($config['hero_video_id'] ?? 0);
        $url = $videoId > 0 ? wp_get_attachment_url($videoId) : false;
        $mime = $videoId > 0 ? (string) get_post_mime_type($videoId) : '';
        $videos = is_string($url) && $url !== '' && str_starts_with($mime, 'video/')
            ? [['url' => $url, 'mime' => $mime]]
            : [];
        $images = [];
        foreach ((array) ($config['hero_slides'] ?? []) as $slide) {
            if (! is_array($slide)) {
                continue;
            }
            $url = wp_get_attachment_image_url(absint($slide['image_id'] ?? 0), 'full');
            if (is_string($url) && $url !== '') {
                $images[] = ['url' => $url, 'data' => $slide];
            }
        }

        $slides = self::displayed_slides($config, $videos, $images);
        echo '<main class="kch-home">';
        if (($config['section_visibility']['hero'] ?? true) && $slides !== []) {
            self::render_hero($slides);
        }
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
            echo '<article class="kch-card kch-dish-card">'
                . wp_get_attachment_image($id, 'large', false, ['loading' => 'lazy']) . '<div class="kch-card-body">'
                . self::formatted_text((string) ($dish['title'] ?? ''), $dish['format']['title'] ?? [], 'kch-card-title')
                . self::formatted_text(
                    (string) ($dish['description'] ?? ''),
                    $dish['format']['description'] ?? [],
                    'kch-card-description'
                ) . '</div></article>';
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
