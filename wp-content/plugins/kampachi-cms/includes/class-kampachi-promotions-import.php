<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Idempotent Kampachi 15 promotions importer. Existing posts are never replaced.
 */
final class Kampachi_Promotions_Import
{
    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 25);
        add_action('admin_post_kampachi_import_promotions', [self::class, 'run_from_admin']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=kc_offer',
            'Import Kampachi 15 Promotions',
            'Import Kampachi 15',
            'manage_options',
            'kampachi-import-promotions',
            [self::class, 'render_admin']
        );
    }

    public static function render_admin(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap"><h1>Kampachi 15 — Promotions</h1>';
        echo '<p>Creates any missing promotions from the supplied Kampachi 15 reference. Existing WordPress promotions, edits, and featured images are preserved.</p>';

        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success"><p>Created ' . esc_html((string) absint($_GET['created'])) . ' new promotions. Review content and expiry dates before going live.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kampachi_import_promotions">';
        wp_nonce_field('kampachi_import_promotions');
        submit_button('Import missing promotions');
        echo '</form></div>';
    }

    public static function run_from_admin(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'kampachi-cms'), '', ['response' => 403]);
        }

        check_admin_referer('kampachi_import_promotions');
        $created = self::import_missing();
        wp_safe_redirect(add_query_arg(
            ['page' => 'kampachi-import-promotions', 'post_type' => 'kc_offer', 'created' => $created],
            admin_url('edit.php')
        ));
        exit;
    }

    public static function import_missing(): int
    {
        $path = dirname(__DIR__) . '/data/promotions.json';

        if (! is_readable($path)) {
            return 0;
        }

        $records = json_decode((string) file_get_contents($path), true);

        if (! is_array($records)) {
            return 0;
        }

        $created = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $slug = sanitize_title((string) ($record['slug'] ?? ''));

            if ($slug === '' || get_page_by_path($slug, OBJECT, 'kc_offer') instanceof WP_Post) {
                continue;
            }

            $imageUrl = esc_url_raw((string) ($record['image'] ?? ''));
            $host = wp_parse_url($imageUrl, PHP_URL_HOST);

            if (! in_array($host, ['kampachi.com.my', 'www.kampachi.com.my'], true)) {
                $imageUrl = '';
            }

            $content = self::build_content($record);
            $postId = wp_insert_post([
                'post_type' => 'kc_offer',
                'post_title' => sanitize_text_field((string) ($record['title'] ?? '')),
                'post_name' => $slug,
                'post_excerpt' => sanitize_textarea_field((string) ($record['excerpt'] ?? '')),
                'post_content' => wp_kses_post($content),
                'post_status' => 'publish',
            ], true);

            if (! is_int($postId) || $postId < 1) {
                continue;
            }

            update_post_meta($postId, '_kampachi_subtitle', sanitize_text_field((string) ($record['subtitle'] ?? '')));
            update_post_meta($postId, '_kampachi_badge', sanitize_text_field((string) ($record['badge'] ?? '')));
            update_post_meta($postId, '_kampachi_source_image_url', $imageUrl);

            $category = sanitize_title((string) ($record['category'] ?? 'dining'));

            if (taxonomy_exists('kc_offer_category') && $category !== '') {
                wp_set_object_terms($postId, $category, 'kc_offer_category');
            }

            $created++;
        }

        return $created;
    }

    private static function build_content(array $record): string
    {
        $markup = '<h2>Offer Details</h2><dl class="kc-offer-facts">';

        foreach ((array) ($record['facts'] ?? []) as $fact) {
            if (! is_array($fact)) {
                continue;
            }

            $label = sanitize_text_field((string) ($fact['label'] ?? ''));
            $value = sanitize_textarea_field((string) ($fact['value'] ?? ''));

            if ($label !== '' && $value !== '') {
                $markup .= '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
            }
        }

        $markup .= '</dl><h2>Book or Enquire</h2><p>Contact your preferred Kampachi outlet to confirm availability and current details.</p><div class="kc-offer-outlets">';

        foreach ((array) ($record['outlets'] ?? []) as $outlet) {
            if (! is_array($outlet)) {
                continue;
            }

            $name = sanitize_text_field((string) ($outlet['name'] ?? ''));
            $url = esc_url_raw((string) ($outlet['booking_url'] ?? ''));

            if ($name !== '' && $url !== '') {
                $markup .= '<p><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($name) . ' — Book or enquire</a></p>';
            }
        }

        $markup .= '</div>';
        $terms = sanitize_textarea_field((string) ($record['terms'] ?? ''));

        if ($terms !== '') {
            $markup .= '<h2>Terms & Conditions</h2><p>' . esc_html($terms) . '</p>';
        }

        return $markup;
    }
}
