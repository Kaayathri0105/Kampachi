<?php

declare(strict_types=1);

use Kampachi\CMS\FooterNavigation;
use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

final class Kampachi_Shell_Translations
{
    private const GROUPS = ['header', 'footer'];
    private const LINK_FIELDS = ['navigation', 'booking', 'links'];

    private const FOOTER_FIELDS = [
        'footer_explore_title' => 'Navigation heading',
        'footer_office_title' => 'Address heading',
        'footer_address' => 'Head office address',
        'footer_contact_title' => 'Contact heading',
        'footer_cta_text' => 'Enquiry button text',
        'footer_company' => 'Copyright company name',
        'footer_copyright' => 'Copyright label',
        'footer_privacy' => 'Privacy link title',
    ];

    public static function language(string $group, mixed $requested): ?string
    {
        if (! is_string($requested)) {
            return null;
        }
        if ($requested === 'en') {
            return 'en';
        }

        return in_array($group, self::GROUPS, true)
            && isset(Kampachi_Master_Settings::languages()[$requested])
            ? $requested
            : null;
    }

    public static function text(string $group, string $field, string $source, string $language): ?string
    {
        if ($language === 'en' || self::language($group, $language) === null) {
            return null;
        }

        $key = self::field_key($field, $source);
        $value = self::values($group, $language)[$key] ?? '';

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function render_tabs(string $group, string $language, string $url): void
    {
        if (! in_array($group, self::GROUPS, true)) {
            return;
        }
        $languages = Kampachi_Master_Settings::languages();
        if (count($languages) < 2) {
            return;
        }

        echo '<section class="kc-post-translations">';
        echo '<nav class="nav-tab-wrapper kc-language-tabs" aria-label="Content languages">';
        foreach ($languages as $code => $name) {
            $label = Kampachi_Translations::tab_label($code, $name);
            $target = $code === 'en' ? $url : add_query_arg('kc_language', $code, $url);
            echo '<div class="kc-language-tab-item">';
            if ($code === $language) {
                echo '<span class="nav-tab nav-tab-active" aria-current="page">' . esc_html($label) . '</span>';
            } else {
                echo '<a class="nav-tab" href="' . esc_url($target) . '">' . esc_html($label) . '</a>';
            }
            echo '</div>';
        }
        echo '</nav><div class="kc-language-notice"><strong>Editing language</strong> ';
        echo esc_html(Kampachi_Translations::tab_label($language, $languages[$language]));
        echo '<p>Manage links, media, and other shared settings in the English tab. ';
        echo 'Leave a translation blank to use the default wording.</p></div></section>';
    }

    public static function render_fields(string $group, string $language): void
    {
        $values = self::values($group, $language);
        foreach (self::sections($group) as $heading => $fields) {
            if ($fields === []) {
                continue;
            }
            echo '<section class="kc-module-panel"><h2>' . esc_html($heading) . '</h2>';
            echo '<table class="form-table"><tbody>';
            foreach ($fields as $key => [$label, $source]) {
                $value = (string) ($values[$key] ?? '');
                $placeholder = apply_filters('kampachi_shell_translation_default', $source, $language);
                $id = 'kc-shell-label-' . $key;
                $name = 'settings[' . $key . ']';
                echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
                $attributes = ' id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" placeholder="'
                    . esc_attr($placeholder) . '"';
                if ($key === 'footer_address') {
                    echo '<textarea class="large-text" rows="4"' . $attributes . '>' . esc_textarea($value) . '</textarea>';
                } else {
                    echo '<input type="text" class="regular-text"' . $attributes . ' value="' . esc_attr($value) . '">';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></section>';
        }
    }

    public static function save(string $group, string $language, array $raw): void
    {
        $values = self::values($group, $language);
        foreach (self::sections($group) as $fields) {
            foreach (array_keys($fields) as $key) {
                if (! is_string($raw[$key] ?? null)) {
                    continue;
                }
                $value = $key === 'footer_address'
                    ? sanitize_textarea_field($raw[$key])
                    : sanitize_text_field($raw[$key]);
                if ($value === '') {
                    unset($values[$key]);
                } else {
                    $values[$key] = $value;
                }
            }
        }
        update_option(self::option_name($group, $language), $values, false);
    }

    /** @return array<string, array<string, array{string, string}>> */
    private static function sections(string $group): array
    {
        if ($group === 'header') {
            return [
                'Header Navigation' => self::header_navigation_fields(),
                'Reservation CTA' => [
                    'reservation_label' => [
                        'CTA button title',
                        Kampachi_Shell_Settings::get('reservation_label', 'RESERVATION'),
                    ],
                ] + self::link_fields('booking', Kampachi_Header_Editor::booking_items()),
            ];
        }

        $footer = [];
        $defaults = [
            'footer_cta_text' => 'Click to Enquire',
            'footer_company' => get_bloginfo('name'),
            'footer_copyright' => 'Copyright',
            'footer_privacy' => 'Privacy & Policy',
        ];
        foreach (self::FOOTER_FIELDS as $key => $label) {
            $footer[$key] = [$label, Kampachi_Shell_Settings::get($key, $defaults[$key] ?? '')];
        }

        return [
            'Footer Content' => $footer,
            'Footer Pages & Links' => self::link_fields('links', FooterNavigation::links()),
        ];
    }

    /** @return array<string, array{string, string}> */
    private static function header_navigation_fields(): array
    {
        $fields = [];
        foreach (Kampachi_Header_Editor::menu_items() as $item) {
            if (! is_array($item)) {
                continue;
            }
            $fields += self::link_fields('navigation', [$item]);
            $children = $item['children'] ?? [];
            $title = (string) ($item['title'] ?? '');
            $url = (string) ($item['url'] ?? '');
            if ($children !== [] && Outlets::isLocationMenu($title, $url)) {
                $children = Kampachi_Header_Editor::location_children();
            }
            if (is_array($children)) {
                $fields += self::link_fields('navigation', $children);
            }
        }

        return $fields;
    }

    /** @return array<string, array{string, string}> */
    private static function link_fields(string $field, array $items): array
    {
        $fields = [];
        foreach ($items as $item) {
            $title = is_string($item['title'] ?? null) ? $item['title'] : '';
            if ($title !== '') {
                $fields[self::field_key($field, $title)] = [$title, $title];
            }
        }

        return $fields;
    }

    private static function field_key(string $field, string $source): string
    {
        if (! in_array($field, self::LINK_FIELDS, true)) {
            return $field;
        }

        $source = trim(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $field . '_' . hash('sha256', $source);
    }

    private static function option_name(string $group, string $language): string
    {
        return 'kampachi_shell_' . $group . '_' . $language;
    }

    private static function values(string $group, string $language): array
    {
        $values = get_option(self::option_name($group, $language), []);

        return is_array($values) ? $values : [];
    }
}
