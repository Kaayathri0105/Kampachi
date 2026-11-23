<?php

declare(strict_types=1);

use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Structured, CMS-managed header navigation and outlet reservation links.
 *
 * The header's saved arrays are authoritative after the first save. Before
 * that, current WordPress menus and outlet URLs populate the editor safely.
 */
final class Kampachi_Header_Editor
{
    /** @return array<int, array{title: string, url: string, children: array<int, array{title: string, url: string}>}> */
    public static function menu_items(): array
    {
        $settings = get_option('kampachi_cms_settings', []);

        if (is_array($settings) && array_key_exists('header_nav_items', $settings)) {
            return is_array($settings['header_nav_items']) ? $settings['header_nav_items'] : [];
        }

        $locations = get_nav_menu_locations();
        $menuId = isset($locations['primary']) ? absint($locations['primary']) : 0;

        if ($menuId > 0) {
            $items = wp_get_nav_menu_items($menuId, ['post_status' => 'publish']);
            if (is_array($items) && $items !== []) {
                $parents = [];
                $children = [];
                foreach ($items as $item) {
                    if (! $item instanceof WP_Post) {
                        continue;
                    }
                    $row = [
                        'title' => (string) $item->title,
                        'url' => (string) $item->url,
                    ];
                    $parentId = (int) $item->menu_item_parent;
                    if ($parentId > 0) {
                        $children[$parentId][] = $row;
                    } else {
                        $parents[(int) $item->ID] = $row + ['children' => []];
                    }
                }
                foreach ($parents as $id => &$parent) {
                    $parent['children'] = $children[$id] ?? [];
                }
                unset($parent);
                return array_values($parents);
            }
        }

        $result = [];
        foreach (['home', 'promotion', 'sake', '47in27', 'catering-events', 'outlets', 'contact-us'] as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            $children = $slug === 'outlets' ? self::location_children() : [];

            if ($page instanceof WP_Post && $page->post_status === 'publish') {
                $result[] = [
                    'title' => get_the_title($page),
                    'url' => get_permalink($page),
                    'children' => $children,
                ];
            } elseif ($slug === 'outlets' && $children !== []) {
                $type = get_post_type_object('kc_outlet');
                $result[] = [
                    'title' => $type !== null ? $type->labels->name : '',
                    'url' => '',
                    'children' => $children,
                ];
            }
        }
        return $result;
    }

    /** @return array<int, array{outlet_id: int, title: string, url: string}> */
    public static function booking_items(): array
    {
        $settings = get_option('kampachi_cms_settings', []);
        if (is_array($settings) && array_key_exists('header_booking_items', $settings)) {
            return self::sanitize_booking($settings['header_booking_items']);
        }

        $result = [];
        foreach (Outlets::publishedLocations() as $outlet) {
            $result[] = [
                'outlet_id' => $outlet->ID,
                'title' => Outlets::locationName($outlet),
                'url' => (string) get_post_meta($outlet->ID, '_kampachi_booking_url', true),
            ];
        }
        return $result;
    }

    /** @return list<array{title: string, url: string}> */
    public static function location_children(): array
    {
        $items = [];
        foreach (Outlets::publishedLocations() as $outlet) {
            $url = get_permalink($outlet);
            if (is_string($url) && $url !== '') {
                $items[] = ['title' => Outlets::locationName($outlet), 'url' => $url];
            }
        }

        return $items;
    }

    public static function render(): void
    {
        $nav = self::menu_items();
        $booking = self::booking_items();
        $outlets = Outlets::publishedLocations();

        echo '<section class="kc-header-section"><div class="kc-header-section-heading">';
        echo '<h2><span class="kc-section-number">02</span> Header Navigation</h2><p>Set the title and link for each menu item. Reorder links with the drag handle.</p></div>';
        echo '<div id="kc-header-nav-list" class="kc-header-sortlist">';
        foreach ($nav as $item) {
            if (is_array($item)) {
                self::render_nav_item($item);
            }
        }
        echo '</div><p><button type="button" class="button button-secondary" id="kc-header-add-menu">+ Add Menu</button></p></section>';

        echo '<section class="kc-header-section"><div class="kc-header-section-heading">';
        echo '<h2><span class="kc-section-number">03</span> Reservation CTA — Outlet Booking Links</h2>';
        echo '<p>Manage the reservation button and its outlet dropdown here. '; 
        echo 'Each outlet can use its own booking URL. If the override is empty, its Outlet post booking URL is used.</p></div>';
        $reservationLabel = Kampachi_Shell_Settings::get('reservation_label', 'RESERVATION');
        $reservationEnabled = Kampachi_Shell_Settings::get('reservation_enabled', '1') !== '0';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="kc-reservation_label">CTA button title</label></th><td>';
        echo '<input type="text" class="regular-text" id="kc-reservation_label" name="settings[reservation_label]" value="' . esc_attr($reservationLabel) . '"></td></tr>';
        echo '<tr><th scope="row">Show CTA button</th><td><label for="kc-reservation_enabled">';
        echo '<input type="checkbox" id="kc-reservation_enabled" name="settings[reservation_enabled]" value="1" ' . checked($reservationEnabled, true, false) . '> Enabled';
        echo '</label></td></tr></tbody></table>';
        echo '<div id="kc-header-booking-list" class="kc-header-sortlist">';
        foreach ($booking as $item) {
            if (is_array($item)) {
                self::render_booking_item($item, $outlets);
            }
        }
        echo '</div><p><button type="button" class="button button-secondary" id="kc-header-add-booking">+ Add Outlet to CTA</button></p>';
        echo '<p class="description">Only published outlets with a valid booking URL will appear in the reservation dropdown. '; 
        echo 'To change an outlet’s default URL, edit that Outlet post.</p></section>';
        self::templates($outlets);
    }

