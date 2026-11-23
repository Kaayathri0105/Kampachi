<?php
/**
 * Plugin Name: Kampachi CMS
 * Description: Native Kampachi modules for homepage, offers, sake, 47in27, outlets, press, awards, contact, careers, navigation and SEO.
 * Version: 3.5.1
 * Requires PHP: 8.1
 * Text Domain: kampachi-cms
 */

declare(strict_types=1);

use Kampachi\CMS\Awards;
use Kampachi\CMS\Press;

if (! defined('ABSPATH')) {
    exit;
}

final class Kampachi_CMS
{
    private const TYPES = [
        'kc_offer' => ['Offers & Events', 'Offer', 'dashicons-calendar-alt', 'offers'],
        'kc_sake' => ['Sake Breweries', 'Sake Brewery', 'dashicons-products', 'sake-collection'],
        'kc_outlet' => ['Outlets', 'Outlet', 'dashicons-location', 'outlets'],
        'kc_job' => ['Careers', 'Vacancy', 'dashicons-businessperson', 'vacancies'],
        Press::POST_TYPE => ['Press & Media', 'Press Article', 'dashicons-media-document', Press::PAGE_SLUG],
        Awards::POST_TYPE => [Awards::PAGE_TITLE, 'Award', 'dashicons-awards', Awards::PAGE_SLUG],
        'kc_prefecture' => ['Prefectures', 'Prefecture', 'dashicons-location-alt', 'prefectures'],
    ];

    private const FIELDS = [
        'subtitle' => ['Subtitle', 'text'],
        'price' => ['Price (MYR)', 'text'],
        'date_start' => ['Start Date', 'date'],
        'date_end' => ['End Date', 'date'],
        'address' => ['Address', 'textarea'],
        'hours' => ['Operating Hours', 'textarea'],
        'phone' => ['Telephone', 'text'],
        'email' => ['Email', 'email'],
        'whatsapp' => ['WhatsApp URL', 'url'],
        'booking_url' => ['Booking / Enquiry URL', 'url'],
        'map_url' => ['Google Maps URL', 'url'],
        'menu_url' => ['Menu PDF / URL', 'url'],
        'outlet_name' => ['Participating Outlet', 'text'],
        'terms' => ['Terms & Conditions', 'textarea'],
    ];

    private const SETTINGS = [
        'contact_email' => ['Contact email', 'email'],
        'contact_phone' => ['Contact telephone', 'text'],
        'career_email' => ['Careers email', 'email'],
        'instagram_url' => ['Instagram URL', 'url'],
        'facebook_url' => ['Facebook URL', 'url'],
    ];

    public static function init(): void
    {
        add_action('init', [self::class, 'register_types']);
        add_action('add_meta_boxes', [self::class, 'register_metaboxes']);
        add_action('save_post', [self::class, 'save_meta']);
        add_action('admin_menu', [self::class, 'register_admin']);
        add_action('admin_post_kampachi_save_settings', [self::class, 'save_settings']);
    }

    public static function register_types(): void
    {
        foreach (self::TYPES as $type => [$plural, $singular, $icon, $slug]) {
            $hasLandingPage = in_array($type, [Press::POST_TYPE, Awards::POST_TYPE], true);

            register_post_type($type, [
                'labels' => ['name' => $plural, 'singular_name' => $singular, 'add_new_item' => 'Add ' . $singular, 'edit_item' => 'Edit ' . $singular],
                'public' => true,
                'show_in_rest' => true,
                'has_archive' => $type !== 'kc_outlet' && ! $hasLandingPage,
                'rewrite' => $hasLandingPage ? ['slug' => $slug, 'with_front' => false] : ['slug' => $slug],
                'menu_icon' => $icon,
                'show_in_menu' => $type === 'kc_prefecture' ? 'kampachi-47in27-module' : true,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes'],
            ]);
        }
        register_taxonomy('kc_offer_category', ['kc_offer'], [
            'label' => 'Promotion Categories',
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => ['slug' => 'promotion-category'],
        ]);

    }

    public static function register_metaboxes(): void
    {
        foreach (array_keys(self::TYPES) as $postType) {
            if (in_array(
                $postType,
                ['kc_offer', 'kc_sake', 'kc_outlet', 'kc_job', Press::POST_TYPE, Awards::POST_TYPE],
                true
            )) {
                continue;
            }

            add_meta_box('kampachi_fields', 'Kampachi Details', [self::class, 'render_metabox'], $postType, 'normal', 'high');
        }
    }

