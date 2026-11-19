<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Footer page selections and custom links, managed in the Footer editor.
 */
final class FooterNavigation
{
    private const POST_TYPES = [
        'page', 'post', 'kc_offer', 'kc_sake', 'kc_outlet',
        'kc_event', 'kc_job', 'kc_prefecture',
    ];

    public static function isConfigured(): bool
    {
        $settings = get_option('kampachi_cms_settings', []);

        return is_array($settings) && array_key_exists('footer_nav_items', $settings);
    }

    /** @return array<int, array{type: string, post_id: int, label: string, url: string, new_tab: bool}> */
    public static function items(): array
    {
        $settings = get_option('kampachi_cms_settings', []);
        if (is_array($settings) && array_key_exists('footer_nav_items', $settings)) {
            return self::sanitizeItems($settings['footer_nav_items']);
        }

        return self::legacyItems();
    }

    /** @return array<int, array{type: string, post_id: int, label: string, url: string, new_tab: bool}> */
    private static function legacyItems(): array
    {
        $locations = get_nav_menu_locations();
        $menuId = absint($locations['footer'] ?? 0);
        $items = $menuId > 0 ? wp_get_nav_menu_items($menuId, ['post_status' => 'publish']) : [];
        $rows = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! $item instanceof WP_Post) {
                continue;
            }

