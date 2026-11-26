<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-kampachi-header-editor.php';
require_once __DIR__ . '/class-kampachi-footer-navigation.php';
require_once __DIR__ . '/class-kampachi-shell-translations.php';

/**
 * Editable header, footer and enquiry delivery settings.
 */
final class Kampachi_Shell_Settings
{
    private const GROUPS = [
        'header' => [
            'header_logo_id' => ['Header logo', 'image'],
            'reservation_label' => ['CTA button title (Reservation)', 'text'],
            'reservation_enabled' => ['Show CTA button', 'checkbox'],
        ],
        'footer' => [
            'footer_logo_id' => ['Footer logo', 'image'],
            'footer_background_id' => ['Footer background image (all pages)', 'image'],
            'footer_explore_title' => ['Navigation heading', 'text'],
            'footer_office_title' => ['Address heading', 'text'],
            'footer_address' => ['Head office address', 'textarea'],
            'footer_contact_title' => ['Contact heading', 'text'],
            'contact_email' => ['Public contact email', 'email'],
            'contact_phone' => ['Public telephone', 'text'],
            'footer_cta_text' => ['Enquiry button text', 'text'],
            'footer_cta_url' => ['Fallback link if the enquiry form is unavailable (optional)', 'url'],
            'footer_company' => ['Copyright company name', 'text'],
            'instagram_url' => ['Instagram URL', 'url'],
            'facebook_url' => ['Facebook URL', 'url'],
            'youtube_url' => ['YouTube URL', 'url'],
            'tiktok_url' => ['TikTok URL', 'url'],
            'xiaohongshu_url' => ['Xiaohongshu (XHS) URL', 'url'],
            'dianping_url' => ['Dianping URL', 'url'],
        ],
        'enquiry' => [
            'enquiry_recipients' => ['Send enquiries to (comma-separated email addresses)', 'emails'],
            'enquiry_subject_prefix' => ['Email subject prefix', 'text'],
        ],
    ];

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menus'], 45);
        add_action('admin_post_kc_save_shell_settings', [self::class, 'save']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_kc_send_enquiry', [self::class, 'send_enquiry']);
        add_action('admin_post_nopriv_kc_send_enquiry', [self::class, 'send_enquiry']);
    }

    public static function menus(): void
    {
        add_menu_page('Header Settings', 'Header', 'manage_options', 'kampachi-header', static function (): void {
            self::render('header');
        }, 'dashicons-align-center', 14);

        add_menu_page('Footer Settings', 'Footer', 'manage_options', 'kampachi-footer', static function (): void {
            self::render('footer');
        }, 'dashicons-align-full-width', 15);

        add_submenu_page('kampachi-contact-module', 'Enquiry Settings', 'Enquiry Settings', 'manage_options', 'kampachi-enquiries', static function (): void {
            self::render('enquiry');
        });
    }