    public static function render_metabox(WP_Post $post): void
    {
        wp_nonce_field('kampachi_save_meta', 'kampachi_meta_nonce');
        echo '<table class="form-table"><tbody>';
        foreach (self::FIELDS as $key => [$label, $type]) {
            $value = (string) get_post_meta($post->ID, '_kampachi_' . $key, true);
            echo '<tr><th><label for="kampachi_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            if ($type === 'textarea') {
                echo '<textarea rows="3" class="large-text" id="kampachi_' . esc_attr($key) . '" name="kampachi_fields[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="regular-text" id="kampachi_' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="kampachi_fields[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function save_meta(int $postId): void
    {
        if (! isset($_POST['kampachi_meta_nonce']) || ! is_string($_POST['kampachi_meta_nonce'])) {
            return;
        }
        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kampachi_meta_nonce'])), 'kampachi_save_meta')) {
            return;
        }
        if (wp_is_post_revision($postId) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (! current_user_can('edit_post', $postId)) {
            return;
        }
        $postType = get_post_type($postId);
        if (! is_string($postType) || ! isset(self::TYPES[$postType])) {
            return;
        }
        $raw = isset($_POST['kampachi_fields']) && is_array($_POST['kampachi_fields']) ? wp_unslash($_POST['kampachi_fields']) : [];
        foreach (self::FIELDS as $key => [, $type]) {
            if (! isset($raw[$key]) || ! is_string($raw[$key])) {
                continue;
            }
            $value = match ($type) {
                'url' => esc_url_raw($raw[$key]),
                'email' => sanitize_email($raw[$key]),
                'textarea' => sanitize_textarea_field($raw[$key]),
                default => sanitize_text_field($raw[$key]),
            };
            update_post_meta($postId, '_kampachi_' . $key, $value);
        }
    }

    /**
     * Register separate WordPress sidebar modules, without a CMS parent menu.
     */
    public static function register_admin(): void
    {
        add_menu_page(
            '47in27',
            '47in27',
            'edit_pages',
            'kampachi-47in27-module',
            static function (): void {
                Kampachi_47in27::editor();
            },
            'dashicons-location-alt',
            8
        );

        add_menu_page(
            'Catering & Events',
            'Catering & Events',
            'edit_pages',
            'kampachi-catering-module',
            static function (): void {
                \Kampachi\CMS\Catering::dashboard();
            },
            'dashicons-food',
            10
        );

        add_menu_page(
            'Contact',
            'Contact',
            'edit_pages',
            'kampachi-contact-module',
            static function (): void {
                self::render_page_module('Contact', 'contact-us');
            },
            'dashicons-email',
            11
        );

        add_menu_page(
            'Navigation',
            'Navigation',
            'edit_theme_options',
            'kampachi-navigation-module',
            [self::class, 'render_navigation_module'],
            'dashicons-menu',
            12
        );

        add_menu_page(
            'SEO',
            'SEO',
            'edit_pages',
            'kampachi-seo-module',
            [self::class, 'render_seo'],
            'dashicons-search',
            13
        );

        // Careers is provided by the kc_job post type, while its landing
        // page remains editable from the Careers module submenu.
        add_submenu_page(
            'edit.php?post_type=kc_job',
            'Careers Landing Page',
            'Careers Page',
            'edit_pages',
            'kampachi-careers-landing',
            static function (): void {
                self::render_page_module('Careers', 'careers');
            }
        );

        add_menu_page(
            'About Kampachi',
            'About Us',
            'edit_pages',
            'kampachi-about',
            static function (): void {
                self::render_page_module('About Us', 'about-us');
            },
            'dashicons-book-alt',
            16
        );

        add_menu_page(
            'Privacy & Policy',
            'Privacy & Policy',
            'edit_pages',
            'kampachi-privacy',
            static function (): void {
                self::render_page_module('Privacy & Policy', 'privacy-policy');
            },
            'dashicons-shield',
            17
        );

        add_submenu_page(
            'kampachi-contact-module',
            'Global Contact Settings',
            'Contact Settings',
            'manage_options',
            'kampachi-global-settings',
            [self::class, 'render_settings']
        );

    }

    /**
     * Show homepage editing actions without replacing WordPress page content.
     */
    public static function render_homepage_module(): void
    {
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-module-admin"><h1>Homepage</h1>';
        self::render_page_links('home');

        if (current_user_can('manage_options')) {
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=kampachi-homepage')) . '">Edit Hero &amp; Homepage Settings</a></p>';
        }

        echo '</div>';
    }

    /**
     * Present a dedicated page-management screen for a standard WP Page.
     */
    public static function render_page_module(string $heading, string $slug): void
    {
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-module-admin"><h1>' . esc_html($heading) . '</h1><section class="kc-module-panel"><h2>Page Content</h2>';
        self::render_page_links($slug);
        echo '</section></div>';
    }

    private static function render_page_links(string $slug): void
    {
        $page = $slug === 'home' ? get_page_by_path($slug) : Kampachi_Landings::page($slug);

        if (! $page instanceof WP_Post) {
            echo '<p>The page has not been created yet.</p>';
            if ($slug === 'home') {
                echo '<p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=page')) . '">Create Page</a></p>';
            }
            return;
        }

        if (current_user_can('edit_post', $page->ID)) {
            echo '<p><a class="button button-primary" href="' . esc_url(get_edit_post_link($page->ID, 'raw')) . '">Edit Page Content</a></p>';
        }

        $permalink = get_permalink($page->ID);

        if (is_string($permalink)) {
            echo '<p><a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url($permalink) . '">View Website Page</a></p>';
        }
    }

