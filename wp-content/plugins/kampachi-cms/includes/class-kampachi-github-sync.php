<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Explicit, locked, allowlisted GitHub one-commit deployment for Kampachi code.
 * A repository must use wp-content/plugins/kampachi-cms and/or
 * wp-content/themes/kampachi-native paths relative to its root.
 */
final class Kampachi_GitHub_Sync
{
    private const MAX_FILES = 25;
    private const MAX_FILE_BYTES = 1048576;

    public static function boot(): void
    {
        add_action('admin_post_kc_github_sync', [self::class, 'sync']);
    }

    private static function token(): string
    {
        return defined('KC_GITHUB_TOKEN') ? trim((string) constant('KC_GITHUB_TOKEN')) : '';
    }

    /** @return array<string, mixed>|WP_Error */
    private static function api(string $path): array|WP_Error
    {
        $url = 'https://api.github.com/' . ltrim($path, '/');
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'];
        if (self::token() !== '') {
            $headers['Authorization'] = 'Bearer ' . self::token();
        }
        $response = wp_safe_remote_get($url, [
            'headers' => $headers,
            'timeout' => 12,
            'redirection' => 0,
            'limit_response_size' => 2200000,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return new WP_Error('kc_github_api', sprintf('GitHub API returned HTTP %d. Verify repository access and credentials.', $status));
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data)) {
            return new WP_Error('kc_github_json', 'GitHub returned an unreadable response.');
        }

        return $data;
    }

    /** @return array{repository: string, branch: string}|WP_Error */
    private static function repository(): array|WP_Error
    {
        $repo = (string) Kampachi_Master_Settings::get('github_repository');
        $branch = (string) Kampachi_Master_Settings::get('github_branch');
        if (! preg_match('~^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$~', $repo)
            || ! preg_match('~^[a-zA-Z0-9_.\/-]{1,120}$~', $branch)
            || str_contains($branch, '..') || str_starts_with($branch, '/')) {
            return new WP_Error('kc_github_config', 'Save a valid GitHub owner/repository and branch first.');
        }

        return ['repository' => $repo, 'branch' => $branch];
    }

    public static function render_status(string $repository, string $branch): void
    {
        echo '<div class="kc-master-github"><h2>Connection &amp; Recent Changes</h2>';
        $flash = get_transient('kc_github_result_' . get_current_user_id());
        if (is_string($flash) && $flash !== '') {
            echo '<div class="notice notice-info inline"><p>' . esc_html($flash) . '</p></div>';
            delete_transient('kc_github_result_' . get_current_user_id());
        }
        if ($repository === '') {
            echo '<p>Repository not configured yet. Save its name and branch below to connect.</p></div>';
            return;
        }

        echo '<p><strong>Repository:</strong> ' . esc_html($repository) . ' · <strong>Branch:</strong> ' . esc_html($branch) . '</p>';
        if (self::token() !== '') {
            $user = self::api('user');
            if (! is_wp_error($user) && ! empty($user['login']) && is_string($user['login'])) {
                echo '<p><strong>Connected GitHub account:</strong> ' . esc_html($user['login']) . '</p>';
            } elseif (is_wp_error($user)) {
                echo '<p class="kc-warning">Token access could not be verified: ' . esc_html($user->get_error_message()) . '</p>';
            }
        } else {
            echo '<p>Using GitHub public API (no account connected). Add KC_GITHUB_TOKEN for private repositories and account identification.</p>';
        }
        $commits = self::api('repos/' . $repository . '/commits?sha=' . rawurlencode($branch) . '&per_page=5');
        if (is_wp_error($commits)) {
            echo '<p class="kc-warning">' . esc_html($commits->get_error_message()) . '</p></div>';
            return;
        }
        echo '<h3>Recent GitHub changes</h3><ul>';
        foreach ($commits as $commit) {
            if (! is_array($commit)) {
                continue;
            }
            $message = isset($commit['commit']['message']) && is_string($commit['commit']['message'])
                ? strtok($commit['commit']['message'], "\n") : 'Commit';
            $sha = is_string($commit['sha'] ?? null) ? substr($commit['sha'], 0, 8) : '';
            echo '<li><code>' . esc_html($sha) . '</code> ' . esc_html((string) $message) . '</li>';
        }
        echo '</ul>';
        $log = get_option('kc_github_last_sync', []);
        if (is_array($log) && ! empty($log['sha'])) {
            echo '<p><strong>Last sync:</strong> ' . esc_html((string) ($log['date'] ?? '')) . ' · <code>' . esc_html((string) $log['sha']) . '</code> (' . absint($log['count'] ?? 0) . ' file(s))</p>';
        }
        if (! defined('KC_ALLOW_GITHUB_SYNC') || constant('KC_ALLOW_GITHUB_SYNC') !== true) {
            echo '<p class="kc-warning">GitHub file sync is disabled. Set KC_ALLOW_GITHUB_SYNC to true in wp-config.php after reviewing the deployment policy.</p>';
        } elseif (! current_user_can('update_plugins') || ! current_user_can('update_themes')) {
            echo '<p class="kc-warning">Your account needs both plugin and theme update permissions to sync code.</p>';
        } else {
            echo '<p>Sync imports up to 25 modified or added .php, .css, .js, .json, .html or .txt files since the previous sync (or the latest commit on first sync). It never deletes files or touches uploads, vendor plugins or WordPress core. Existing files are restored if this operation fails midway.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="kc_github_sync">';
            wp_nonce_field('kc_github_sync');
            submit_button('Sync latest commit files now', 'secondary', 'submit', false, ['onclick' => "return confirm('Update application code from the latest GitHub commit? Take a site backup first.');"]);
            echo '</form>';
        }
        echo '<p class="description">The GitHub repository structure must contain wp-content/plugins/kampachi-cms and/or wp-content/themes/kampachi-native. Make production backups before code sync; this is not a substitute for staging and Git-based deployment.</p></div>';
    }