    public static function assets(string $hook): void
    {
        if (! str_contains($hook, 'kampachi-header') && ! str_contains($hook, 'kampachi-footer')) {
            return;
        }
        wp_enqueue_media();
        $languageStyles = dirname(__DIR__) . '/assets/post-translation-tabs.css';
        wp_enqueue_style(
            'kc-post-translation-tabs',
            plugins_url('../assets/post-translation-tabs.css', __FILE__),
            [],
            (string) filemtime($languageStyles)
        );
        if (str_contains($hook, 'kampachi-header')) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style('kc-header-admin', plugins_url('../assets/header-admin.css', __FILE__), [], (string) filemtime(dirname(__DIR__) . '/assets/header-admin.css'));
            wp_enqueue_script('kc-header-admin', plugins_url('../assets/header-admin.js', __FILE__), ['jquery', 'jquery-ui-sortable'], '2.8.1', true);
        }
        if (str_contains($hook, 'kampachi-footer')) {
            $footerAssets = dirname(__DIR__) . '/assets/';
            wp_enqueue_style('kc-footer-admin', plugins_url('../assets/footer-admin.css', __FILE__), [], (string) filemtime($footerAssets . 'footer-admin.css'));
            wp_enqueue_script('kc-footer-admin', plugins_url('../assets/footer-admin.js', __FILE__), [], (string) filemtime($footerAssets . 'footer-admin.js'), true);
        }
        wp_enqueue_script('kc-shell-admin', plugins_url('../assets/shell-admin.js', __FILE__), ['jquery'], '2.8.1', true);
    }

    public static function get(string $key, string $fallback = ''): string
    {
        $settings = get_option('kampachi_cms_settings', []);
        $value = is_array($settings) ? ($settings[$key] ?? '') : '';
        return is_scalar($value) && (string) $value !== '' ? (string) $value : $fallback;
    }

    public static function render(string $group): void
    {
        if (! current_user_can('manage_options') || ! isset(self::GROUPS[$group])) {
            wp_die(esc_html__('You do not have permission to edit these settings.'), '', ['response' => 403]);
        }
        $headings = ['header' => 'Header', 'footer' => 'Footer', 'enquiry' => 'Enquiry Email'];
        $language = Kampachi_Shell_Translations::language($group, $_GET['kc_language'] ?? 'en') ?? 'en';
        $url = self::admin_url_for($group);
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-settings-admin"><h1>Kampachi ' . esc_html($headings[$group]) . ' Settings</h1>';
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }
        Kampachi_Shell_Translations::render_tabs($group, $language, $url);
        if ($language === 'en' && $group === 'header') {
            echo '<p>Manage all header content here. The navigation and reservation outlet links below replace the old menu and automatic booking dropdown after saving.</p>';
        } elseif ($language === 'en' && $group === 'footer') {
            echo '<p>Manage the footer content and page links here. Add pages and arrange them in Footer Pages &amp; Links below. Changes apply to every page using the shared footer.</p>';
        } elseif ($group === 'enquiry') {
            echo '<p>Enquiries from the footer and Contact Us forms will be emailed to these addresses. This setting does not control job application emails.</p>';
            echo '<p>WordPress email delivery requires a functioning mail service (for example, configured SMTP). Form submissions are not stored by this module.</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kc_save_shell_settings">';
        echo '<input type="hidden" name="group" value="' . esc_attr($group) . '">';
        echo '<input type="hidden" name="kc_language" value="' . esc_attr($language) . '">';
        wp_nonce_field(self::nonce_action($group, $language), 'kc_shell_nonce');
        if ($language === 'en') {
            self::render_settings($group);
        } else {
            Kampachi_Shell_Translations::render_fields($group, $language);
        }
        echo '<div class="kc-save-bar"><span><strong>' . esc_html($headings[$group]) . ' settings</strong><small>Save to publish your changes.</small></span>';
        submit_button('Save ' . $headings[$group] . ' Settings');
        echo '</div></form></div>';
    }

    private static function render_settings(string $group): void
    {
        if ($group === 'header') {
            echo '<section class="kc-header-section"><h2><span class="kc-section-number">01</span> Header Logo</h2>';
        }
        if ($group !== 'header') {
            echo '<section class="kc-module-panel"><h2>' . esc_html($group === 'footer' ? 'Footer Content' : 'Enquiry Recipients') . '</h2>';
        }
        echo '<table class="form-table"><tbody>';
        foreach (self::GROUPS[$group] as $key => [$label, $type]) {
            if ($group === 'header' && $key !== 'header_logo_id') {
                // Reservation controls are rendered in Section 3, not the logo section.
                continue;
            }
            $value = self::get($key);
            if ($key === 'reservation_enabled' && $value === '') {
                $value = '1';
            }
            echo '<tr><th scope="row"><label for="kc-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            $name = 'settings[' . esc_attr($key) . ']';
            if ($type === 'checkbox') {
                echo '<label><input type="checkbox" id="kc-' . esc_attr($key) . '" name="' . $name . '" value="1" ' . checked($value, '1', false) . '> Enabled</label>';
            } elseif ($type === 'textarea') {
                echo '<textarea rows="4" class="large-text" id="kc-' . esc_attr($key) . '" name="' . $name . '">' . esc_textarea($value) . '</textarea>';
            } elseif ($type === 'image') {
                $id = absint($value);
                $imageUrl = $id > 0 ? wp_get_attachment_image_url($id, 'thumbnail') : false;
                echo '<input type="hidden" id="kc-' . esc_attr($key) . '" name="' . $name . '" value="' . esc_attr($value) . '">';
                echo '<img class="kc-shell-preview" style="display:block;max-height:100px;max-width:220px;margin-bottom:8px" src="' . esc_url(is_string($imageUrl) ? $imageUrl : '') . '" alt=""' . ($imageUrl ? '' : ' hidden') . '>';
                $buttonLabel = $key === 'footer_background_id' ? 'Choose Background Image' : 'Choose Logo';
                echo '<button type="button" class="button kc-shell-pick-image" data-target="kc-' . esc_attr($key) . '">' . esc_html($buttonLabel) . '</button> ';
                echo '<button type="button" class="button kc-shell-clear-image" data-target="kc-' . esc_attr($key) . '">Clear</button>';
            } else {
                $inputType = in_array($type, ['email', 'url'], true) ? $type : 'text';
                echo '<input class="regular-text" id="kc-' . esc_attr($key) . '" type="' . esc_attr($inputType) . '" name="' . $name . '" value="' . esc_attr($value) . '">';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></section>';
        if ($group === 'header') {
            Kampachi_Header_Editor::render();
        } elseif ($group === 'footer') {
            \Kampachi\CMS\FooterNavigation::renderEditor();
        }
    }

    public static function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }
        $group = isset($_POST['group']) && is_string($_POST['group'])
            ? sanitize_key(wp_unslash($_POST['group'])) : '';
        if (! isset(self::GROUPS[$group])) {
            wp_die('Unknown settings group.', '', ['response' => 400]);
        }
        $language = Kampachi_Shell_Translations::language($group, $_POST['kc_language'] ?? 'en');
        if ($language === null) {
            wp_die('Unknown settings language.', '', ['response' => 400]);
        }
        check_admin_referer(self::nonce_action($group, $language), 'kc_shell_nonce');
        $raw = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        if ($language !== 'en') {
            Kampachi_Shell_Translations::save($group, $language, $raw);
            wp_safe_redirect(add_query_arg(['updated' => '1', 'kc_language' => $language], self::admin_url_for($group)));
            exit;
        }
        $settings = get_option('kampachi_cms_settings', []);
        $settings = is_array($settings) ? $settings : [];
        foreach (self::GROUPS[$group] as $key => [, $type]) {
            $value = isset($raw[$key]) && is_string($raw[$key]) ? trim($raw[$key]) : '';
            $settings[$key] = match ($type) {
                'email' => sanitize_email($value),
                'url' => esc_url_raw($value, ['http', 'https']),
                'textarea' => sanitize_textarea_field($value),
                'image' => self::verified_image($value),
                'checkbox' => $value === '1' ? '1' : '0',
                'emails' => self::valid_emails_string($value),
                default => sanitize_text_field($value),
            };
        }
        if ($group === 'header') {
            $settings['header_nav_items'] = Kampachi_Header_Editor::sanitize_nav(wp_unslash($_POST['header_nav'] ?? []));
            $settings['header_booking_items'] = Kampachi_Header_Editor::sanitize_booking(wp_unslash($_POST['header_booking'] ?? []));
        }
        if ($group === 'footer' && ($_POST['footer_nav_present'] ?? null) === '1') {
            $links = $_POST['footer_nav'] ?? [];
            if (! is_array($links)) {
                wp_die('Footer links must be a list.', '', ['response' => 400]);
            }
            $settings['footer_nav_items'] = \Kampachi\CMS\FooterNavigation::sanitizeItems(wp_unslash($links));
        }
        update_option('kampachi_cms_settings', $settings, false);
        wp_safe_redirect(add_query_arg('updated', '1', self::admin_url_for($group)));
        exit;
    }

    private static function admin_url_for(string $group): string
    {
        $page = match ($group) {
            'header' => 'kampachi-header',
            'footer' => 'kampachi-footer',
            default => 'kampachi-enquiries',
        };

        return admin_url('admin.php?page=' . $page);
    }

    private static function nonce_action(string $group, string $language): string
    {
        return 'kc_save_shell_' . $group . ($language === 'en' ? '' : '_' . $language);
    }

    private static function verified_image(string $value): string
    {
        $id = absint($value);
        return $id > 0 && wp_attachment_is_image($id) ? (string) $id : '';
    }

    private static function valid_emails_string(string $value): string
    {
        $emails = preg_split('/[,;\r\n]+/', $value) ?: [];
        $result = [];
        foreach ($emails as $email) {
            $email = sanitize_email(trim($email));
            if (is_email($email) && ! in_array($email, $result, true)) {
                $result[] = $email;
            }
        }
        return implode(', ', $result);
    }

    /** @return array<int, string> */
    public static function enquiry_recipients(): array
    {
        $configured = self::get('enquiry_recipients');
        $fallback = self::get('contact_email', 'kampachi@equatorial.com');
        $addresses = explode(',', $configured !== '' ? $configured : $fallback);
        return array_values(array_filter(array_map('trim', $addresses), 'is_email'));
    }

    public static function send_enquiry(): void
    {
        $fromFooter = ($_POST['kc_enquiry_source'] ?? null) === 'footer';
        $return = home_url('/contact-us/');
        $statusKey = 'enquiry';
        if ($fromFooter) {
            $referer = wp_get_referer();
            if ($referer !== false) {
                $return = explode('#', $referer, 2)[0] . '#footer-enquiry';
                $statusKey = 'footer_enquiry';
            }
        }
        $redirect = static function (string $state) use ($return, $statusKey): void {
            wp_safe_redirect(add_query_arg($statusKey, $state, $return), 303);
            exit;
        };
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            $redirect('invalid');
        }
        $nonce = $_POST['kc_enquiry_nonce'] ?? null;
        if (! is_string($nonce) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'kc_send_enquiry')) {
            $redirect('invalid');
        }
        if (isset($_POST['website']) && is_string($_POST['website']) && trim($_POST['website']) !== '') {
            $redirect('sent');
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $fingerprint = 'kc_form_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $count = (int) get_transient($fingerprint);
        if ($count >= 5) {
            $redirect('limited');
        }
        set_transient($fingerprint, $count + 1, HOUR_IN_SECONDS);
        $name = self::enquiry_name($fromFooter);
        $telephone = $fromFooter ? self::enquiry_text('telephone') : '';
        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $subject = self::enquiry_text('subject');
        $message = isset($_POST['message']) && is_string($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        if (
            $name === '' || ! is_email($email) || mb_strlen($telephone, 'UTF-8') > 40
            || $subject === '' || mb_strlen($subject, 'UTF-8') > 160
            || $message === '' || mb_strlen($message, 'UTF-8') > 5000
        ) {
            $redirect('invalid');
        }
        $recipients = self::enquiry_recipients();
        if ($recipients === []) {
            $redirect('unavailable');
        }
        $prefix = self::get('enquiry_subject_prefix', 'Kampachi Enquiry');
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email];
        $contact = "Name: {$name}\nEmail: {$email}";
        if ($telephone !== '') {
            $contact .= "\nTelephone: {$telephone}";
        }
        $content = "{$contact}\nSubject: {$subject}\n\n{$message}";
        $sent = wp_mail($recipients, sanitize_text_field($prefix . ' — ' . $subject), $content, $headers);
        $redirect($sent ? 'sent' : 'failed');
    }

    private static function enquiry_name(bool $fromFooter): string
    {
        if (! $fromFooter) {
            $name = self::enquiry_text('name');
            return mb_strlen($name, 'UTF-8') <= 120 ? $name : '';
        }

        $firstName = self::enquiry_text('first_name');
        $lastName = self::enquiry_text('last_name');
        if (
            $firstName === '' || $lastName === ''
            || mb_strlen($firstName, 'UTF-8') > 60 || mb_strlen($lastName, 'UTF-8') > 60
        ) {
            return '';
        }

        return $firstName . ' ' . $lastName;
    }

    private static function enquiry_text(string $field): string
    {
        $value = $_POST[$field] ?? null;
        return is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
    }
}
