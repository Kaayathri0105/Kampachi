<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use RuntimeException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Updates allowlisted Kampachi constants in a dedicated wp-config.php block.
 * This is not a general purpose wp-config editor: it never displays credentials
 * or overwrites unrelated WordPress configuration.
 */
final class WPConfigManager
{
    private const START_MARKER = '// BEGIN KAMPACHI MANAGED CONFIG';

    private const END_MARKER = '// END KAMPACHI MANAGED CONFIG';

    private const MANAGED_KEYS = [
        'KC_SMTP_PASSWORD' => 'SMTP password',
        'KC_AZURE_TRANSLATOR_KEY' => 'Azure Translator API key',
        'KC_AZURE_TRANSLATOR_REGION' => 'Azure Translator region',
        'KC_DEEPL_AUTH_KEY' => 'DeepL API key',
        'KC_GITHUB_TOKEN' => 'GitHub personal access token',
    ];

    public static function boot(): void
    {
        add_action('admin_post_kc_master_config_save', [self::class, 'save']);
    }

    public static function isEnabled(): bool
    {
        return is_super_admin()
            && current_user_can('manage_options')
            && defined('KC_ALLOW_WP_CONFIG_EDITOR')
            && constant('KC_ALLOW_WP_CONFIG_EDITOR') === true
            && (! defined('DISALLOW_FILE_MODS') || constant('DISALLOW_FILE_MODS') !== true)
            && (! defined('DISALLOW_FILE_EDIT') || constant('DISALLOW_FILE_EDIT') !== true);
    }

