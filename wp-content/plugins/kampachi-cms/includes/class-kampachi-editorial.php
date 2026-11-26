<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Careers, About Us and Privacy Policy content editors.
 */
final class Kampachi_Editorial
{
    private const PAGE_SLUGS = ['about-us', 'privacy-policy'];

    /** @return array<int, array{title: string, description: string}> */
    public static function default_privacy_sections(): array
    {
        return [];
    }

    public static function boot(): void
    {
        add_action('add_meta_boxes', [self::class, 'register_boxes']);
        add_action('save_post_' . Kampachi_Landings::POST_TYPE, [self::class, 'save_page'], 20, 2);
        add_action('save_post_kc_job', [self::class, 'save_job'], 20, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_boxes(): void
    {
        add_meta_box('kc_job_application', 'Vacancy Details & Application', [self::class, 'job_box'], 'kc_job', 'normal', 'high');
        $post = get_post();
        if (! $post instanceof WP_Post || $post->post_type !== Kampachi_Landings::POST_TYPE) {
            return;
        }
        if ($post->post_name === 'about-us') {
            add_meta_box('kc_about_photos', 'About Kampachi — Photo Gallery', [self::class, 'about_box'], Kampachi_Landings::POST_TYPE, 'normal', 'high');
        }
        if ($post->post_name === 'privacy-policy') {
            add_meta_box('kc_privacy_sections', 'Privacy & Policy — Content Sections', [self::class, 'privacy_box'], Kampachi_Landings::POST_TYPE, 'normal', 'high');
        }
    }

    public static function enqueue_assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }
        $post = get_post(isset($_GET['post']) ? absint($_GET['post']) : 0);
        if ($screen->post_type !== 'kc_job'
            && (! $post instanceof WP_Post || ! in_array($post->post_name, self::PAGE_SLUGS, true))) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('kc-editorial-admin', plugins_url('../assets/editorial-admin.css', __FILE__), [], '2.7.0');
        wp_enqueue_script(
            'kc-editorial-admin',
            plugins_url('../assets/editorial-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable'],
            '2.7.0',
            true
        );
    }

    public static function job_box(WP_Post $post): void
    {
        wp_nonce_field('kc_save_job', 'kc_job_nonce');
        echo '<div class="kc-editorial-admin">';
        echo '<p>Use the post title for the vacancy name and the WordPress main content editor for the full job description. Published vacancies appear as expandable cards on the Careers page.</p>';
        self::field('salary_min', 'Salary from (MYR)', self::job_meta($post->ID, 'salary_min'), 'number', '0.01');
        self::field('salary_max', 'Salary to (MYR)', self::job_meta($post->ID, 'salary_max'), 'number', '0.01');
        self::field('apply_url', 'Apply link (optional)', self::job_meta($post->ID, 'apply_url'), 'url');
        self::field('apply_email', 'Application email (optional)', self::job_meta($post->ID, 'apply_email') ?: self::job_meta($post->ID, 'email'), 'email');
        self::field('apply_whatsapp', 'WhatsApp number or wa.me URL (optional)', self::job_meta($post->ID, 'apply_whatsapp') ?: self::job_meta($post->ID, 'whatsapp'));
        echo '<p class="description">The application form sends résumés to the application email above. If left blank, it uses the Careers email in Kampachi Settings. Configured application links are also shown below the vacancy details.</p>';
        echo '</div>';
    }

    private static function job_meta(int $id, string $key): string
    {
        $value = get_post_meta($id, '_kampachi_' . $key, true);
        return is_scalar($value) ? (string) $value : '';
    }

    private static function field(string $name, string $label, string $value, string $type = 'text', string $step = ''): void
    {
        echo '<p><label for="kc-job-' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input class="widefat" id="kc-job-' . esc_attr($name) . '" name="kc_job[' . esc_attr($name) . ']" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '"';
        if ($step !== '') {
            echo ' step="' . esc_attr($step) . '" min="0"';
        }
        echo '></p>';
    }

    public static function about_box(WP_Post $post): void
    {
        wp_nonce_field('kc_save_editorial_page', 'kc_editorial_nonce');
        $stored = get_post_meta($post->ID, '_kampachi_about_image_ids', true);
        $ids = is_array($stored) ? array_values(array_filter(array_map('absint', $stored))) : [];
        echo '<div class="kc-editorial-admin">';
        echo '<p>Set the section title, subtitle and introduction above in the Landing Introduction box. Add as many photographs as needed, and drag to arrange them.</p>';
        echo '<input type="hidden" id="kc-about-ids" name="kc_about_image_ids" value="' . esc_attr(implode(',', $ids)) . '">';
        echo '<div class="kc-editorial-gallery" data-kc-about-gallery>';
        foreach ($ids as $id) {
            if (! wp_attachment_is_image($id)) {
                continue;
            }
            $thumbnail = wp_get_attachment_image_url($id, 'thumbnail');
            echo '<div class="kc-editorial-photo" data-id="' . esc_attr((string) $id) . '">';
            echo '<img src="' . esc_url(is_string($thumbnail) ? $thumbnail : '') . '" alt="">';
            echo '<button type="button" class="button-link-delete" data-kc-remove-photo>Remove</button></div>';
        }
        echo '</div><p><button type="button" class="button" data-kc-add-photos>+ Add Photos</button> ';
        echo '<button type="button" class="button" data-kc-clear-photos>Clear All</button></p></div>';
    }