    private static function allowed_path(string $path): bool
    {
        return (bool) preg_match(
            '~^wp-content/(?:plugins/kampachi-cms|themes/kampachi-native)/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:php|css|js|json|html|txt)$~',
            $path
        );
    }

    /** @return array{path: string, data: string}|WP_Error */
    private static function download(string $repository, string $path, string $sha): array|WP_Error
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $result = self::api('repos/' . $repository . '/contents/' . $encodedPath . '?ref=' . rawurlencode($sha));
        if (is_wp_error($result)) {
            return $result;
        }
        if (($result['type'] ?? '') !== 'file' || ($result['encoding'] ?? '') !== 'base64'
            || ! is_string($result['content'] ?? null) || (int) ($result['size'] ?? -1) > self::MAX_FILE_BYTES) {
            return new WP_Error('kc_github_contents', 'File is not a supported GitHub base64 source file or is too large: ' . $path);
        }
        $body = base64_decode(preg_replace('/\s+/', '', $result['content']), true);
        if (! is_string($body) || strlen($body) > self::MAX_FILE_BYTES) {
            return new WP_Error('kc_github_decode', 'Cannot decode GitHub file: ' . $path);
        }
        $blobSha = sha1('blob ' . strlen($body) . "\0" . $body);
        if (! is_string($result['sha'] ?? null) || ! hash_equals($result['sha'], $blobSha)) {
            return new WP_Error('kc_github_checksum', 'GitHub blob checksum failed for: ' . $path);
        }