    /**
     * Display credential status without ever rendering sensitive content.
     */
    public static function render(): void
    {
        if (! is_super_admin()) {
            echo '<p>Only WordPress super administrators can access the protected configuration editor.</p>';
            return;
        }

        echo '<h2>Protected WordPress Configuration</h2>';
        echo '<p>Manage only Kampachi integration credentials. This does not replace the full <code>wp-config.php</code>, WordPress database settings or authentication keys.</p>';
        echo '<p><strong>Before using this editor:</strong> make a complete website backup. Then add the following line manually to <code>wp-config.php</code>, above the WordPress bootstrap line:</p>';
        echo '<p><code>define(\'KC_ALLOW_WP_CONFIG_EDITOR\', true);</code></p>';
        echo '<p class="description">Only a WordPress super administrator can use this feature. It also respects <code>DISALLOW_FILE_MODS</code> and <code>DISALLOW_FILE_EDIT</code>. Turn the opt-in off again after making changes.</p>';

        $path = self::configPath();
        if ($path === null) {
            echo '<div class="notice notice-error inline"><p>Could not locate an ordinary wp-config.php file. Update the file using your host or deployment process.</p></div>';
            return;
        }

        $status = self::isEnabled() ? 'Enabled for this request' : 'Locked';
        $writable = is_writable($path) && is_writable(dirname($path));
        echo '<p><strong>Access:</strong> ' . esc_html($status) . ' &nbsp; <strong>Filesystem:</strong> ' . ($writable ? 'Writable' : 'Read-only') . '</p>';

        $last = get_option('kc_wp_config_last_update', []);
        if (is_array($last) && isset($last['at']) && is_string($last['at'])) {
            echo '<p class="description">Last managed update: ' . esc_html($last['at']) . ' (WordPress user ID ' . absint($last['user']) . ').</p>';
        }

        if (! self::isEnabled() || ! $writable) {
            echo '<p class="description">The editor will become available after the opt-in constant is enabled and the server permits editing. No file is changed while locked.</p>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="kc_master_config_save">';
        wp_nonce_field('kc_master_config_save');
        echo '<table class="form-table"><tbody>';

        foreach (self::MANAGED_KEYS as $name => $label) {
            $configured = defined($name) && (string) constant($name) !== '';
            $type = $name === 'KC_AZURE_TRANSLATOR_REGION' ? 'text' : 'password';
            echo '<tr><th scope="row"><label for="kc-config-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
            echo '<input class="regular-text" autocomplete="new-password" type="' . esc_attr($type) . '" id="kc-config-' . esc_attr($name) . '" name="credentials[' . esc_attr($name) . ']" value="" placeholder="Leave blank to keep unchanged">';
            echo '<p class="description">Current value: ' . ($configured ? 'configured' : 'not configured') . '. The existing value is never shown.</p>';
            echo '<label><input type="checkbox" name="remove_keys[]" value="' . esc_attr($name) . '"> Remove managed value</label>';
            echo '</td></tr>';
        }

        echo '<tr><th>Allow GitHub sync</th><td><select name="github_sync">';
        echo '<option value="keep">Keep current setting</option>';
        echo '<option value="off">Disabled (recommended)</option>';
        echo '<option value="on">Enabled (manual sync only)</option>';
        echo '</select><p class="description">Current state: ' . (defined('KC_ALLOW_GITHUB_SYNC') && constant('KC_ALLOW_GITHUB_SYNC') === true ? 'enabled' : 'disabled') . '</p></td></tr>';
        echo '</tbody></table>';
        submit_button('Update Managed wp-config Values');
        echo '</form>';
        echo '<p class="description">Updates are restricted to supported Kampachi constants. Simple existing <code>define()</code> entries are migrated to the managed block; dynamic definitions are not replaced. Before writing, the editor stores a guarded <code>.php</code> backup beside wp-config.php. Other WordPress settings remain untouched.</p>';
    }

    public static function save(): void
    {
        if (! self::isEnabled()) {
            wp_die('Protected configuration editing is not enabled.', '', ['response' => 403]);
        }

        check_admin_referer('kc_master_config_save');

        $credentials = isset($_POST['credentials']) && is_array($_POST['credentials'])
            ? wp_unslash($_POST['credentials'])
            : [];
        $requestedRemovals = isset($_POST['remove_keys']) && is_array($_POST['remove_keys'])
            ? wp_unslash($_POST['remove_keys'])
            : [];
        $removals = array_intersect(
            array_keys(self::MANAGED_KEYS),
            array_filter($requestedRemovals, 'is_string')
        );
        $updates = [];

        foreach (self::MANAGED_KEYS as $name => $label) {
            if (in_array($name, $removals, true)) {
                $updates[$name] = null;
                continue;
            }

            if (! isset($credentials[$name]) || ! is_string($credentials[$name])) {
                continue;
            }

            $value = $credentials[$name];
            if ($value === '') {
                continue;
            }

            if (strlen($value) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                self::redirect('invalid');
            }

            if ($name === 'KC_AZURE_TRANSLATOR_REGION' && ! preg_match('/^[a-z0-9-]{1,60}$/', $value)) {
                self::redirect('invalid');
            }

            $updates[$name] = $value;
        }

        $githubSync = isset($_POST['github_sync']) && is_string($_POST['github_sync'])
            ? sanitize_key(wp_unslash($_POST['github_sync']))
            : 'keep';

        if ($githubSync === 'on' || $githubSync === 'off') {
            $updates['KC_ALLOW_GITHUB_SYNC'] = $githubSync === 'on';
        }

        if ($updates === []) {
            self::redirect('unchanged');
        }

        try {
            self::updateFile($updates);
        } catch (RuntimeException $exception) {
            // Return a generic error so filesystem paths / secrets are never disclosed.
            error_log('Kampachi protected configuration update failed: ' . $exception->getCode());
            self::redirect('error');
        }

        update_option('kc_wp_config_last_update', [
            'at' => gmdate('Y-m-d H:i:s') . ' UTC',
            'user' => get_current_user_id(),
        ], false);
        self::redirect('config_saved');
    }

    /**
     * Update only allowlisted entries within a managed block.
     *
     * @param array<string, string|bool|null> $updates
     */
    private static function updateFile(array $updates): void
    {
        $path = self::configPath();
        if ($path === null || ! is_writable($path) || ! is_writable(dirname($path))) {
            throw new RuntimeException('WordPress configuration file is not writable.', 101);
        }

        $original = file_get_contents($path);
        if (! is_string($original) || strlen($original) > 3 * 1024 * 1024) {
            throw new RuntimeException('Invalid configuration size or content.', 102);
        }

        $backupSource = $original;
        [$unmanaged, $managed] = self::readManagedBlock($original);
        $allowedNames = array_merge(array_keys(self::MANAGED_KEYS), ['KC_ALLOW_GITHUB_SYNC']);

        foreach ($updates as $key => $value) {
            if (! in_array($key, $allowedNames, true)) {
                throw new RuntimeException('Unrecognized configuration key.', 103);
            }

            $outsideDefinition = '/\bdefine\s*\(\s*([\'\"])' . preg_quote($key, '/') . '\1\s*,/';
            if (preg_match($outsideDefinition, $unmanaged)) {
                $original = self::migrateSimpleDefinition($original, $key);
                [$unmanaged] = self::readManagedBlock($original);
            }

            if ($value === null) {
                unset($managed[$key]);
            } else {
                $managed[$key] = $value;
            }
        }

        $lines = [self::START_MARKER];
        foreach ($allowedNames as $key) {
            if (array_key_exists($key, $managed)) {
                $lines[] = 'define(' . var_export($key, true) . ', ' . var_export($managed[$key], true) . ');';
            }
        }
        $lines[] = self::END_MARKER;
        $block = implode("\n", $lines);

        if (str_contains($original, self::START_MARKER)) {
            $start = strpos($original, self::START_MARKER);
            $end = strpos($original, self::END_MARKER);
            if ($start === false || $end === false || $start >= $end) {
                throw new RuntimeException('Malformed managed configuration block.', 105);
            }
            $updated = substr($original, 0, $start)
                . $block
                . substr($original, $end + strlen(self::END_MARKER));
        } else {
            // Insert directly before the normal WordPress bootstrap, never after it.
            $bootstrap = '/^[ \t]*require(?:_once)?[ \t]*(?:\([ \t]*)?ABSPATH[ \t]*\.[ \t]*[\'\"]wp-settings\.php[\'\"][ \t]*\)?[ \t]*;/m';
            if (! preg_match($bootstrap, $original, $match, PREG_OFFSET_CAPTURE)) {
                throw new RuntimeException('Could not identify a safe insertion point.', 106);
            }
            $offset = $match[0][1];
            $updated = substr($original, 0, $offset) . $block . "\n\n" . substr($original, $offset);
        }

        if ($updated === $backupSource) {
            return;
        }

        $dir = dirname($path);
        $backup = $dir . '/wp-config-kc-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.php';
        $backupContent = "<?php exit; ?>\n" . $backupSource;
        if (file_put_contents($backup, $backupContent, LOCK_EX) !== strlen($backupContent)) {
            throw new RuntimeException('Could not write protected backup.', 107);
        }
        chmod($backup, 0600);

        $temp = tempnam($dir, '.kc-config-');
        if ($temp === false) {
            throw new RuntimeException('Could not create temporary file.', 108);
        }
        try {
            if (file_put_contents($temp, $updated, LOCK_EX) !== strlen($updated)) {
                throw new RuntimeException('Could not write managed configuration.', 109);
            }
            $mode = fileperms($path);
            if ($mode !== false) {
                chmod($temp, $mode & 0777);
            }
            if (! rename($temp, $path)) {
                throw new RuntimeException('Could not replace configuration atomically.', 110);
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    /**
     * Migrate a single top-level, literal define() statement. Complex expressions,
     * conditional configuration, and duplicate declarations are rejected.
     */
    private static function migrateSimpleDefinition(string $source, string $key): string
    {
        $pattern = <<<'REGEX'
~^[ \t]*define[ \t]*\([ \t]*(['"])__KEY__\1[ \t]*,[ \t]*(?:'(?:\\.|[^'\\\r\n])*'|"(?:\\.|[^"\\\r\n])*"|true|false)[ \t]*\)[ \t]*;[ \t]*(?://[^\r\n]*)?\r?\n?~m
REGEX;
        $pattern = str_replace('__KEY__', preg_quote($key, '~'), $pattern);

        if (preg_match_all($pattern, $source) !== 1) {
            throw new RuntimeException('Complex existing definition requires manual migration.', 104);
        }

        $updated = preg_replace($pattern, '', $source, 1);
        if (! is_string($updated) || $updated === $source) {
            throw new RuntimeException('Could not safely migrate existing definition.', 116);
        }

        return $updated;
    }

    /**
     * @return array{0: string, 1: array<string, string|bool>}
     */
    private static function readManagedBlock(string $source): array
    {
        $startCount = substr_count($source, self::START_MARKER);
        $endCount = substr_count($source, self::END_MARKER);
        if ($startCount !== $endCount || $startCount > 1) {
            throw new RuntimeException('Duplicate or unmatched managed block markers.', 111);
        }
        if ($startCount === 0) {
            return [$source, []];
        }

        $start = strpos($source, self::START_MARKER);
        $end = strpos($source, self::END_MARKER);
        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('Malformed managed block.', 112);
        }

        $inner = substr($source, $start + strlen(self::START_MARKER), $end - ($start + strlen(self::START_MARKER)));
        $outside = substr($source, 0, $start) . substr($source, $end + strlen(self::END_MARKER));
        $known = [];
        $allowed = array_merge(array_keys(self::MANAGED_KEYS), ['KC_ALLOW_GITHUB_SYNC']);

        // Only accept the exact statements previously generated by this editor.
        foreach (explode("\n", trim($inner)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^define\((\'[A-Z_]+\'),\s*(.+)\);$/', $line, $matches)) {
                throw new RuntimeException('The managed block was modified outside this editor.', 113);
            }
            $key = trim($matches[1], "'");
            if (! in_array($key, $allowed, true) || array_key_exists($key, $known)) {
                throw new RuntimeException('Unexpected managed block entry.', 114);
            }
            $encodedValue = $matches[2];
            if ($encodedValue === 'true' || $encodedValue === 'false') {
                $known[$key] = $encodedValue === 'true';
                continue;
            }
            // PHP var_export() of a string contains single-quoted escaped data.
            if (! preg_match('/^\'(?:\\\\.|[^\'\\\\])*\'$/', $encodedValue)) {
                throw new RuntimeException('Unsupported managed value.', 115);
            }
            $known[$key] = stripcslashes(substr($encodedValue, 1, -1));
        }

        return [$outside, $known];
    }

    private static function configPath(): ?string
    {
        $root = rtrim(ABSPATH, '/\\');
        $paths = [$root . '/wp-config.php'];
        $parent = dirname($root);
        if (! is_file($parent . '/wp-settings.php')) {
            $paths[] = $parent . '/wp-config.php';
        }
        foreach ($paths as $path) {
            if (is_file($path) && ! is_link($path) && realpath($path) === $path) {
                return $path;
            }
        }

        return null;
    }

    private static function redirect(string $notice): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => 'kampachi-master-settings',
            'tab' => 'config',
            'notice' => $notice,
        ], admin_url('admin.php')));
        exit;
    }
}