            $isPost = ($item->type ?? '') === 'post_type';
            $rows[] = [
                'type' => $isPost ? 'page' : 'custom',
                'post_id' => $isPost ? absint($item->object_id ?? 0) : 0,
                'label' => is_string($item->title ?? null) ? $item->title : '',
                'url' => is_string($item->url ?? null) ? $item->url : '',
                'new_tab' => ($item->target ?? '') === '_blank',
            ];
        }

        return self::sanitizeItems($rows);
    }

    /** @return array<int, array{type: string, post_id: int, label: string, url: string, new_tab: bool}> */
    public static function sanitizeItems(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $item = self::sanitizeItem($row);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array{type: string, post_id: int, label: string, url: string, new_tab: bool}|null */
    private static function sanitizeItem(mixed $row): ?array
    {
        if (! is_array($row) || ! in_array($row['type'] ?? null, ['page', 'custom'], true)) {
            return null;
        }

        $label = is_string($row['label'] ?? null) ? sanitize_text_field($row['label']) : '';
        $item = [
            'type' => $row['type'],
            'post_id' => 0,
            'label' => $label,
            'url' => '',
            'new_tab' => in_array($row['new_tab'] ?? false, [true, 1, '1'], true),
        ];

        if ($item['type'] === 'page') {
            $value = $row['post_id'] ?? null;
            $id = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
            if (self::publishedPost($id) === null) {
                return null;
            }

            $item['post_id'] = $id;

            return $item;
        }

        $url = is_string($row['url'] ?? null) ? self::safeUrl($row['url']) : '';
        if ($label === '' || $url === '') {
            return null;
        }

        $item['url'] = $url;

        return $item;
    }

    private static function publishedPost(int $postId): ?WP_Post
    {
        $post = $postId > 0 ? get_post($postId) : null;

        return $post instanceof WP_Post
            && $post->post_status === 'publish'
            && in_array($post->post_type, self::POST_TYPES, true)
                ? $post
                : null;
    }

    private static function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return '';
        }

        if (str_starts_with($url, '#') || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return esc_url_raw($url, ['http', 'https']);
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (! preg_match('~^https?://~i', $url) || ! is_string($host) || $host === '') {
            return '';
        }

        return esc_url_raw($url, ['http', 'https']);
    }

    /** @return array<int, array{title: string, url: string, new_tab: bool}> */
    public static function links(): array
    {
        $links = [];
        foreach (self::items() as $item) {
            $title = $item['label'];
            $url = $item['url'];
            if ($item['type'] === 'page') {
                $title = $title !== '' ? $title : get_the_title($item['post_id']);
                $url = get_permalink($item['post_id']);
            }

            if ($title !== '' && is_string($url) && $url !== '') {
                $links[] = ['title' => $title, 'url' => $url, 'new_tab' => $item['new_tab']];
            }
        }

        return $links;
    }

    public static function renderLinks(): void
    {
        $links = self::links();
        if ($links === []) {
            return;
        }

        echo '<ul class="footer-links">';
        foreach ($links as $link) {
            echo '<li><a href="' . esc_url($link['url']) . '"';
            if ($link['new_tab']) {
                echo ' target="_blank" rel="noopener noreferrer"';
            }
            echo '>' . esc_html($link['title']) . '</a></li>';
        }
        echo '</ul>';
    }

    public static function renderEditor(): void
    {
        $items = self::items();
        $posts = get_posts([
            'post_type' => self::POST_TYPES,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        echo '<section class="kc-module-panel kc-footer-links-panel"><h2>Footer Pages &amp; Links</h2>';
        echo '<p>Add pages or custom links to the footer. Use the arrows to arrange their order.</p>';
        echo '<input type="hidden" name="footer_nav_present" value="1">';
        echo '<div id="kc-footer-link-list">';
        foreach ($items as $index => $item) {
            self::renderRow((string) $index, $item, $posts);
        }
        echo '</div><p id="kc-footer-links-empty"' . ($items !== [] ? ' hidden' : '') . '>No footer links added yet.</p>';
        echo '<p class="kc-footer-add-actions"><button type="button" class="button" id="kc-footer-add-page">+ Add Page</button> ';
        echo '<button type="button" class="button" id="kc-footer-add-custom">+ Add Custom Link</button></p>';
        echo '<template id="kc-footer-link-template">';
        self::renderRow('__INDEX__', ['type' => 'page', 'post_id' => 0, 'label' => '', 'url' => '', 'new_tab' => false], $posts);
        echo '</template></section>';
    }

    /** @param array<int, WP_Post> $posts */
    private static function renderRow(string $index, array $item, array $posts): void
    {
        $prefix = 'footer_nav[' . $index . ']';
        $isPage = $item['type'] === 'page';
        echo '<div class="kc-footer-link-row">';
        echo '<input type="hidden" data-footer-field="type" name="' . esc_attr($prefix . '[type]') . '" value="' . esc_attr($item['type']) . '">';
        echo '<label class="kc-footer-page-field"' . (! $isPage ? ' hidden' : '') . '>Page / Post';
        echo '<select data-footer-field="post_id" name="' . esc_attr($prefix . '[post_id]') . '"' . ($isPage ? ' required' : '') . '>';
        echo '<option value="0">Choose a published page or post</option>';
        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }
            $type = get_post_type_object($post->post_type);
            $group = $type !== null ? $type->labels->singular_name : $post->post_type;
            $title = get_the_title($post);
            echo '<option value="' . esc_attr((string) $post->ID) . '" data-title="' . esc_attr($title) . '" data-url="';
            echo esc_attr(get_permalink($post)) . '"' . selected($item['post_id'], $post->ID, false) . '>';
            echo esc_html($title . ' — ' . $group) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Link title <span class="description">(optional for a page)</span><input type="text" data-footer-field="label" name="';
        echo esc_attr($prefix . '[label]') . '" value="' . esc_attr($item['label']) . '"' . (! $isPage ? ' required' : '') . '></label>';
        echo '<label class="kc-footer-custom-field"' . ($isPage ? ' hidden' : '') . '>Link URL<input type="text" data-footer-field="url" name="';
        echo esc_attr($prefix . '[url]') . '" value="' . esc_attr($item['url']) . '" placeholder="https://… or /page/"' . (! $isPage ? ' required' : '') . '></label>';
        echo '<label class="kc-footer-new-tab"><input type="checkbox" data-footer-field="new_tab" name="';
        echo esc_attr($prefix . '[new_tab]') . '" value="1"' . checked($item['new_tab'], true, false) . '> Open in a new tab</label>';
        echo '<div class="kc-footer-link-actions"><button type="button" class="button" data-footer-move="up" aria-label="Move footer link up">↑</button> ';
        echo '<button type="button" class="button" data-footer-move="down" aria-label="Move footer link down">↓</button> ';
        echo '<button type="button" class="button-link-delete" data-footer-remove>Remove</button></div></div>';
    }
}
