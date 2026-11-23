<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Press
{
    public const POST_TYPE = 'kc_press';
    public const PAGE_SLUG = 'press-media';

    private const SCHEMA_VERSION = '1';
    private const SCHEMA_OPTION = 'kampachi_press_schema';
    private const NONCE = 'kampachi_press_nonce';
    private const SAVE_ACTION = 'kampachi_save_press_details';
    public const HIGHLIGHT_META = '_kampachi_press_highlight';
    private const FIELDS = [
        'press_publication' => ['Publication or media outlet', 'text'],
        'press_source_url' => ['Original article URL', 'url'],
    ];

    public static function boot(): void
    {
        add_action('wp_loaded', [self::class, 'upgrade'], 20);
        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'registerBox']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save'], 10, 2);
    }

    public static function upgrade(): void
    {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    public static function registerBox(): void
    {
        add_meta_box(
            'kampachi-press-details',
            'Press Article Details',
            [self::class, 'renderBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function renderBox(WP_Post $post): void
    {
        wp_nonce_field(self::SAVE_ACTION, self::NONCE);
        foreach (self::FIELDS as $key => [$label, $type]) {
            $name = 'kampachi_' . $key;
            $value = get_post_meta($post->ID, '_' . $name, true);
            echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';
            echo '<input class="widefat" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="'
                . esc_attr($type) . '" value="' . esc_attr(is_string($value) ? $value : '') . '">';
        }
        echo '<p><input type="hidden" name="kampachi_press_highlight" value="0">';
        echo '<label><input type="checkbox" name="kampachi_press_highlight" value="1"'
            . checked(self::isHighlight($post->ID), true, false) . '> Show in Press &amp; Media highlights</label></p>';
        echo '<p class="description">Add a featured image for the highlight card. Other articles appear in the archive.</p>';
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
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), self::SAVE_ACTION)
        ) {
            return;
        }

        foreach (self::FIELDS as $key => [, $type]) {
            $name = 'kampachi_' . $key;
            $raw = $_POST[$name] ?? null;
            if (! is_string($raw)) {
                continue;
            }

            $raw = wp_unslash($raw);
            $value = $type === 'url' ? self::sourceUrl($raw) : sanitize_text_field($raw);
            update_post_meta($postId, '_' . $name, wp_slash($value));
        }

        $highlight = $_POST['kampachi_press_highlight'] ?? null;
        if ($highlight === '1' || $highlight === '0') {
            self::setHighlight($postId, $highlight === '1');
        }
    }

    public static function setHighlight(int $postId, bool $highlight): void
    {
        if ($highlight) {
            update_post_meta($postId, self::HIGHLIGHT_META, '1');
        } else {
            delete_post_meta($postId, self::HIGHLIGHT_META);
        }
    }

    public static function isHighlight(int $postId): bool
    {
        return get_post_meta($postId, self::HIGHLIGHT_META, true) === '1';
    }

    public static function originalUrl(int $postId): string
    {
        $url = get_post_meta($postId, '_kampachi_press_source_url', true);

        return is_string($url) ? self::sourceUrl($url) : '';
    }

    private static function sourceUrl(string $value): string
    {
        $url = esc_url_raw($value, ['https', 'http']);
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        $host = wp_parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['https', 'http'], true) && is_string($host) && $host !== '' ? $url : '';
    }
}
