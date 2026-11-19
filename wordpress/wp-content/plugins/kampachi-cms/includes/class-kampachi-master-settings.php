<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One authoritative design / integration settings panel for Kampachi CMS.
 * Credentials are deliberately read from wp-config.php, never saved in options.
 */
final class Kampachi_Master_Settings
{
    private const OPTION = 'kampachi_master_settings';

    private const TABS = [
        'menu' => 'Menu Style',
        'fonts' => 'Overall Fonts',
        'smtp' => 'SMTP Mail',
        'languages' => 'Languages',
        'github' => 'GitHub',
        'tracking' => 'Tracking / GTM',
        'config' => 'WP Config',
    ];

    private const FONT_CHOICES = [
        'Libre Baskerville' => 'Libre Baskerville',
        'Noto Serif JP' => 'Noto Serif JP',
        'Georgia' => 'Georgia',
        'Arial' => 'Arial',
        'Inter' => 'Inter',
        'custom' => 'Uploaded TTF font',
    ];

    private const LANGUAGES = [
        'en' => 'English',
        'ms' => 'Bahasa Melayu',
        'zh' => 'Chinese (Simplified)',
        'ja' => 'Japanese',
        'ta' => 'Tamil',
        'vi' => 'Vietnamese',
        'fr' => 'French',
        'de' => 'German',
    ];

