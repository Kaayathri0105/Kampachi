<?php

declare(strict_types=1);

namespace Kampachi\Native;

use Kampachi_Homepage;
use Kampachi_Landings;
use WP_HTML_Tag_Processor;
use WP_Post;

final class Site_Language
{
    /** @var array<string, array<int, WP_Post|null>> */
    private static array $published = [];

    private const LANGUAGES = ['zh', 'zh-hant', 'ja'];

    /** @var array<string, array<string, string>> */
    private static array $labels = [];

    private static function language(): string
    {
        if (is_admin() || did_action('wp') === 0) {
            return '';
        }

        $language = get_post_meta(get_queried_object_id(), '_kc_language', true);

        return in_array($language, self::LANGUAGES, true) ? $language : '';
    }

    public static function active(): bool
    {
        return self::language() !== '';
    }

    public static function text(string $value): string
    {
        $language = self::language();
        if ($value === '' || $language === '') {
            return $value;
        }

        self::$labels[$language] ??= require __DIR__ . '/languages/' . $language . '.php';
        $key = strtolower(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return self::$labels[$language][$key] ?? $value;
    }

    public static function gettext(string $translation, string $original, string $domain): string
    {
        if (! in_array($domain, ['kampachi-native', 'kampachi-cms'], true)) {
            return $translation;
        }

        $localized = self::text($original);

        return $localized === $original ? $translation : $localized;
    }

    /** @param list<WP_Post> $posts @return list<WP_Post> */
    public static function posts(array $posts): array
    {
        $language = self::language();
        if ($language === '' || $posts === []) {
            return $posts;
        }

        self::$published[$language] ??= [];
        $sources = [];
        $localized = [];
        foreach ($posts as $post) {
            if (get_post_meta($post->ID, '_kc_language', true) === $language) {
                if ($post->post_status === 'publish') {
                    $localized[$post->ID] = $post;
                }
                continue;
            }
            $sourceId = absint(get_post_meta($post->ID, '_kc_translation_of', true)) ?: $post->ID;
            $sources[$post->ID] = $sourceId;
        }

        $missing = array_diff(array_unique($sources), array_keys(self::$published[$language]));
        if ($missing !== []) {
            foreach ($missing as $id) {
                self::$published[$language][$id] = null;
            }
            $translations = get_posts([
                'post_type' => array_values(array_unique(array_column($posts, 'post_type'))),
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'ID',
                'order' => 'DESC',
                'kc_include_translations' => true,
                'meta_query' => [
                    ['key' => '_kc_translation_of', 'value' => array_values($missing), 'compare' => 'IN'],
                    ['key' => '_kc_language', 'value' => $language],
                ],
            ]);
            foreach ($translations as $translation) {
                $sourceId = absint(get_post_meta($translation->ID, '_kc_translation_of', true));
                self::$published[$language][$sourceId] ??= $translation;
            }
        }

        $ordered = [];
        foreach ($posts as $post) {
            $translation = $localized[$post->ID] ?? self::$published[$language][$sources[$post->ID] ?? 0] ?? null;
            if ($translation !== null) {
                $ordered[$translation->ID] = $translation;
            }
        }

        return array_values($ordered);
    }

    public static function url(string $url): string
    {
        if ($url === '' || ! self::active() || str_starts_with($url, '#')) {
            return $url;
        }

        $home = wp_parse_url(home_url('/'));
        $parts = wp_parse_url($url);
        if (! is_array($parts) || (isset($parts['scheme']) && ! in_array($parts['scheme'], ['https', 'http'], true))
            || (isset($parts['host']) && strtolower($parts['host']) !== strtolower($home['host'] ?? ''))) {
            return $url;
        }

        $path = trim($parts['path'] ?? '/', '/');
        $basePath = trim($home['path'] ?? '', '/');
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = ltrim(substr($path, strlen($basePath)), '/');
        }
        $postId = url_to_postid(home_url('/' . $path . (isset($parts['query']) ? '?' . $parts['query'] : '')));
        if ($postId > 0) {
            $post = get_post($postId);
        } elseif ($path === '') {
            $post = class_exists(Kampachi_Homepage::class) ? Kampachi_Homepage::master_page() : null;
        } else {
            $post = get_page_by_path($path, OBJECT, ['page', 'kc_landing']);
        }
        if (! $post instanceof WP_Post) {
            return $url;
        }

        $target = self::posts([$post])[0] ?? null;
        $permalink = $target !== null ? get_permalink($target) : '';
        if (! is_string($permalink) || $permalink === '') {
            return '';
        }
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $permalink = add_query_arg(array_diff_key($query, array_flip(['p', 'page_id', 'post_type'])), $permalink);
        }

        return $permalink . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    public static function links(array $links): array
    {
        if (! self::active()) {
            return $links;
        }

        foreach ($links as $key => $link) {
            $url = self::url($link['url']);
            if ($url === '') {
                unset($links[$key]);
                continue;
            }
            $links[$key]['url'] = $url;
            $links[$key]['title'] = self::text($link['title']);
        }

        return $links;
    }

    public static function menu_items(array $items): array
    {
        if (! self::active()) {
            return $items;
        }

        foreach ($items as $item) {
            $item->title = self::text((string) $item->title);
            $item->url = self::url((string) $item->url);
        }

        return $items;
    }

    public static function rich_links(string $html): string
    {
        if (! self::active() || $html === '' || ! class_exists(WP_HTML_Tag_Processor::class)) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);
        while ($processor->next_tag('a')) {
            $url = $processor->get_attribute('href');
            if (! is_string($url)) {
                continue;
            }
            $localized = self::url($url);
            if ($localized === '') {
                $processor->remove_attribute('href');
                $processor->set_attribute('aria-disabled', 'true');
            } elseif ($localized !== $url) {
                $processor->set_attribute('href', $localized);
            }
        }

        return $processor->get_updated_html();
    }
}