    public static function render_navigation_module(): void
    {
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-module-admin"><h1>Navigation</h1>';
        echo '<section class="kc-module-panel"><h2>Website Navigation</h2>';
        echo '<p>Manage header menus and reservation outlets under Header Settings. Footer navigation continues to use WordPress menus.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=kampachi-header')) . '">Edit Header Navigation</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('nav-menus.php')) . '">Edit Footer WordPress Menus</a></p>';
        echo '</section></div>';
    }

    public static function render_settings(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $values = get_option('kampachi_cms_settings', []);
        $values = is_array($values) ? $values : [];
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-module-admin"><h1>Kampachi Global Contact Settings</h1><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kampachi_save_settings">';
        wp_nonce_field('kampachi_settings', 'kampachi_settings_nonce');
        echo '<section class="kc-module-panel"><h2>Contact Information</h2><table class="form-table"><tbody>';
        foreach (self::SETTINGS as $key => [$label, $type]) {
            $value = isset($values[$key]) && is_scalar($values[$key]) ? (string) $values[$key] : '';
            echo '<tr><th><label for="setting_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            if ($type === 'textarea') {
                echo '<textarea id="setting_' . esc_attr($key) . '" class="large-text" rows="5" name="settings[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input id="setting_' . esc_attr($key) . '" class="large-text" type="' . esc_attr($type) . '" name="settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></section>';
        echo '<div class="kc-save-bar"><span><strong>Contact settings</strong><small>Changes take effect after saving.</small></span>';
        submit_button('Save Kampachi Settings');
        echo '</div></form></div>';
    }

    public static function save_settings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }
        check_admin_referer('kampachi_settings', 'kampachi_settings_nonce');
        $raw = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        $settings = get_option('kampachi_cms_settings', []);
        $settings = is_array($settings) ? $settings : [];
        foreach (self::SETTINGS as $key => [, $type]) {
            $value = isset($raw[$key]) && is_string($raw[$key]) ? $raw[$key] : '';
            $settings[$key] = match ($type) {
                'url' => esc_url_raw($value),
                'email' => sanitize_email($value),
                'textarea' => sanitize_textarea_field($value),
                default => sanitize_text_field($value),
            };
        }
        update_option('kampachi_cms_settings', $settings, false);
        wp_safe_redirect(admin_url('admin.php?page=kampachi-global-settings&updated=1'));
        exit;
    }

    public static function render_seo(): void
    {
        \Kampachi\CMS\Seo::dashboard();
    }

    public static function activate(): void
    {
        self::register_types();
        foreach (['dining' => 'Dining', 'seasonal' => 'Seasonal', 'buffet' => 'Buffet'] as $termSlug => $termName) {
            if (! term_exists($termSlug, 'kc_offer_category')) {
                wp_insert_term($termName, 'kc_offer_category', ['slug' => $termSlug]);
            }
        }

        if (! get_page_by_path('home') instanceof WP_Post) {
            $id = wp_insert_post([
                'post_type' => 'page',
                'post_title' => 'Home',
                'post_name' => 'home',
                'post_status' => 'publish',
            ], true);
            if (is_int($id) && $id > 0 && ! get_option('page_on_front')) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $id);
            }
        }
        Kampachi_Landings::register_type();
        Kampachi_Landings::install();
        \Kampachi\CMS\Catering::register_type();
        flush_rewrite_rules();
    }
}