    private const DEFAULTS = [
        'menu_gap' => '14',
        'menu_font' => 'Libre Baskerville',
        'menu_font_id' => '0',
        'menu_font_size' => '12',
        'menu_color' => '#f4f0eb',
        'before_mode' => 'solid',
        'before_color' => '#000000',
        'before_opacity' => '100',
        'before_blur' => '12',
        'after_mode' => 'solid',
        'after_color' => '#000000',
        'after_opacity' => '100',
        'after_blur' => '12',
        'font_family' => 'Libre Baskerville',
        'font_id' => '0',
        'h1_size' => '30',
        'h2_size' => '28',
        'h3_size' => '22',
        'h4_size' => '18',
        'h5_size' => '16',
        'paragraph_size' => '16',
        'span_size' => '16',
        'link_size' => '16',
        'link_color' => '#7b2629',
        'link_underline' => '1',
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_auth' => '1',
        'smtp_username' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => 'Kampachi',
        'language_provider' => 'none',
        'auto_translate' => '0',
        'languages' => ['en'],
        'github_repository' => '',
        'github_branch' => 'main',
        'gtm_enabled' => '0',
        'gtm_container_id' => '',
    ];

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 50);
        add_action('admin_post_kc_master_save', [self::class, 'save']);
        add_action('admin_post_kc_master_test_email', [self::class, 'test_email']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('wp_enqueue_scripts', [self::class, 'frontend_css'], 999);
        add_action('phpmailer_init', [self::class, 'configure_mail'], 20);
        add_filter('upload_mimes', [self::class, 'allow_ttf_for_admins']);
        add_action('wp_head', [self::class, 'render_gtm_head'], 1);
        add_action('wp_body_open', [self::class, 'render_gtm_body'], 1);
    }

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public static function get(string $key): mixed
    {
        $values = self::settings();

        return $values[$key] ?? null;
    }

    /** @return array<string, string> */
    public static function languages(): array
    {
        $enabled = self::get('languages');
        $enabled = is_array($enabled) ? $enabled : ['en'];

        return array_intersect_key(self::LANGUAGES, array_fill_keys($enabled, true));
    }

    public static function menu(): void
    {
        add_menu_page(
            'Kampachi Master Settings',
            'Master Settings',
            'manage_options',
            'kampachi-master-settings',
            [self::class, 'render'],
            'dashicons-admin-generic',
            19
        );
    }

    public static function admin_assets(string $hook): void
    {
        if (! str_contains($hook, 'kampachi-master-settings')) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('kc-master-admin', plugins_url('../assets/master-settings.css', __FILE__), [], '3.1.0');
        wp_enqueue_script('kc-master-admin', plugins_url('../assets/master-settings.js', __FILE__), ['jquery', 'wp-color-picker'], '3.1.0', true);
    }

    /** @param array<string, string> $mimes */
    public static function allow_ttf_for_admins(array $mimes): array
    {
        if (is_admin() && current_user_can('manage_options') && current_user_can('upload_files')) {
            $mimes['ttf'] = 'font/ttf';
        }

        return $mimes;
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }

        $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'menu';
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'menu';
        $values = self::settings();
        echo '<div class="wrap kc-master"><h1>Kampachi Master Settings</h1>';
        echo '<p>One source for site-wide navigation, typography, email, languages, GTM tracking and protected integration configuration. Existing content remains editable in its current modules.</p>';

        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Master settings saved successfully.</p></div>';
        }
        if (isset($_GET['notice']) && is_string($_GET['notice'])) {
            $notice = sanitize_key(wp_unslash($_GET['notice']));
            $messages = [
                'sent' => 'SMTP test email accepted by WordPress. Check the recipient inbox.',
                'failed' => 'Test email was not sent. Verify SMTP host, credentials and server logs.',
                'config_saved' => 'Managed wp-config.php values were updated. New constants take effect on subsequent requests.',
                'unchanged' => 'No config changes were submitted.',
                'invalid' => 'Invalid value provided. No configuration changes were made.',
                'error' => 'Protected configuration update failed. Check the opt-in, file permissions and existing duplicate constants. No changes were applied.',
            ];
            if (isset($messages[$notice])) {
                $positive = in_array($notice, ['sent', 'config_saved', 'unchanged'], true);
                echo '<div class="notice notice-' . ($positive ? 'success' : 'error') . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
            }
        }

        echo '<nav class="nav-tab-wrapper" aria-label="Master Settings tabs">';
        foreach (self::TABS as $slug => $title) {
            $url = add_query_arg(['page' => 'kampachi-master-settings', 'tab' => $slug], admin_url('admin.php'));
            echo '<a class="nav-tab ' . ($slug === $tab ? 'nav-tab-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
        }
        echo '</nav>';

        if ($tab === 'github') {
            self::github_tab($values);
        }

        if ($tab === 'config') {
            \Kampachi\CMS\WPConfigManager::render();
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kc_master_save">';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        wp_nonce_field('kc_master_' . $tab, 'kc_master_nonce');
        echo '<div class="kc-master-panel">';

        match ($tab) {
            'menu' => self::menu_fields($values),
            'fonts' => self::font_fields($values),
            'smtp' => self::smtp_fields($values),
            'languages' => self::language_fields($values),
            'github' => self::github_fields($values),
            'tracking' => self::tracking_fields($values),
            'config' => null,
        };

        echo '</div>';
        submit_button('Save ' . self::TABS[$tab]);
        echo '</form>';

        if ($tab === 'smtp') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="kc_master_test_email">';
            wp_nonce_field('kc_master_test_email');
            submit_button('Send test email to site admin', 'secondary');
            echo '</form>';
        }
        echo '</div>';
    }

    /** @param array<string, mixed> $values */
    private static function menu_fields(array $values): void
    {
        echo '<h2>Header and Navigation</h2><p>Applies to the theme header before and after scrolling, on desktop and mobile.</p>';
        echo '<table class="form-table"><tbody>';
        self::number_field($values, 'menu_gap', 'Gap between menu items (px)', 0, 80);
        self::font_select($values, 'menu_font', 'Menu font family');
        self::font_upload($values, 'menu_font_id', 'Menu custom TTF');
        self::number_field($values, 'menu_font_size', 'Menu font size (px)', 8, 40);
        self::color_field($values, 'menu_color', 'Menu text colour');
        echo '</tbody></table>';

        foreach (['before' => 'Before Scroll', 'after' => 'After Scroll'] as $prefix => $title) {
            echo '<h2>' . esc_html($title) . '</h2><table class="form-table"><tbody>';
            self::select_field($values, $prefix . '_mode', 'Header appearance', [
                'transparent' => 'Fully transparent',
                'semi' => 'Semi-transparent colour',
                'blur' => 'Frosted glass / blur',
                'solid' => 'Solid colour',
            ]);
            self::color_field($values, $prefix . '_color', 'Background colour / HEX code');
            self::number_field($values, $prefix . '_opacity', 'Background opacity (%)', 0, 100);
            self::number_field($values, $prefix . '_blur', 'Blur strength (px)', 0, 35);
            echo '</tbody></table>';
        }
        echo '<p class="description">Colour fields accept HEX values such as #000000 or #7B2629. Transparent mode ignores the colour; solid mode is fully opaque.</p>';
    }

    /** @param array<string, mixed> $values */
    private static function font_fields(array $values): void
    {
        echo '<h2>Global Typography</h2><p>Font choices apply across the active Kampachi native theme, including CMS-driven pages. All sizes are desktop values in pixels, with mobile limits applied automatically.</p><table class="form-table"><tbody>';
        self::font_select($values, 'font_family', 'Website font family');
        self::font_upload($values, 'font_id', 'Website custom TTF');
        foreach (['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'paragraph' => 'Paragraph', 'span' => 'Text span', 'link' => 'Hyperlink'] as $key => $label) {
            self::number_field($values, $key . '_size', $label . ' size (px)', 8, 100);
        }
        self::color_field($values, 'link_color', 'Hyperlink text and underline colour');
        self::checkbox_field($values, 'link_underline', 'Underline hyperlinks');
        echo '</tbody></table>';
    }

    /** @param array<string, mixed> $values */
    private static function smtp_fields(array $values): void
    {
        echo '<h2>WordPress Outgoing Mail</h2><p>SMTP applies to wp_mail(), including Contact enquiries and other plugin emails. For security, the password is NOT stored in the WordPress database.</p><table class="form-table"><tbody>';
        self::checkbox_field($values, 'smtp_enabled', 'Use SMTP');
        self::text_field($values, 'smtp_host', 'SMTP server / host');
        self::number_field($values, 'smtp_port', 'Port', 1, 65535);
        self::select_field($values, 'smtp_encryption', 'Encryption', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL / SMTPS (465)', 'none' => 'None (not recommended)']);
        self::checkbox_field($values, 'smtp_auth', 'SMTP authentication');
        self::text_field($values, 'smtp_username', 'SMTP username');
        self::text_field($values, 'smtp_from_email', 'From email', 'email');
        self::text_field($values, 'smtp_from_name', 'From name');
        echo '<tr><th>SMTP password</th><td><code>define(\'KC_SMTP_PASSWORD\', \'your-password\');</code><p class="description">Add to wp-config.php on your server. Current state: <strong>' . (defined('KC_SMTP_PASSWORD') && (string) constant('KC_SMTP_PASSWORD') !== '' ? 'configured' : 'not configured') . '</strong>. Do not enter secrets into source control.</p></td></tr>';
        echo '</tbody></table>';
        echo '<p class="description">To change the SMTP password from this plugin, open Master Settings → WP Config and enable its protected editor first.</p>';
    }

    /** @param array<string, mixed> $values */
    private static function language_fields(array $values): void
    {
        echo '<h2>Website Languages</h2><p>Choose the language API and enabled languages. English remains the master. The language selector only links to translations that exist; it will not show a fake language switch for untranslated pages.</p><table class="form-table"><tbody>';
        self::select_field($values, 'language_provider', 'Translation API', [
            'none' => 'None / manual translation',
            'azure' => 'Microsoft Azure Translator',
            'deepl' => 'DeepL API',
        ]);
        self::checkbox_field($values, 'auto_translate', 'Automatically create translations for new English content');
        echo '<tr><th scope="row">Available languages</th><td><div class="kc-master-checkgrid">';
        $selected = is_array($values['languages']) ? $values['languages'] : ['en'];
        foreach (self::LANGUAGES as $code => $label) {
            $checked = in_array($code, $selected, true) || $code === 'en';
            echo '<label><input type="checkbox" name="values[languages][]" value="' . esc_attr($code) . '" ' . checked($checked, true, false) . ($code === 'en' ? ' disabled' : '') . '> ' . esc_html($label) . ' (' . esc_html($code) . ')</label>';
        }
        echo '</div><p class="description">English is always enabled. Create language-specific content before adding public language links.</p></td></tr>';
        echo '<tr><th>Translator credentials</th><td><p>Azure: <code>KC_AZURE_TRANSLATOR_KEY</code>, <code>KC_AZURE_TRANSLATOR_REGION</code>. DeepL: <code>KC_DEEPL_AUTH_KEY</code>. Define secrets in wp-config.php.</p><p class="description">API selection is stored here for translation integrations. Automatic translation creates separate posts when English drafts are saved; previously created translations keep their independently edited text. Verify translations before publishing. Existing pages are not retroactively translated.</p></td></tr>';
        echo '</tbody></table>';
        echo '<p class="description">Translation credentials can also be maintained from the protected WP Config tab.</p>';
    }

    /** @param array<string, mixed> $values */
    private static function github_fields(array $values): void
    {
        echo '<h2>GitHub Source Configuration</h2><p>Connect a GitHub repository to view its latest commits. Source-code updates require an explicit administrator action and are disabled by default.</p><table class="form-table"><tbody>';
        self::text_field($values, 'github_repository', 'GitHub owner/repository (e.g. my-org/kampachi)');
        self::text_field($values, 'github_branch', 'Branch (e.g. main)');
        echo '<tr><th>Personal access token</th><td><code>define(\'KC_GITHUB_TOKEN\', \'github-token\');</code><p class="description">Store the token in wp-config.php, not in the theme or database. Use the minimum repository read permission.</p></td></tr>';
        echo '<tr><th>Allow site-file sync</th><td><code>define(\'KC_ALLOW_GITHUB_SYNC\', true);</code><p class="description">Off by default. Only files inside the Kampachi plugin and native theme can be updated. Never enable without GitHub branch protection and backups.</p></td></tr>';
        echo '</tbody></table>';
    }

    /** @param array<string, mixed> $values */
    private static function tracking_fields(array $values): void
    {
        echo '<h2>Google Tag Manager</h2>';
        echo '<p>Configure one container for the complete public website. GTM loads automatically on all Kampachi WordPress pages through the theme hooks. No theme PHP or wp-config.php modifications are needed.</p>';
        echo '<table class="form-table"><tbody>';
        self::checkbox_field($values, 'gtm_enabled', 'Enable Google Tag Manager');
        self::text_field($values, 'gtm_container_id', 'GTM Container ID (e.g. GTM-ABC1234)');
        echo '<tr><th scope="row">Placement</th><td>';
        echo '<p><strong>Head:</strong> The official GTM JavaScript snippet is inserted near the start of <code>&lt;head&gt;</code> by <code>wp_head</code>.</p>';
        echo '<p><strong>Body:</strong> The official GTM no-script iframe is inserted immediately after <code>&lt;body&gt;</code> by <code>wp_body_open</code>.</p>';
        echo '<p class="description">Do not also paste GTM into the theme header, another plugin or Google Site Kit. If you already have a container installed, remove that duplicate first. Tags managed inside GTM may require consent controls under your privacy policy.</p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        $id = self::validated_gtm_id($values);
        if ($id !== '') {
            echo '<p><strong>Validated container:</strong> <code>' . esc_html($id) . '</code></p>';
        }
    }

    /** @param array<string, mixed> $settings */
    private static function validated_gtm_id(array $settings): string
    {
        if (($settings['gtm_enabled'] ?? '') !== '1') {
            return '';
        }

        $id = strtoupper(trim((string) ($settings['gtm_container_id'] ?? '')));

        return preg_match('/^GTM-[A-Z0-9]{4,20}$/', $id) ? $id : '';
    }

    /**
     * GTM container JavaScript in the site's HTML head.
     */
    public static function render_gtm_head(): void
    {
        $id = self::validated_gtm_id(self::settings());
        if ($id === '') {
            return;
        }

        echo "<!-- Google Tag Manager: Kampachi Master Settings -->\n";
        echo '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer",' . wp_json_encode($id) . ');</script>' . "\n";
    }

    /**
     * GTM no-script fallback at the beginning of the site's HTML body.
     */
    public static function render_gtm_body(): void
    {
        $id = self::validated_gtm_id(self::settings());
        if ($id === '') {
            return;
        }

        echo '<!-- Google Tag Manager (noscript): Kampachi Master Settings -->' . "\n";
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($id) . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>' . "\n";
    }

    /** @param array<string, mixed> $values */
    private static function text_field(array $values, string $key, string $label, string $type = 'text'): void
    {
        echo '<tr><th><label for="kc-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" type="' . esc_attr($type) . '" id="kc-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']" value="' . esc_attr((string) ($values[$key] ?? '')) . '"></td></tr>';
    }

    /** @param array<string, mixed> $values */
    private static function number_field(array $values, string $key, string $label, int $min, int $max): void
    {
        echo '<tr><th><label for="kc-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input type="number" id="kc-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" value="' . esc_attr((string) ($values[$key] ?? '')) . '"></td></tr>';
    }

    /** @param array<string, mixed> $values */
    private static function checkbox_field(array $values, string $key, string $label): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><label><input type="checkbox" name="values[' . esc_attr($key) . ']" value="1" ' . checked((string) ($values[$key] ?? '') === '1', true, false) . '> Enable</label></td></tr>';
    }

    /** @param array<string, mixed> $values */
    private static function select_field(array $values, string $key, string $label, array $choices): void
    {
        echo '<tr><th><label for="kc-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><select id="kc-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']">';
        foreach ($choices as $value => $title) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected((string) ($values[$key] ?? ''), (string) $value, false) . '>' . esc_html($title) . '</option>';
        }
        echo '</select></td></tr>';
    }

    /** @param array<string, mixed> $values */
    private static function font_select(array $values, string $key, string $label): void
    {
        self::select_field($values, $key, $label, self::FONT_CHOICES);
    }

    /** @param array<string, mixed> $values */
    private static function color_field(array $values, string $key, string $label): void
    {
        echo '<tr><th><label for="kc-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input type="text" class="kc-color" id="kc-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']" value="' . esc_attr((string) ($values[$key] ?? '')) . '" data-default-color="#000000"></td></tr>';
    }

    /** @param array<string, mixed> $values */
    private static function font_upload(array $values, string $key, string $label): void
    {
        $id = absint($values[$key] ?? 0);
        $url = self::ttf_url($id);
        echo '<tr><th>' . esc_html($label) . '</th><td><input type="hidden" class="kc-font-id" name="values[' . esc_attr($key) . ']" value="' . esc_attr((string) $id) . '">';
        echo '<button class="button kc-pick-font" type="button">Choose / Upload TTF</button> <button class="button kc-clear-font" type="button">Clear</button> <span class="kc-font-label">' . esc_html($url !== '' ? basename((string) wp_parse_url($url, PHP_URL_PATH)) : 'No TTF selected') . '</span>';
        echo '<p class="description">Only genuine .ttf attachments are accepted. Use the font family dropdown above to select “Uploaded TTF font”.</p></td></tr>';
    }

    private static function ttf_url(int $id): string
    {
        if ($id <= 0 || get_post_type($id) !== 'attachment') {
            return '';
        }
        $file = get_attached_file($id);
        $url = wp_get_attachment_url($id);
        if (! is_string($file) || strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) !== 'ttf'
            || ! is_string($url) || (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://'))
            || ! is_readable($file)) {
            return '';
        }
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return '';
        }
        $signature = fread($handle, 4);
        fclose($handle);
        if (! in_array($signature, ["\x00\x01\x00\x00", 'true', 'typ1'], true)) {
            return '';
        }

        return $url;
    }

    public static function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }
        $tab = isset($_POST['tab']) && is_string($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : '';
        if (! isset(self::TABS[$tab])) {
            wp_die('Invalid settings tab.', '', ['response' => 400]);
        }
        check_admin_referer('kc_master_' . $tab, 'kc_master_nonce');
        $raw = isset($_POST['values']) && is_array($_POST['values']) ? wp_unslash($_POST['values']) : [];
        $all = self::settings();
        $fields = match ($tab) {
            'menu' => ['menu_gap', 'menu_font', 'menu_font_id', 'menu_font_size', 'menu_color', 'before_mode', 'before_color', 'before_opacity', 'before_blur', 'after_mode', 'after_color', 'after_opacity', 'after_blur'],
            'fonts' => ['font_family', 'font_id', 'h1_size', 'h2_size', 'h3_size', 'h4_size', 'h5_size', 'paragraph_size', 'span_size', 'link_size', 'link_color', 'link_underline'],
            'smtp' => ['smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_auth', 'smtp_username', 'smtp_from_email', 'smtp_from_name'],
            'languages' => ['language_provider', 'languages', 'auto_translate'],
            'github' => ['github_repository', 'github_branch'],
            'tracking' => ['gtm_enabled', 'gtm_container_id'],
            'config' => [],
        };
        $numbers = ['menu_gap' => [0, 80], 'menu_font_size' => [8, 40], 'before_opacity' => [0, 100], 'after_opacity' => [0, 100], 'before_blur' => [0, 35], 'after_blur' => [0, 35], 'smtp_port' => [1, 65535]];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'paragraph', 'span', 'link'] as $part) {
            $numbers[$part . '_size'] = [8, 100];
        }
        foreach ($fields as $key) {
            $value = $raw[$key] ?? null;
            if ($key === 'languages') {
                $requested = is_array($value) ? array_filter($value, 'is_string') : [];
                $all[$key] = array_values(array_unique(array_merge(['en'], array_intersect(array_keys(self::LANGUAGES), $requested))));
                continue;
            }
            if ($key === 'link_underline' || $key === 'smtp_enabled' || $key === 'smtp_auth' || $key === 'auto_translate' || $key === 'gtm_enabled') {
                $all[$key] = $value === '1' ? '1' : '0';
                continue;
            }
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if (isset($numbers[$key])) {
                [$min, $max] = $numbers[$key];
                $all[$key] = (string) max($min, min($max, (int) $value));
            } elseif (str_ends_with($key, '_color')) {
                $all[$key] = sanitize_hex_color($value) ?: self::DEFAULTS[$key];
            } elseif (in_array($key, ['font_family', 'menu_font'], true)) {
                $all[$key] = isset(self::FONT_CHOICES[$value]) ? $value : self::DEFAULTS[$key];
            } elseif (in_array($key, ['font_id', 'menu_font_id'], true)) {
                $all[$key] = self::ttf_url(absint($value)) !== '' ? (string) absint($value) : '0';
            } elseif (str_ends_with($key, '_mode')) {
                $all[$key] = in_array($value, ['transparent', 'semi', 'blur', 'solid'], true) ? $value : 'solid';
            } elseif ($key === 'smtp_encryption') {
                $all[$key] = in_array($value, ['tls', 'ssl', 'none'], true) ? $value : 'tls';
            } elseif ($key === 'language_provider') {
                $all[$key] = in_array($value, ['none', 'azure', 'deepl'], true) ? $value : 'none';
            } elseif ($key === 'smtp_from_email') {
                $all[$key] = sanitize_email($value);
            } elseif ($key === 'smtp_host') {
                $all[$key] = preg_match('/^[a-zA-Z0-9.-]{0,255}$/', $value) ? $value : '';
            } elseif ($key === 'gtm_container_id') {
                $value = strtoupper($value);
                $all[$key] = preg_match('/^GTM-[A-Z0-9]{4,20}$/', $value) ? $value : '';
            } elseif ($key === 'github_repository') {
                $all[$key] = preg_match('~^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$~', $value) ? $value : '';
            } elseif ($key === 'github_branch') {
                $all[$key] = preg_match('~^[a-zA-Z0-9_.\/-]{1,120}$~', $value) && ! str_contains($value, '..') && ! str_starts_with($value, '/') ? $value : 'main';
            } else {
                $all[$key] = sanitize_text_field($value);
            }
        }
        update_option(self::OPTION, $all, false);
        wp_safe_redirect(add_query_arg(['page' => 'kampachi-master-settings', 'tab' => $tab, 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function configure_mail(PHPMailer\PHPMailer\PHPMailer $mailer): void
    {
        $s = self::settings();
        if ($s['smtp_enabled'] !== '1' || $s['smtp_host'] === '') {
            return;
        }
        $mailer->isSMTP();
        $mailer->Host = (string) $s['smtp_host'];
        $mailer->Port = (int) $s['smtp_port'];
        $mailer->SMTPAutoTLS = $s['smtp_encryption'] === 'tls';
        $mailer->SMTPSecure = $s['smtp_encryption'] === 'none' ? '' : (string) $s['smtp_encryption'];
        $mailer->SMTPAuth = $s['smtp_auth'] === '1';
        $mailer->Username = (string) $s['smtp_username'];
        $mailer->Password = defined('KC_SMTP_PASSWORD') ? (string) constant('KC_SMTP_PASSWORD') : '';
        if (is_email($s['smtp_from_email'])) {
            $mailer->setFrom((string) $s['smtp_from_email'], (string) $s['smtp_from_name'], false);
        }
    }

    public static function test_email(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }
        check_admin_referer('kc_master_test_email');
        $sent = wp_mail((string) get_option('admin_email'), 'Kampachi SMTP test', 'Test email sent from Kampachi Master Settings.');
        wp_safe_redirect(add_query_arg(['page' => 'kampachi-master-settings', 'tab' => 'smtp', 'notice' => $sent ? 'sent' : 'failed'], admin_url('admin.php')));
        exit;
    }

    private static function font_css(string $name, int $attachmentId, string $alias): array
    {
        $fallback = 'Georgia, serif';
        if ($name === 'custom') {
            $url = self::ttf_url($attachmentId);
            if ($url === '') {
                return [$fallback, ''];
            }
            return ['"' . $alias . '", Georgia, serif', '@font-face{font-family:"' . $alias . '";src:url("' . esc_url_raw($url) . '") format("truetype");font-display:swap;}'];
        }
        $allowed = array_keys(self::FONT_CHOICES);
        $safe = in_array($name, $allowed, true) ? $name : 'Libre Baskerville';

        return ['"' . $safe . '", Georgia, serif', ''];
    }

    private static function header_background(array $s, string $prefix): array
    {
        $mode = (string) $s[$prefix . '_mode'];
        $hex = sanitize_hex_color((string) $s[$prefix . '_color']) ?: '#000000';
        $opacity = $mode === 'transparent' ? 0 : ($mode === 'solid' ? 1 : (int) $s[$prefix . '_opacity'] / 100);
        $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $background = sprintf('rgba(%d,%d,%d,%.2f)', $rgb[0], $rgb[1], $rgb[2], $opacity);
        $blur = $mode === 'blur' ? max(0, min(35, (int) $s[$prefix . '_blur'])) : 0;

        return [$background, $blur];
    }

    public static function frontend_css(): void
    {
        if (! wp_get_theme()->exists() || get_template() !== 'kampachi-native') {
            return;
        }
        $s = self::settings();
        [$font, $fontFace] = self::font_css((string) $s['font_family'], absint($s['font_id']), 'KampachiCustomFont');
        [$menuFont, $menuFontFace] = self::font_css((string) $s['menu_font'], absint($s['menu_font_id']), 'KampachiCustomMenuFont');
        [$before, $beforeBlur] = self::header_background($s, 'before');
        [$after, $afterBlur] = self::header_background($s, 'after');
        $sizes = [];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'paragraph', 'span', 'link'] as $part) {
            $sizes[$part] = (int) $s[$part . '_size'];
        }
        $menuGap = (int) $s['menu_gap'];
        $menuSize = (int) $s['menu_font_size'];
        $menuColor = sanitize_hex_color((string) $s['menu_color']) ?: '#f4f0eb';
        $linkColor = sanitize_hex_color((string) $s['link_color']) ?: '#7b2629';
        $underline = $s['link_underline'] === '1' ? 'underline' : 'none';
        $css = $fontFace . $menuFontFace . ':root{' .
            '--kampachi-font-family:' . $font . ';--kampachi-font-h1:' . $sizes['h1'] . 'px;--kampachi-font-h2:' . $sizes['h2'] . 'px;' .
            '--kampachi-font-h3:' . $sizes['h3'] . 'px;--kampachi-font-h4:' . $sizes['h4'] . 'px;--kampachi-font-h5:' . $sizes['h5'] . 'px;' .
            '--kampachi-font-paragraph:' . $sizes['paragraph'] . 'px;--kampachi-font-body:' . $sizes['paragraph'] . 'px;' .
            '--kc-menu-gap:' . $menuGap . 'px;--kc-menu-font:' . $menuFont . ';--kc-menu-size:' . $menuSize . 'px;--kc-menu-color:' . $menuColor . ';}' .
            'html body,html body *:not(.dashicons):not(.ab-icon):not(.fa):not(.fas):not(.far):not(.fab):not(svg):not(svg *){font-family:var(--kampachi-font-family)!important;}' .
            'html body h1{font-size:var(--kampachi-font-h1)!important;}html body h2{font-size:var(--kampachi-font-h2)!important;}' .
            'html body h3{font-size:var(--kampachi-font-h3)!important;}html body h4{font-size:var(--kampachi-font-h4)!important;}' .
            'html body h5{font-size:var(--kampachi-font-h5)!important;}html body p{font-size:var(--kampachi-font-paragraph)!important;}' .
            'html body main span:not([class]),html body .kampachi-editor-content span:not([class]){font-size:' . $sizes['span'] . 'px!important;}' .
            'html body main a:not(.button):not(.btn):not([class]),html body .kampachi-editor-content a{font-size:' . $sizes['link'] . 'px!important;color:' . $linkColor . '!important;text-decoration:' . $underline . '!important;text-decoration-color:' . $linkColor . '!important;}' .
            '.site-header,.site-header:not(.is-scrolled){background:' . $before . '!important;backdrop-filter:blur(' . $beforeBlur . 'px)!important;-webkit-backdrop-filter:blur(' . $beforeBlur . 'px)!important;}' .
            '.site-header.is-scrolled{background:' . $after . '!important;backdrop-filter:blur(' . $afterBlur . 'px)!important;-webkit-backdrop-filter:blur(' . $afterBlur . 'px)!important;}' .
            '.menu-overlay .nav-list{gap:var(--kc-menu-gap)!important;}' .
            'body.kc-has-translations .site-header .language-wrap{display:block!important;margin-right:12px;}' .
            'body.kc-has-translations .language-button{display:flex;align-items:center;gap:4px;border:0;background:transparent;color:white;cursor:pointer;padding:10px;font-size:13px;}' .
            'body.kc-has-translations .language-popover{background:#fff;box-shadow:0 10px 28px rgba(0,0,0,.25);z-index:90;}' .
            'body.kc-has-translations .language-popover a{display:block;padding:9px 12px;color:#222;text-decoration:none;white-space:nowrap;font-size:13px;}' .
            '.menu-overlay .nav-link,.menu-overlay .nav-list>li>a,.menu-overlay .nav-dropdown-toggle{font-family:var(--kc-menu-font)!important;font-size:var(--kc-menu-size)!important;color:var(--kc-menu-color)!important;}' .
            '.menu-overlay.is-scrolled .nav-link,.menu-overlay.is-scrolled .nav-list>li>a,.menu-overlay.is-scrolled .nav-dropdown-toggle{color:var(--kc-menu-color)!important;}' .
            '@media(max-width:1199px){.menu-overlay .nav-list{gap:var(--kc-menu-gap)!important;}}' .
            '@media(max-width:600px){:root{--kampachi-font-h1:' . min(30, $sizes['h1']) . 'px;--kampachi-font-h2:' . min(26, $sizes['h2']) . 'px;--kampachi-font-h3:' . min(22, $sizes['h3']) . 'px;}}';

        wp_register_style('kc-master-design', false, [], '3.0.0');
        wp_enqueue_style('kc-master-design');
        wp_add_inline_style('kc-master-design', $css);
    }

    /** @param array<string, mixed> $values */
    private static function github_tab(array $values): void
    {
        if (! class_exists('Kampachi_GitHub_Sync')) {
            return;
        }
        Kampachi_GitHub_Sync::render_status((string) $values['github_repository'], (string) $values['github_branch']);
    }
}