        return ['path' => $path, 'data' => $body];
    }

    private static function local_path(string $path): string|WP_Error
    {
        if (! self::allowed_path($path)) {
            return new WP_Error('kc_github_path', 'Unapproved repository file path: ' . $path);
        }
        $allowedRoot = str_starts_with($path, 'wp-content/plugins/kampachi-cms/')
            ? realpath(WP_CONTENT_DIR . '/plugins/kampachi-cms')
            : realpath(WP_CONTENT_DIR . '/themes/kampachi-native');
        $relative = substr($path, strlen('wp-content/'));
        $target = WP_CONTENT_DIR . '/' . $relative;
        $directory = realpath(dirname($target));
        if (! is_string($allowedRoot) || ! is_string($directory)
            || ! str_starts_with($directory . '/', $allowedRoot . '/')
            || is_link($target)) {
            return new WP_Error('kc_github_local_path', 'Local directory must exist and must be inside wp-content: ' . $path);
        }

        return $target;
    }

    public static function sync(): void
    {
        if (! current_user_can('manage_options') || ! current_user_can('update_plugins') || ! current_user_can('update_themes')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }
        check_admin_referer('kc_github_sync');
        if (! defined('KC_ALLOW_GITHUB_SYNC') || constant('KC_ALLOW_GITHUB_SYNC') !== true) {
            wp_die('GitHub file updates are disabled in wp-config.php.', '', ['response' => 403]);
        }

        $config = self::repository();
        $message = '';
        $lock = 'kc_github_sync_lock';
        if (get_transient($lock)) {
            $message = 'Another GitHub sync is already running.';
        } elseif (is_wp_error($config)) {
            $message = $config->get_error_message();
        } else {
            set_transient($lock, '1', 2 * MINUTE_IN_SECONDS);
            try {
                $message = self::perform_sync($config['repository'], $config['branch']);
            } catch (Throwable $error) {
                $message = 'GitHub sync stopped: ' . $error->getMessage();
            } finally {
                delete_transient($lock);
            }
        }
        set_transient('kc_github_result_' . get_current_user_id(), $message, MINUTE_IN_SECONDS * 5);
        wp_safe_redirect(add_query_arg(['page' => 'kampachi-master-settings', 'tab' => 'github'], admin_url('admin.php')));
        exit;
    }

    private static function perform_sync(string $repository, string $branch): string
    {
        $commit = self::api('repos/' . $repository . '/commits/' . rawurlencode($branch));
        if (is_wp_error($commit)) {
            throw new RuntimeException($commit->get_error_message());
        }
        $sha = $commit['sha'] ?? null;
        if (! is_string($sha) || ! preg_match('/^[0-9a-f]{40}$/i', $sha)) {
            throw new RuntimeException('GitHub did not return a valid commit SHA.');
        }
        $log = get_option('kc_github_last_sync', []);
        $log = is_array($log) ? $log : [];
        $changed = $commit['files'] ?? [];
        if (($log['repository'] ?? '') === $repository && ($log['branch'] ?? '') === $branch
            && isset($log['sha']) && is_string($log['sha'])
            && preg_match('/^[0-9a-f]{40}$/i', $log['sha'])) {
            if (hash_equals($log['sha'], $sha)) {
                return 'Repository already synchronized to commit ' . substr($sha, 0, 8) . '.';
            }
            $comparison = self::api('repos/' . $repository . '/compare/' . $log['sha'] . '...' . $sha);
            if (is_wp_error($comparison)) {
                throw new RuntimeException('Cannot compare new commits with the last sync. Use a normal deployment to re-baseline.');
            }
            $changed = $comparison['files'] ?? [];
        }
        if (! is_array($changed)) {
            throw new RuntimeException('GitHub did not return a changed-file list.');
        }
        $paths = [];
        foreach ($changed as $item) {
            $path = is_array($item) && is_string($item['filename'] ?? null) ? $item['filename'] : '';
            if (self::allowed_path($path) && in_array($item['status'] ?? '', ['modified', 'added'], true)) {
                $paths[] = $path;
            }
        }
        if ($paths === []) {
            return 'Latest commit ' . substr($sha, 0, 8) . ' contains no eligible Kampachi code files to sync.';
        }
        if (count($paths) > self::MAX_FILES || count($changed) >= 300) {
            throw new RuntimeException('Too many changed files to sync safely in one operation. Deploy this commit through a normal release process.');
        }

        $staged = [];
        foreach ($paths as $path) {
            $target = self::local_path($path);
            if (is_wp_error($target)) {
                throw new RuntimeException($target->get_error_message());
            }
            $data = self::download($repository, $path, $sha);
            if (is_wp_error($data)) {
                throw new RuntimeException($data->get_error_message());
            }
            $staged[$target] = $data['data'];
        }
        $originals = [];
        $applied = [];
        try {
            foreach ($staged as $target => $body) {
                $current = is_file($target) ? file_get_contents($target) : null;
                if ($current === false) {
                    throw new RuntimeException('Failed reading original file ' . basename($target));
                }
                $originals[$target] = $current;
                $tmp = tempnam(dirname($target), 'kc-sync-');
                if (! is_string($tmp)) {
                    throw new RuntimeException('Cannot create a temporary deployment file.');
                }
                try {
                    $mode = is_file($target) ? (fileperms($target) & 0777) : 0644;
                    if (file_put_contents($tmp, $body, LOCK_EX) !== strlen($body)
                        || ! chmod($tmp, $mode) || ! rename($tmp, $target)) {
                        throw new RuntimeException('Cannot replace ' . basename($target));
                    }
                } finally {
                    if (is_file($tmp)) {
                        unlink($tmp);
                    }
                }
                $applied[] = $target;
            }
        } catch (Throwable $error) {
            foreach (array_reverse($applied) as $target) {
                if ($originals[$target] === null) {
                    @unlink($target);
                } else {
                    @file_put_contents($target, $originals[$target], LOCK_EX);
                }
            }
            throw new RuntimeException('Failed applying changes. Attempted rollback. ' . $error->getMessage(), 0, $error);
        }
        update_option('kc_github_last_sync', [
            'sha' => $sha,
            'repository' => $repository,
            'branch' => $branch,
            'date' => current_time('mysql'),
            'count' => count($applied),
            'paths' => $paths,
        ], false);
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return sprintf('Synchronized %d allowed file(s) from %s at commit %s.', count($applied), $repository, substr($sha, 0, 8));
    }
}