    /** @param array<string, mixed> $item */
    private static function render_nav_item(array $item): void
    {
        echo '<div class="kc-header-nav-item kc-header-sortable">';
        echo '<div class="kc-header-row"><span class="dashicons dashicons-move kc-header-handle" title="Drag to reorder"></span>';
        echo '<label>Menu title<input type="text" class="regular-text" data-field="title" value="' . esc_attr((string) ($item['title'] ?? '')) . '" placeholder="e.g. Outlets" required></label>';
        echo '<label>Link (leave blank for dropdown only)<input type="text" class="regular-text" data-field="url" value="' . esc_attr((string) ($item['url'] ?? '')) . '" placeholder="https://..."></label>';
        echo '<button type="button" class="button-link-delete kc-header-remove" aria-label="Remove menu">Remove</button></div>';
        echo '<div class="kc-header-subitems"><strong>Dropdown items</strong><div class="kc-header-child-list kc-header-sortlist">';
        foreach (($item['children'] ?? []) as $child) {
            if (is_array($child)) {
                self::render_child_item($child);
            }
        }
        echo '</div><button class="button kc-header-add-child" type="button">+ Add Dropdown Item</button></div></div>';
    }

    /** @param array<string, mixed> $child */
    private static function render_child_item(array $child): void
    {
        echo '<div class="kc-header-child-item kc-header-sortable"><span class="dashicons dashicons-move kc-header-handle" title="Drag to reorder"></span>';
        echo '<label>Title<input type="text" data-field="title" value="' . esc_attr((string) ($child['title'] ?? '')) . '" required></label>';
        echo '<label>URL<input type="text" data-field="url" value="' . esc_attr((string) ($child['url'] ?? '')) . '" placeholder="https://..." required></label>';
        echo '<button class="button-link-delete kc-header-remove" type="button">Remove</button></div>';
    }

    /** @param array<string, mixed> $item @param WP_Post[] $outlets */
    private static function render_booking_item(array $item, array $outlets): void
    {
        echo '<div class="kc-header-booking-item kc-header-sortable"><span class="dashicons dashicons-move kc-header-handle" title="Drag to reorder"></span>';
        echo '<label>Outlet<select data-field="outlet_id" class="kc-header-outlet-select"><option value="0">Choose outlet</option>';
        foreach ($outlets as $outlet) {
            $default = (string) get_post_meta($outlet->ID, '_kampachi_booking_url', true);
            echo '<option value="' . esc_attr((string) $outlet->ID) . '" data-default-url="' . esc_attr($default) . '" ' . selected(absint($item['outlet_id'] ?? 0), $outlet->ID, false) . '>' . esc_html(Outlets::locationName($outlet)) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Booking URL (override)<input type="url" data-field="url" value="' . esc_attr((string) ($item['url'] ?? '')) . '" placeholder="Uses Outlet post booking URL if blank"></label>';
        echo '<button class="button-link-delete kc-header-remove" type="button">Remove</button></div>';
    }

    /** @param WP_Post[] $outlets */
    private static function templates(array $outlets): void
    {
        echo '<template id="kc-header-menu-template">';
        self::render_nav_item(['title' => '', 'url' => '', 'children' => []]);
        echo '</template><template id="kc-header-child-template">';
        self::render_child_item(['title' => '', 'url' => '']);
        echo '</template><template id="kc-header-booking-template">';
        self::render_booking_item(['outlet_id' => 0, 'title' => '', 'url' => ''], $outlets);
        echo '</template>';
    }

    /** @param mixed $raw @return array<int, array{title: string, url: string, children: array<int, array{title: string, url: string}>}> */
    public static function sanitize_nav(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $output = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $title = isset($entry['title']) && is_string($entry['title']) ? sanitize_text_field($entry['title']) : '';
            if ($title === '') {
                continue;
            }
            $url = isset($entry['url']) && is_string($entry['url']) ? self::safe_url($entry['url']) : '';
            $children = [];
            if (isset($entry['children']) && is_array($entry['children'])) {
                foreach ($entry['children'] as $child) {
                    if (! is_array($child) || ! isset($child['title']) || ! is_string($child['title'])) {
                        continue;
                    }
                    $childTitle = sanitize_text_field($child['title']);
                    $childUrl = isset($child['url']) && is_string($child['url']) ? self::safe_url($child['url']) : '';
                    if ($childTitle !== '' && $childUrl !== '') {
                        $children[] = ['title' => $childTitle, 'url' => $childUrl];
                    }
                }
            }
            if ($url !== '' || $children !== []) {
                $output[] = ['title' => $title, 'url' => $url, 'children' => $children];
            }
        }
        return $output;
    }

    /** @param mixed $raw @return array<int, array{outlet_id: int, title: string, url: string}> */
    public static function sanitize_booking(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $output = [];
        $seen = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id = absint($entry['outlet_id'] ?? 0);
            $title = Outlets::locationName($id);
            if ($title === '' || isset($seen[$title])) {
                continue;
            }
            $seen[$title] = true;
            $url = isset($entry['url']) && is_string($entry['url']) ? self::safe_url($entry['url']) : '';
            $output[] = ['outlet_id' => $id, 'title' => $title, 'url' => $url];
        }
        return $output;
    }

    private static function safe_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Absolute HTTPS/HTTP or site-root relative paths only.
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return esc_url_raw($url, ['http', 'https']);
        }
        if (! preg_match('~^https?://~i', $url)
            || ! is_string(wp_parse_url($url, PHP_URL_HOST))) {
            return '';
        }
        return esc_url_raw($url, ['http', 'https']);
    }
}