    public static function privacy_box(WP_Post $post): void
    {
        wp_nonce_field('kc_save_editorial_page', 'kc_editorial_nonce');
        $items = get_post_meta($post->ID, '_kampachi_privacy_sections', true);
        $items = is_array($items) ? $items : [];
        if (! metadata_exists('post', $post->ID, '_kampachi_privacy_sections') && trim($post->post_content) === '') {
            $items = self::default_privacy_sections();
        }
        echo '<div class="kc-editorial-admin" data-kc-privacy-editor>';
        echo '<p>Add and drag as many policy sections as needed. The introduction title and subtitle are managed in the Landing Introduction box above. Review policy text with the relevant legal/privacy team before publishing.</p>';
        echo '<div class="kc-editorial-sections" data-kc-privacy-list>';
        foreach ($items as $index => $item) {
            if (is_array($item)) {
                self::privacy_row((string) ($item['title'] ?? ''), (string) ($item['description'] ?? ''), (int) $index);
            }
        }
        echo '</div><p><button type="button" class="button" data-kc-add-section>+ Add Section</button></p>';
        echo '<template data-kc-privacy-template>';
        self::privacy_row('', '', 0);
        echo '</template></div>';
    }

    private static function privacy_row(string $title, string $description, int $index): void
    {
        echo '<div class="kc-editorial-section" data-kc-privacy-row>';
        echo '<span class="dashicons dashicons-move" aria-label="Drag to rearrange"></span>';
        echo '<label><strong>Section title</strong><input type="text" class="widefat" data-kc-section-title name="kc_privacy_sections[' . $index . '][title]" value="' . esc_attr($title) . '"></label>';
        echo '<label><strong>Section description</strong><textarea class="widefat" rows="5" name="kc_privacy_sections[' . $index . '][description]" data-kc-section-description>' . esc_textarea($description) . '</textarea></label>';
        echo '<button type="button" class="button-link-delete" data-kc-remove-section>Remove Section</button></div>';
    }

    private static function can_save(int $postId, string $nonceKey, string $action): bool
    {
        if (wp_is_post_revision($postId) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return false;
        }
        if (! current_user_can('edit_post', $postId)) {
            return false;
        }
        $nonce = $_POST[$nonceKey] ?? null;
        return is_string($nonce) && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), $action) !== false;
    }

    public static function save_job(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== 'kc_job' || ! self::can_save($postId, 'kc_job_nonce', 'kc_save_job')) {
            return;
        }
        $raw = isset($_POST['kc_job']) && is_array($_POST['kc_job']) ? wp_unslash($_POST['kc_job']) : [];
        foreach (['salary_min', 'salary_max'] as $field) {
            $value = $raw[$field] ?? '';
            $value = is_scalar($value) ? trim((string) $value) : '';
            $number = $value !== '' && is_numeric($value) ? (float) $value : null;
            update_post_meta($postId, '_kampachi_' . $field, $number !== null && is_finite($number) && $number >= 0
                ? number_format($number, 2, '.', '')
                : '');
        }
        $savedMin = (string) get_post_meta($postId, '_kampachi_salary_min', true);
        $savedMax = (string) get_post_meta($postId, '_kampachi_salary_max', true);
        if ($savedMin !== '' && $savedMax !== '' && (float) $savedMax < (float) $savedMin) {
            update_post_meta($postId, '_kampachi_salary_max', '');
        }
        foreach (['apply_url', 'apply_email', 'apply_whatsapp'] as $field) {
            $value = $raw[$field] ?? '';
            $value = is_string($value) ? trim($value) : '';
            if ($field === 'apply_url') {
                $value = esc_url_raw($value, ['http', 'https']);
            } elseif ($field === 'apply_email') {
                $value = sanitize_email($value);
            } else {
                $value = preg_match('~^https?://~i', $value)
                    ? esc_url_raw($value, ['http', 'https'])
                    : preg_replace('/[^\d+]/', '', $value);
            }
            update_post_meta($postId, '_kampachi_' . $field, $value);
        }
    }

    public static function save_page(int $postId, WP_Post $post): void
    {
        if (! in_array($post->post_name, self::PAGE_SLUGS, true)
            || ! self::can_save($postId, 'kc_editorial_nonce', 'kc_save_editorial_page')) {
            return;
        }
        if ($post->post_name === 'about-us') {
            $raw = $_POST['kc_about_image_ids'] ?? '';
            $ids = is_string($raw) ? explode(',', sanitize_text_field(wp_unslash($raw))) : [];
            $valid = [];
            foreach ($ids as $id) {
                $id = absint($id);
                if ($id > 0 && wp_attachment_is_image($id) && ! in_array($id, $valid, true)) {
                    $valid[] = $id;
                }
            }
            update_post_meta($postId, '_kampachi_about_image_ids', $valid);
            return;
        }
        $raw = isset($_POST['kc_privacy_sections']) && is_array($_POST['kc_privacy_sections'])
            ? wp_unslash($_POST['kc_privacy_sections']) : [];
        $sections = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $title = isset($entry['title']) && is_string($entry['title']) ? sanitize_text_field($entry['title']) : '';
            $description = isset($entry['description']) && is_string($entry['description']) ? wp_kses_post($entry['description']) : '';
            if ($title !== '' || trim(wp_strip_all_tags($description)) !== '') {
                $sections[] = ['title' => $title, 'description' => $description];
            }
        }
        update_post_meta($postId, '_kampachi_privacy_sections', $sections);
    }
}
