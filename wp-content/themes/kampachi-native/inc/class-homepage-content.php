<?php

/**
 * Kampachi homepage data adapter for the original native homepage design.
 *
 * The CMS option stores presentation choices, while published posts remain
 * the single source of truth for offers, sake and outlets.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

namespace Kampachi\Native;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Homepage_Content
{
    private const OPTION = 'kampachi_homepage_sections';

    private const SECTIONS = ['intro', 'offers', 'dishes', 'sake', 'outlets'];

    public static function settings(): array
    {
        if (is_callable([\Kampachi_Homepage::class, 'options_for_post'])) {
            return \Kampachi_Homepage::options_for_post(get_queried_object_id());
        }

        $settings = get_option(self::OPTION, []);

        return is_array($settings) ? $settings : [];
    }

    public static function enabled(array $settings, string $section): bool
    {
        if ($section === 'outlets') {
            return true;
        }

        if (! in_array($section, array_merge(['hero'], self::SECTIONS), true)) {
            return false;
        }

        $visibility = $settings['section_visibility'] ?? null;

        return ! is_array($visibility) || ! array_key_exists($section, $visibility)
            || (bool) $visibility[$section];
    }

    public static function ordered_sections(array $settings): array
    {
        return array_values(array_filter(
            self::SECTIONS,
            static fn (string $section): bool => self::enabled($settings, $section)
        ));
    }

    public static function content(array $settings, string $section, string $field, string $default = ''): string
    {
        $key = $section . '_' . $field;
        if (array_key_exists($key, $settings)) {
            return is_string($settings[$key]) ? $settings[$key] : '';
        }

        return Site_Language::text($default);
    }

    public static function description(array $settings, string $section, string $default = ''): string
    {
        return wp_kses_post(self::content($settings, $section, 'description', $default));
    }

    /**
     * @param array<string, string> $attributes
     * @return array{0: string, 1: string}
     */
    public static function text_presentation(mixed $format, string $defaultTag, array $attributes = []): array
    {
        if (is_callable([\Kampachi_Homepage::class, 'text_presentation'])) {
            return \Kampachi_Homepage::text_presentation($format, $defaultTag, $attributes);
        }

        $markup = '';
        foreach ($attributes as $name => $value) {
            $markup .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
        }

        return [$defaultTag, $markup];
    }

    /** @param array<string, string> $attributes */
    public static function text_element(
        string $value,
        mixed $format,
        string $defaultTag,
        array $attributes = [],
        bool $plain = false
    ): string {
        [$tag, $attrs] = self::text_presentation($format, $defaultTag, $attributes);
        if ($plain || $tag !== $defaultTag) {
            $text = is_callable([\Kampachi_Homepage::class, 'plain_text'])
                ? \Kampachi_Homepage::plain_text($value)
                : wp_strip_all_tags($value);
            $html = nl2br(esc_html($text), false);
        } else {
            $html = Site_Language::rich_links(wp_kses_post($value));
        }

        return '<' . $tag . $attrs . '>' . $html . '</' . $tag . '>';
    }

    /** @param array<string, string> $attributes */
    public static function section_text(
        array $settings,
        string $section,
        string $field,
        string $tag,
        array $attributes,
        string $default = ''
    ): string {
        return self::text_element(
            self::content($settings, $section, $field, $default),
            $settings['format'][$section . '_' . $field] ?? [],
            $tag,
            $attributes,
            $field === 'title'
        );
    }

    public static function limit(array $settings, string $section, int $fallback, int $maximum): int
    {
        $key = $section . '_limit';
        if (! array_key_exists($key, $settings)) {
            return $fallback;
        }

        return max(0, min($maximum, absint($settings[$key])));
    }

    public static function items_per_view(array $settings, string $section, int $default, int $count): int
    {
        return max(1, min($count, absint($settings[$section . '_per_view'] ?? $default)));
    }

    /**
     * Return only published posts of the requested type in the editor's order.
     * Before the first homepage save, use the existing published content.
     * After a save, deliberately empty selections stay empty.
     *
     * @return array<int, WP_Post>
     */
    public static function posts(array $settings, string $key, string $postType, int $limit = 0): array
    {
        if (array_key_exists($key, $settings)) {
            $ids = is_array($settings[$key]) ? $settings[$key] : [];
            $posts = [];
            foreach (array_unique(array_map('absint', $ids)) as $id) {
                if ($id === 0) {
                    continue;
                }

                $post = get_post($id);
                if ($post instanceof WP_Post && $post->post_type === $postType && $post->post_status === 'publish') {
                    $posts[] = $post;
                }

                if (! Site_Language::active() && $limit > 0 && count($posts) >= $limit) {
                    break;
                }
            }

            $localized = Site_Language::posts($posts);

            return $limit > 0 ? array_slice($localized, 0, $limit) : $localized;
        }

        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => ! Site_Language::active() && $limit > 0 ? $limit : -1,
            'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        $localized = Site_Language::posts($posts);

        return $limit > 0 ? array_slice($localized, 0, $limit) : $localized;
    }

    public static function image(int $postId, string $type): string
    {
        if (function_exists('kampachi_native_post_image')) {
            $featured = kampachi_native_post_image($postId, 'large');
            if ($featured !== '') {
                return $featured;
            }
        }

        $key = $type === 'sake' ? '_kampachi_sake_image_ids' : '_kampachi_outlet_hero_ids';
        $ids = get_post_meta($postId, $key, true);
        if (! is_array($ids)) {
            return '';
        }

        foreach ($ids as $id) {
            $url = wp_get_attachment_image_url(absint($id), 'large');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    /** @return array<int, array<string, string>> */
    public static function sake_slides(array $settings): array
    {
        $posts = self::posts($settings + ['sake_ids' => []], 'sake_ids', 'kc_sake');
        $slides = [];

        foreach ($posts as $post) {
            $url = self::image($post->ID, 'sake');

            $slides[] = [
                'src' => $url,
                'alt' => $post->post_title,
                'title' => $post->post_title,
                'description' => trim(wp_strip_all_tags($post->post_excerpt)),
                'link' => kampachi_native_label('discover'),
                'href' => get_permalink($post),
            ];
        }

        return $slides;
    }
}
