<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use Kampachi_Landings;
use Kampachi_Translations;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class PressLanding extends CollectionLanding
{
    protected const KEY = 'press';
    protected const TITLE = 'Press & Media Landing';
    protected const ITEMS_LABEL = 'press articles';
    protected const HERO_TITLE = 'Featured Video';
    protected const ITEMS_TITLE = 'Article List';
    protected const ITEMS_HELP = 'Use Show to control which articles appear in the list.';
    protected const POST_TYPE = Press::POST_TYPE;
    protected const PAGE_SLUG = Press::PAGE_SLUG;
    protected const PAGE_TITLE = 'Press & Media';

    private const FIELDS = [
        'press_video_title' => 'Title',
        'press_video_description' => 'Description',
        'press_video_cta_label' => 'Button label',
        'press_video_cta_url' => 'Button URL',
    ];

    protected static function renderHero(WP_Post $page): void
    {
        if (Kampachi_Translations::post_language($page) === 'en') {
            $videoId = absint(get_post_meta($page->ID, '_kampachi_hero_video_id', true));
            echo '<input type="hidden" name="kampachi_landing[hero_touched]" value="0"'
                . ' data-kc-collection-hero-touched>';
            Kampachi_Landings::render_video_picker($videoId, 'Featured video (MP4)');
        } else {
            echo '<p class="description">Choose the shared video in the English tab.</p>';
        }
        echo '<p class="description">The video is shared across languages. The title, description and button'
            . ' appear below the player and are saved for this language.</p>';

        foreach (self::FIELDS as $key => $label) {
            $value = (string) get_post_meta($page->ID, '_kampachi_' . $key, true);
            $id = 'kc-' . $key;
            $name = 'kampachi_landing[' . $key . ']';
            echo '<div class="kc-field"><label class="kc-label" for="' . esc_attr($id) . '">'
                . esc_html($label) . '</label>';
            $attributes = ' id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"';
            if ($key === 'press_video_description') {
                echo '<textarea class="widefat" rows="4"' . $attributes . '>' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="widefat" type="text"' . $attributes . ' value="' . esc_attr($value) . '">';
            }
            echo '</div>';
        }
        echo '<p class="description">Enter both a label and a link to show the button.</p>';
    }

    protected static function saveLandingFields(WP_Post $page, array $landing): void
    {
        if (Kampachi_Translations::post_language($page) !== 'en') {
            unset($landing['hero_video_id']);
        }
        parent::saveLandingFields($page, $landing);

        foreach (array_keys(self::FIELDS) as $key) {
            $raw = $landing[$key] ?? null;
            if (! is_string($raw)) {
                continue;
            }

            $value = match ($key) {
                'press_video_description' => sanitize_textarea_field($raw),
                'press_video_cta_url' => esc_url_raw($raw, ['http', 'https']),
                default => sanitize_text_field($raw),
            };
            update_post_meta($page->ID, '_kampachi_' . $key, wp_slash($value));
        }
    }

    public static function videoUrl(int $pageId): string
    {
        $sourceId = absint(get_post_meta($pageId, '_kc_translation_of', true));
        $id = absint(get_post_meta($sourceId ?: $pageId, '_kampachi_hero_video_id', true));
        if ($id === 0 || ! wp_attachment_is('video', $id)) {
            return '';
        }

        return wp_get_attachment_url($id) ?: '';
    }
}
