<?php
/**
 * Editable 47in27 landing-page sections.
 *
 * @package KampachiCMS
 */

declare(strict_types=1);

use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

final class Kampachi_47in27
{
    private const OPTION = 'kampachi_47in27_sections';

    private const RICH_FIELDS = [
        'intro_subtitle', 'intro_title', 'intro_description',
        'campaign_subtitle', 'campaign_title', 'campaign_description',
        'pin_subtitle', 'pin_title', 'pin_description',
        'collection_subtitle', 'collection_title', 'collection_description',
    ];

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 1000);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_kampachi_47in27_save', [self::class, 'save']);
    }

    public static function defaults(): array
    {
        return [
            'hero_video_id' => 0,
            'hero_slides' => [],
            'campaign_image_id' => 0,
            'campaign_post_id' => 0,
            'campaign_outlets' => [],
            'intro_subtitle' => '',
            'intro_title' => '',
            'intro_description' => '',
            'campaign_subtitle' => '',
            'campaign_title' => '',
            'campaign_description' => '',
            'campaign_start' => '',
            'campaign_end' => '',
            'pin_subtitle' => '',
            'pin_title' => '',
            'pin_description' => '',
            'cta_1_title' => '',
            'cta_1_url' => '',
            'cta_2_title' => '',
            'cta_2_url' => '',
            'collection_subtitle' => '',
            'collection_title' => '',
            'collection_description' => '',
        ];
    }

    public static function options(): array
    {
        $stored = get_option(self::OPTION, []);

        return array_replace(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'kampachi-47in27-module',
            '47in27 Landing Page',
            'Landing Page',
            'edit_pages',
            'kampachi-47in27-editor',
            [self::class, 'editor']
        );
    }

    public static function assets(string $hook): void
    {
        if ($hook !== '47in27_page_kampachi-47in27-editor') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_editor();
        wp_enqueue_style('kampachi-47in27-admin', plugins_url('../assets/47in27-admin.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('kampachi-47in27-admin', plugins_url('../assets/47in27-admin.js', __FILE__), ['jquery'], '1.0.0', true);
    }

    private static function field(string $name, string $label, string $value, string $type = 'text'): void
    {
        echo '<div class="kc47-field"><label for="kc47-' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input class="regular-text" id="kc47-' . esc_attr($name) . '" name="campaign[' . esc_attr($name) . ']" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '"></div>';
    }

    private static function rich(string $name, string $label, string $value): void
    {
        echo '<div class="kc47-field"><label for="kc47-' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label>';
        wp_editor($value, 'kc47-' . $name, [
            'textarea_name' => 'campaign[' . $name . ']',
            'textarea_rows' => $name === 'pin_description' ? 9 : 4,
            'media_buttons' => false,
            'teeny' => true,
            'quicktags' => true,
        ]);
        echo '</div>';
    }

    private static function media(string $name, string $label, int $id, string $type = 'image'): void
    {
        $url = $id > 0 ? wp_get_attachment_url($id) : false;
        echo '<div class="kc47-field kc47-media" data-kind="' . esc_attr($type) . '">';
        echo '<label><strong>' . esc_html($label) . '</strong></label>';
        echo '<input type="hidden" class="kc47-media-id" name="campaign[' . esc_attr($name) . ']" value="' . esc_attr((string) $id) . '">';
        echo '<div class="kc47-media-preview">';
        if ($url && $type === 'image') {
            echo wp_get_attachment_image($id, 'medium');
        } elseif ($url) {
            echo '<span>' . esc_html(wp_basename($url)) . '</span>';
        }
        echo '</div><button type="button" class="button kc47-pick">Choose ' . esc_html($type) . '</button> ';
        echo '<button type="button" class="button kc47-clear">Remove</button></div>';
    }

    private static function slide(int $index, array $data): void
    {
        $id = absint($data['image_id'] ?? 0);
        $url = $id > 0 ? wp_get_attachment_url($id) : false;
        echo '<div class="kc47-slide kc47-media" data-kind="image"><div class="kc47-slide-top"><strong>Hero image</strong><button class="button-link-delete kc47-delete-slide" type="button">Remove</button></div>';
        echo '<input type="hidden" class="kc47-media-id" name="campaign[hero_slides][' . $index . '][image_id]" value="' . esc_attr((string) $id) . '">';
        echo '<div class="kc47-media-preview">';
        if ($url) {
            echo wp_get_attachment_image($id, 'medium');
        }
        echo '</div><button type="button" class="button kc47-pick">Choose image</button> ';
        echo '<label>Movement <select name="campaign[hero_slides][' . $index . '][direction]">';
        foreach (['zoom-in' => 'Zoom in', 'zoom-out' => 'Zoom out', 'left' => 'Pan left', 'right' => 'Pan right', 'up' => 'Pan up', 'down' => 'Pan down', 'none' => 'None'] as $direction => $label) {
            echo '<option value="' . esc_attr($direction) . '" ' . selected($data['direction'] ?? 'zoom-in', $direction, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Overlay title<input type="text" name="campaign[hero_slides][' . $index . '][title]" value="' . esc_attr((string) ($data['title'] ?? '')) . '"></label>';
        echo '<label>Overlay text<textarea rows="2" name="campaign[hero_slides][' . $index . '][text]">' . esc_textarea((string) ($data['text'] ?? '')) . '</textarea></label></div>';
    }

    public static function editor(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You cannot edit the 47in27 page.', 'kampachi-cms'), '', ['response' => 403]);
        }

        $config = self::options();
        $outlets = Outlets::publishedLocations();
        $prefectures = get_posts(['post_type' => 'kc_prefecture', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $selectedOutlets = Outlets::locationIds($config['campaign_outlets']);

        echo '<div class="wrap kc47-admin kc-home-admin kc-shared-admin"><h1>47in27 Landing Page</h1><p>Manage the hero, introduction, live prefecture campaign and published prefecture collection.</p>';
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>47in27 landing page saved.</p></div>';
        }
        echo '<nav class="kc47-jump"><a href="#kc47-hero">01 Hero</a><a href="#kc47-intro">02 Introduction</a><a href="#kc47-campaign">03 Current campaign</a><a href="#kc47-collection">04 Prefecture posts</a></nav>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="kampachi_47in27_save">';
        wp_nonce_field('kampachi_47in27_save', 'kampachi_47in27_nonce');
        echo '<section class="kc47-panel" id="kc47-hero"><h2>01 · Hero Banner</h2><p>Use an MP4 video or unlimited 16:9 image slides. Images take precedence when selected; remove all image slides to show the video. Recommended image size: 2560 × 1440 WebP.</p>';
        self::media('hero_video_id', 'Hero video (optional)', absint($config['hero_video_id']), 'video');
        echo '<div id="kc47-slides" data-next-index="' . esc_attr((string) count((array) $config['hero_slides'])) . '">';
        foreach ((array) $config['hero_slides'] as $i => $slide) {
            if (is_array($slide)) {
                self::slide((int) $i, $slide);
            }
        }
        echo '</div><button type="button" class="button" id="kc47-add-slide">+ Add hero image</button></section>';
        echo '<section class="kc47-panel" id="kc47-intro"><h2>02 · Introduction</h2>';
        foreach (['intro_subtitle' => 'Subtitle', 'intro_title' => 'Title', 'intro_description' => 'Description'] as $key => $label) {
            self::rich($key, $label, (string) $config[$key]);
        }
        echo '</section>';
        echo '<section class="kc47-panel" id="kc47-campaign"><h2>03 · Currently Running Prefecture</h2><p>Select a published prefecture or choose automatic selection based on the dates set inside its Prefecture post. The fields below provide landing-page presentation and can override the selected post.</p>';
        echo '<div class="kc47-field"><label for="kc47-campaign-post"><strong>Prefecture post</strong></label><select id="kc47-campaign-post" name="campaign[campaign_post_id]"><option value="0">Auto-select a published prefecture active today (fallback to landing-page dates)</option>';
        foreach ($prefectures as $post) {
            echo '<option value="' . esc_attr((string) $post->ID) . '" ' . selected(absint($config['campaign_post_id']), $post->ID, false) . '>' . esc_html($post->post_title) . '</option>';
        }
        echo '</select></div>';
        self::media('campaign_image_id', 'Campaign image (left column)', absint($config['campaign_image_id']));
        foreach (['campaign_subtitle' => 'Campaign subtitle', 'campaign_title' => 'Campaign title', 'campaign_description' => 'Campaign description'] as $key => $label) {
            self::rich($key, $label, (string) $config[$key]);
        }
        self::field('campaign_start', 'Start date', (string) $config['campaign_start'], 'date');
        self::field('campaign_end', 'End date', (string) $config['campaign_end'], 'date');
        echo '<div class="kc47-field"><strong>Locations</strong><div class="kc47-outlets">';
        foreach ($outlets as $post) {
            echo '<label><input type="checkbox" name="campaign[campaign_outlets][]" value="' . esc_attr((string) $post->ID) . '" ' . checked(in_array($post->ID, $selectedOutlets, true), true, false) . '> ' . esc_html(Outlets::locationName($post)) . '</label>';
        }
        if ($outlets === []) {
            echo '<p>No published outlets. Create outlets using the Outlets menu first.</p>';
        }
        echo '</div></div><h3>Right column — Pin Details</h3>';
        foreach (['pin_subtitle' => 'Pin subtitle', 'pin_title' => 'Pin title', 'pin_description' => 'Pin details / terms (rich text)'] as $key => $label) {
            self::rich($key, $label, (string) $config[$key]);
        }
        echo '<h3>Two call-to-action buttons</h3><p>Paste a PDF URL from Media Library or a reservation/booking URL. Leave a title or URL blank to hide its button.</p>';
        foreach ([1, 2] as $number) {
            self::field('cta_' . $number . '_title', 'Button ' . $number . ' text', (string) $config['cta_' . $number . '_title']);
            self::field('cta_' . $number . '_url', 'Button ' . $number . ' PDF / booking URL', (string) $config['cta_' . $number . '_url'], 'url');
            echo '<button type="button" class="button kc47-pick-pdf" data-target="kc47-cta_' . esc_attr((string) $number) . '_url">Choose PDF from Media Library</button>';
        }
        echo '</section>';
        echo '<section class="kc47-panel" id="kc47-collection"><h2>04 · Prefecture Posts</h2><p>This section automatically displays all published 47in27 → Prefectures posts with featured image, title and excerpt/description.</p>';
        foreach (['collection_subtitle' => 'Section subtitle', 'collection_title' => 'Section title', 'collection_description' => 'Section description'] as $key => $label) {
            self::rich($key, $label, (string) $config[$key]);
        }
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=kc_prefecture')) . '">Manage prefecture posts</a></section>';
        submit_button('Save 47in27 Landing Page');
        echo '</form><template id="kc47-slide-template">';
        self::slide(999999, []);
        echo '</template></div>';
    }

    private static function date(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $value ? $value : '';
    }

    public static function save(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('Permission denied.', 'kampachi-cms'), '', ['response' => 403]);
        }

        check_admin_referer('kampachi_47in27_save', 'kampachi_47in27_nonce');
        $raw = isset($_POST['campaign']) && is_array($_POST['campaign']) ? wp_unslash($_POST['campaign']) : [];
        $clean = [];

        foreach (self::RICH_FIELDS as $name) {
            $clean[$name] = isset($raw[$name]) && is_string($raw[$name]) ? wp_kses_post($raw[$name]) : '';
        }
        foreach (['cta_1_title', 'cta_2_title'] as $name) {
            $clean[$name] = isset($raw[$name]) && is_string($raw[$name]) ? sanitize_text_field($raw[$name]) : '';
        }
        foreach (['cta_1_url', 'cta_2_url'] as $name) {
            $clean[$name] = isset($raw[$name]) && is_string($raw[$name]) ? esc_url_raw($raw[$name], ['http', 'https', 'mailto', 'tel']) : '';
        }
        foreach (['campaign_start', 'campaign_end'] as $name) {
            $clean[$name] = self::date($raw[$name] ?? '');
        }
        if ($clean['campaign_start'] !== '' && $clean['campaign_end'] !== '' && $clean['campaign_start'] > $clean['campaign_end']) {
            wp_die(esc_html__('The campaign end date must not be earlier than the start date.', 'kampachi-cms'), '', ['response' => 400]);
        }

        $imageId = absint($raw['campaign_image_id'] ?? 0);
        $clean['campaign_image_id'] = wp_attachment_is_image($imageId) ? $imageId : 0;
        $videoId = absint($raw['hero_video_id'] ?? 0);
        $clean['hero_video_id'] = str_starts_with((string) get_post_mime_type($videoId), 'video/') ? $videoId : 0;
        $postId = absint($raw['campaign_post_id'] ?? 0);
        $clean['campaign_post_id'] = get_post_type($postId) === 'kc_prefecture' && get_post_status($postId) === 'publish' ? $postId : 0;
        $clean['campaign_outlets'] = Outlets::locationIds($raw['campaign_outlets'] ?? []);
        $clean['hero_slides'] = [];
        $directions = ['none', 'zoom-in', 'zoom-out', 'left', 'right', 'up', 'down'];
        if (isset($raw['hero_slides']) && is_array($raw['hero_slides'])) {
            foreach ($raw['hero_slides'] as $slide) {
                if (! is_array($slide)) {
                    continue;
                }
                $id = absint($slide['image_id'] ?? 0);
                if (! wp_attachment_is_image($id)) {
                    continue;
                }
                $direction = isset($slide['direction']) && is_string($slide['direction']) ? $slide['direction'] : 'zoom-in';
                $clean['hero_slides'][] = [
                    'image_id' => $id,
                    'direction' => in_array($direction, $directions, true) ? $direction : 'zoom-in',
                    'title' => isset($slide['title']) && is_string($slide['title']) ? sanitize_text_field($slide['title']) : '',
                    'text' => isset($slide['text']) && is_string($slide['text']) ? sanitize_textarea_field($slide['text']) : '',
                ];
            }
        }

        update_option(self::OPTION, $clean, false);
        wp_safe_redirect(admin_url('admin.php?page=kampachi-47in27-editor&updated=1'));
        exit;
    }

    public static function current_post(array $settings): ?WP_Post
    {
        $id = absint($settings['campaign_post_id'] ?? 0);
        if ($id > 0) {
            $post = get_post($id);

            return $post instanceof WP_Post && $post->post_status === 'publish' && $post->post_type === 'kc_prefecture' ? $post : null;
        }

        $today = current_time('Y-m-d');
        $candidates = get_posts([
            'post_type' => 'kc_prefecture',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($candidates as $post) {
            $start = self::date(get_post_meta($post->ID, '_kampachi_date_start', true));
            $end = self::date(get_post_meta($post->ID, '_kampachi_date_end', true));
            if ($start !== '' && $end !== '' && $start <= $today && $today <= $end) {
                return $post;
            }
        }

        return null;
    }
}
