<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class CareersLanding extends CollectionLanding
{
    public const APPLICATION_ACTION = 'kc_apply_vacancy';

    protected const KEY = 'careers';
    protected const TITLE = 'Careers Landing';
    protected const ITEMS_LABEL = 'vacancies';
    protected const ITEMS_TITLE = 'Vacancy Order';
    protected const POST_TYPE = 'kc_job';
    protected const PAGE_SLUG = 'careers';
    protected const PAGE_TITLE = 'Careers';
    protected const HAS_BODY_EDITOR = true;

    public static function boot(): void
    {
        parent::boot();
        add_action('admin_post_' . self::APPLICATION_ACTION, [self::class, 'submitApplication']);
        add_action('admin_post_nopriv_' . self::APPLICATION_ACTION, [self::class, 'submitApplication']);
    }

    public static function resumeLimit(): int
    {
        return min(5 * MB_IN_BYTES, wp_max_upload_size());
    }

    public static function submitApplication(): void
    {
        $jobId = absint(self::applicationField('vacancy'));
        $return = wp_get_referer() ?: home_url('/careers/');
        $return = remove_query_arg(['kc_application', 'kc_vacancy'], explode('#', $return, 2)[0]);
        $status = self::applicationStatus($jobId);
        $return = add_query_arg(['kc_application' => $status, 'kc_vacancy' => $jobId], $return);

        wp_safe_redirect($return . '#vacancy-' . $jobId, 303);
        exit;
    }

    private static function applicationStatus(int $jobId): string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || ! wp_verify_nonce(self::applicationField('kc_application_nonce'), self::APPLICATION_ACTION . '_' . $jobId)
        ) {
            return 'invalid';
        }

        $job = $jobId > 0 ? get_post($jobId) : null;
        if (! $job instanceof WP_Post || $job->post_type !== self::POST_TYPE || $job->post_status !== 'publish'
            || post_password_required($job)
        ) {
            return 'invalid';
        }

        if (self::applicationField('website') !== '') {
            return 'sent';
        }

        $name = sanitize_text_field(self::applicationField('name'));
        $email = self::applicationField('email');
        if ($name === '' || mb_strlen($name) > 120 || ! is_email($email) || strlen($email) > 254) {
            return 'invalid';
        }

        $resume = self::uploadedResume($_FILES['resume'] ?? null);
        if ($resume === '') {
            return 'resume';
        }

        $recipient = self::applicationRecipient($job);
        if ($recipient === '') {
            return 'unavailable';
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $rateKey = 'kc_application_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 5) {
            return 'limited';
        }
        set_transient($rateKey, $attempts + 1, HOUR_IN_SECONDS);

        $title = sanitize_text_field($job->post_title);
        $sent = wp_mail(
            $recipient,
            'Kampachi application: ' . $title,
            "Vacancy: {$title}\nName: {$name}\nEmail: {$email}",
            ['Reply-To: ' . $email],
            ['Resume.pdf' => $resume]
        );

        return $sent ? 'sent' : 'failed';
    }

    private static function applicationField(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim(wp_unslash($value)) : '';
    }

    private static function uploadedResume(mixed $file): string
    {
        if (! is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
            || ! is_string($file['name'] ?? null) || ! is_string($file['tmp_name'] ?? null)
            || ! is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > self::resumeLimit()
            || ! is_uploaded_file($file['tmp_name'])
        ) {
            return '';
        }

        $type = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], ['pdf' => 'application/pdf']);

        return $type['type'] === 'application/pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) === '%PDF-'
            ? $file['tmp_name']
            : '';
    }

    private static function applicationRecipient(WP_Post $job): string
    {
        $sourceId = absint(get_post_meta($job->ID, '_kc_translation_of', true));
        foreach (array_filter([$job->ID, $sourceId]) as $id) {
            foreach (['_kampachi_apply_email', '_kampachi_email'] as $key) {
                $address = get_post_meta($id, $key, true);
                if (is_string($address) && is_email($address)) {
                    return $address;
                }
            }
        }

        $settings = get_option('kampachi_cms_settings', []);
        $address = is_array($settings) ? ($settings['career_email'] ?? '') : '';

        return is_string($address) && is_email($address) ? $address : '';
    }

    protected static function adminUrl(): string
    {
        return add_query_arg(
            ['post_type' => self::POST_TYPE, 'page' => 'kampachi-careers-landing'],
            admin_url('edit.php')
        );
    }
}
