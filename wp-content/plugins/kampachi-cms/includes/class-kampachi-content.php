<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Keep module card excerpts separate from the introduction and main editor.
 */
final class Content
{
    public const DESCRIPTION_EDITOR = 'kampachi-description-editor';

    public const POST_TYPES = [
        'kc_offer', 'kc_sake', 'kc_outlet', 'kc_event', 'kc_job', 'kc_prefecture', Press::POST_TYPE,
    ];

    private const LABELS = [
        'discover' => 'Discover',
        'more' => 'More',
        'locations' => 'Locations',
        'visit_us' => 'Visit Us',
        'menus' => 'Menus',
        'signature_dishes' => 'Signature Dishes',
        'opening_hours' => 'Hours of Operation',
        'contact' => 'Contact',
        'address' => 'Address',
        'location' => 'Location',
        'directions' => 'Open in Google Maps',
        'offer_details' => 'Offer Details',
        'schedule' => 'Date & Time',
        'offers' => 'Offers & Events',
        'enquire' => 'Enquire Now',
        'back_sake' => 'Back to Sake Breweries',
        'back_events' => 'Back to Catering & Events',
        'back_offers' => 'Back to Offers & Events',
        'back_careers' => 'Back to Careers',
        'back_prefectures' => 'Back to 47in27',
        'back_outlets' => 'Back to Outlets',
        'back_press' => 'Back to Press & Media',
        'view_original_article' => 'Read the original article',
        'back_home' => 'Back to Home',
        'contact_title' => 'Contact Us',
        'enquiry_title' => 'Send an Enquiry',
        'enquiry_button' => 'Send Enquiry',
        'apply_online' => 'Apply Online',
        'apply_email' => 'Apply by Email',
        'apply_whatsapp' => 'Apply via WhatsApp',
        'price' => 'Price',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'salary' => 'Salary',
    ];

    public static function boot(): void
    {
        add_filter('get_the_excerpt', [self::class, 'excerpt'], 99, 2);
        add_action('add_meta_boxes', [self::class, 'registerBoxes'], 99);
        add_action('admin_menu', [self::class, 'registerMenu'], 99);
        add_action('admin_post_kampachi_save_website_text', [self::class, 'saveLabels']);
    }

    public static function enqueueDescriptionEditor(): void
    {
        wp_enqueue_editor();
        $asset = 'description-editor.js';
        wp_enqueue_script(
            self::DESCRIPTION_EDITOR,
            plugins_url('../assets/' . $asset, __FILE__),
            ['editor', 'quicktags'],
            (string) filemtime(dirname(__DIR__) . '/assets/' . $asset),
            true
        );
    }

    public static function label(string $key): string
    {
        if (! array_key_exists($key, self::LABELS)) {
            return '';
        }

        $settings = get_option('kampachi_website_labels', []);
        if (is_array($settings) && array_key_exists($key, $settings)) {
            return is_string($settings[$key]) ? $settings[$key] : '';
        }

        return __(self::LABELS[$key], 'kampachi-cms');
    }

    public static function registerMenu(): void
    {
        add_submenu_page(
            'kampachi-header',
            'Website Text',
            'Website Text',
            'manage_options',
            'kampachi-website-text',
            [self::class, 'renderLabels']
        );
    }

    public static function renderLabels(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot edit website text.', 'kampachi-cms'), '', ['response' => 403]);
        }

        echo '<div class="wrap"><h1>Website Text</h1>';
        echo '<p>Manage shared button text and section labels here. Page descriptions and images stay in their own CMS modules.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kampachi_save_website_text">';
        wp_nonce_field('kampachi_save_website_text');
        echo '<table class="form-table"><tbody>';

        foreach (self::LABELS as $key => $default) {
            echo '<tr><th><label for="kc-label-' . esc_attr($key) . '">' . esc_html($default) . '</label></th>';
            echo '<td><input class="regular-text" id="kc-label-' . esc_attr($key) . '" name="labels[';
            echo esc_attr($key) . ']" value="' . esc_attr(self::label($key)) . '"></td></tr>';
        }

        echo '</tbody></table>';
        submit_button('Save Website Text');
        echo '</form></div>';
    }

    public static function saveLabels(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot edit website text.', 'kampachi-cms'), '', ['response' => 403]);
        }

        check_admin_referer('kampachi_save_website_text');
        $raw = $_POST['labels'] ?? null;
        if (! is_array($raw)) {
            wp_die(esc_html__('Website text must be a list of fields.', 'kampachi-cms'), '', ['response' => 400]);
        }

        $settings = get_option('kampachi_website_labels', []);
        $settings = is_array($settings) ? $settings : [];
        foreach (array_keys(self::LABELS) as $key) {
            if (isset($raw[$key]) && is_string($raw[$key])) {
                $settings[$key] = sanitize_text_field(wp_unslash($raw[$key]));
            }
        }

        update_option('kampachi_website_labels', $settings, false);
        wp_safe_redirect(admin_url('admin.php?page=kampachi-website-text&updated=1'));
        exit;
    }

    public static function excerpt(string $excerpt, WP_Post $post): string
    {
        if (! in_array($post->post_type, self::POST_TYPES, true)) {
            return $excerpt;
        }

        return trim(wp_strip_all_tags($post->post_excerpt));
    }

    public static function registerBoxes(): void
    {
        foreach (self::POST_TYPES as $postType) {
            // Offers already provides this field inside its introduction editor.
            if ($postType === 'kc_offer') {
                continue;
            }

            remove_meta_box('postexcerpt', $postType, 'normal');
            add_meta_box(
                'postexcerpt',
                __('Short Description (Excerpt) — Cards Only', 'kampachi-cms'),
                'post_excerpt_meta_box',
                $postType,
                'normal',
                'high'
            );
        }
    }
}