require_once __DIR__ . '/includes/class-kampachi-press.php';
require_once __DIR__ . '/includes/class-kampachi-awards.php';
require_once __DIR__ . '/includes/class-kampachi-content.php';
require_once __DIR__ . '/includes/class-kampachi-seo.php';
require_once __DIR__ . '/includes/class-kampachi-admin-style.php';
require_once __DIR__ . '/includes/class-kampachi-hero-motion.php';
require_once __DIR__ . '/includes/class-kampachi-catering.php';
require_once __DIR__ . '/includes/class-kampachi-sake.php';
require_once __DIR__ . '/includes/class-kampachi-sake-bottles.php';
require_once __DIR__ . '/includes/class-kampachi-outlets.php';
require_once __DIR__ . '/includes/class-kampachi-offers.php';
require_once __DIR__ . '/includes/class-kampachi-editorial.php';
require_once __DIR__ . '/includes/class-kampachi-shell-settings.php';
require_once __DIR__ . '/includes/class-kampachi-homepage.php';
require_once __DIR__ . '/includes/class-kampachi-47in27.php';
require_once __DIR__ . '/includes/class-kampachi-landings.php';
require_once __DIR__ . '/includes/class-kampachi-collection-landing.php';
require_once __DIR__ . '/includes/class-kampachi-offers-landing.php';
require_once __DIR__ . '/includes/class-kampachi-outlets-landing.php';
require_once __DIR__ . '/includes/class-kampachi-press-landing.php';
require_once __DIR__ . '/includes/class-kampachi-awards-landing.php';
require_once __DIR__ . '/includes/class-kampachi-promotions-import.php';
require_once __DIR__ . '/includes/class-kampachi-master-settings.php';
require_once __DIR__ . '/includes/class-kampachi-wp-config-manager.php';
require_once __DIR__ . '/includes/class-kampachi-github-sync.php';
require_once __DIR__ . '/includes/class-kampachi-translations.php';

/**
 * Remove the built-in body editor from the Kampachi post types.
 *
 * Run after custom types register; leave Pages unchanged because their
 * templates can still consume post_content.
 */
add_action('init', static function (): void {
    foreach ([
        'post',
        'kc_offer',
        'kc_sake',
        'kc_outlet',
        'kc_event',
    ] as $postType) {
        if (post_type_exists($postType)) {
            remove_post_type_support($postType, 'editor');
        }
    }
}, 999);

/**
 * Keep previously stored body text when saving from a screen that no
 * longer sends a post_content field. Does not affect explicit content edits.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $postarr
 * @param array<string, mixed> $unsanitizedPostarr
 * @return array<string, mixed>
 */
add_filter('wp_insert_post_data', static function (
    array $data,
    array $postarr,
    array $unsanitizedPostarr,
    bool $update
): array {
    if (! $update || ! is_admin() || wp_doing_ajax()) {
        return $data;
    }

    if (
        ! isset($_POST['action'])
        || $_POST['action'] !== 'editpost'
        || array_key_exists('post_content', $_POST)
    ) {
        return $data;
    }

    $postId = absint($postarr['ID'] ?? 0);
    if ($postId === 0) {
        return $data;
    }

    $post = get_post($postId);
    if (! $post instanceof WP_Post) {
        return $data;
    }

    if (! in_array($post->post_type, [
        'post',
        Kampachi_Landings::POST_TYPE,
        'kc_offer',
        'kc_sake',
        'kc_outlet',
        'kc_job',
        'kc_prefecture',
        'kc_event',
    ], true)) {
        return $data;
    }

    $data['post_content'] = $post->post_content;

    return $data;
}, 10, 4);

Kampachi_CMS::init();
\Kampachi\CMS\Content::boot();
\Kampachi\CMS\Seo::boot();
\Kampachi\CMS\AdminStyle::boot();
\Kampachi\CMS\HeroMotion::boot();
\Kampachi\CMS\Catering::boot();
\Kampachi\CMS\Sake::boot();
\Kampachi\CMS\SakeBottles::boot();
\Kampachi\CMS\Outlets::boot();
\Kampachi\CMS\Offers::boot();
Kampachi_Editorial::boot();
Kampachi_Shell_Settings::boot();
Kampachi_Homepage::boot();
Kampachi_47in27::boot();
Kampachi_Landings::boot();
Press::boot();
Awards::boot();
\Kampachi\CMS\OffersLanding::boot();
\Kampachi\CMS\OutletsLanding::boot();
\Kampachi\CMS\PressLanding::boot();
\Kampachi\CMS\AwardsLanding::boot();
Kampachi_Master_Settings::boot();
\Kampachi\CMS\WPConfigManager::boot();
Kampachi_GitHub_Sync::boot();
Kampachi_Translations::boot();
register_activation_hook(__FILE__, [Kampachi_CMS::class, 'activate']);
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

add_action('admin_notices', static function (): void {
    if (! current_user_can('activate_plugins')) {
        return;
    }

    foreach (['kampachi-homepage-module/kampachi-homepage-module.php', 'kampachi-content-manager/kampachi-content-manager.php'] as $legacy) {
        if (function_exists('is_plugin_active') && is_plugin_active($legacy)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Deactivate the old Kampachi plugin: ', 'kampachi-cms') . esc_html($legacy) . '</p></div>';
        }
    }
});
