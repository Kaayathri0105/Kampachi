<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Awards
{
    public const POST_TYPE = 'kc_award';
    public const PAGE_SLUG = 'awards-accolades';
    public const PAGE_TITLE = 'Awards & Accolades';

    private const INSTALL_OPTION = 'kampachi_awards_installed';
    private const ACTION = 'kampachi_save_award_details';
    private const NONCE = 'kampachi_award_nonce';
    private const FIELDS = [
        'award_organisation' => ['Awarding body', 'text'],
        'award_year' => ['Award year(s)', 'text'],
        'award_source_url' => ['Official award or announcement URL', 'url'],
    ];

    public static function boot(): void
    {
        add_action('wp_loaded', [self::class, 'upgrade'], 20);
        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'registerBox']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    public static function upgrade(): void
    {
        if (! add_option(self::INSTALL_OPTION, '1', '', false)) {
            return;
        }

        flush_rewrite_rules(false);
    }

    public static function registerBox(): void
    {
        add_meta_box(
            'kampachi-award-details',
            'Award Details',
            [self::class, 'renderBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function renderBox(WP_Post $post): void
    {
        wp_nonce_field(self::ACTION, self::NONCE);

        foreach (self::FIELDS as $key => [$label, $type]) {
            $name = 'kampachi_' . $key;
            $value = get_post_meta($post->ID, '_' . $name, true);

            echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';
            echo '<input class="widefat" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="'
                . esc_attr($type) . '" value="' . esc_attr(is_string($value) ? $value : '') . '">';
        }
    }

    public static function save(int $postId, WP_Post $post): void
    {
        $nonce = $_POST[self::NONCE] ?? null;
        if (
            $post->post_type !== self::POST_TYPE
            || wp_is_post_autosave($postId)
            || wp_is_post_revision($postId)
            || ! current_user_can('edit_post', $postId)
            || ! is_string($nonce)
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), self::ACTION)
        ) {
            return;
        }

        foreach (self::FIELDS as $key => [, $type]) {
            $name = 'kampachi_' . $key;
            $raw = $_POST[$name] ?? null;
            if (! is_string($raw)) {
                continue;
            }

            $raw = trim(wp_unslash($raw));
            $value = match ($type) {
                'url' => self::sourceUrl($raw),
                default => sanitize_text_field($raw),
            };

            update_post_meta($postId, '_' . $name, wp_slash($value));
        }
    }

    public static function originalUrl(int $postId): string
    {
        $value = get_post_meta($postId, '_kampachi_award_source_url', true);

        return is_string($value) ? self::sourceUrl($value) : '';
    }

    private static function sourceUrl(string $value): string
    {
        $url = esc_url_raw($value, ['https', 'http']);
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        $host = wp_parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['https', 'http'], true) && is_string($host) && $host !== '' ? $url : '';
    }
}
