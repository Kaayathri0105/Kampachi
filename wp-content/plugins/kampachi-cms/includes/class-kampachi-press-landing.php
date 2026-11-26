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

    private const YOUTUBE_META = '_kampachi_press_youtube_url';

    private const FIELDS = [
        'press_video_title' => 'Title',
        'press_video_description' => 'Description',
        'press_video_cta_label' => 'Button label',
        'press_video_cta_url' => 'Button URL',
    ];

    protected static function renderHero(WP_Post $page): void
    {
        if (Kampachi_Translations::post_language($page) === 'en') {
            $youtube = (string) get_post_meta($page->ID, self::YOUTUBE_META, true);
            echo '<div class="kc-field"><label class="kc-label" for="kc-press-youtube-url">YouTube URL</label>';
            echo '<input class="widefat" type="url" id="kc-press-youtube-url" name="kampachi_landing[press_youtube_url]"'
                . ' value="' . esc_attr($youtube) . '" placeholder="https://www.youtube.com/watch?v=…">';
            echo '<p class="description">A YouTube link takes priority over the uploaded MP4. The YouTube player'
                . ' starts muted when the browser allows autoplay.</p></div>';
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
        $youtube = null;
        if (Kampachi_Translations::post_language($page) !== 'en') {
            unset($landing['hero_video_id']);
        } elseif (is_string($landing['press_youtube_url'] ?? null)) {
            $input = trim($landing['press_youtube_url']);
            $youtube = self::youtubeId($input);
            if ($input !== '' && $youtube === '') {
                wp_die(
                    esc_html__('Enter a YouTube link for a single video.', 'kampachi-cms'),
                    '',
                    ['response' => 400, 'back_link' => true]
                );
            }
        }

        parent::saveLandingFields($page, $landing);

        if ($youtube !== null) {
            $url = $youtube === '' ? '' : 'https://www.youtube.com/watch?v=' . $youtube;
            update_post_meta($page->ID, self::YOUTUBE_META, $url);
        }

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
        $id = absint(get_post_meta(self::videoOwner($pageId), '_kampachi_hero_video_id', true));
        if ($id === 0 || ! wp_attachment_is('video', $id)) {
            return '';
        }

        return wp_get_attachment_url($id) ?: '';
    }

    public static function youtubeEmbedUrl(int $pageId): string
    {
        $url = (string) get_post_meta(self::videoOwner($pageId), self::YOUTUBE_META, true);
        $video = self::youtubeId($url);
        if ($video === '') {
            return '';
        }

        $home = wp_parse_url(home_url('/'));
        $origin = $home['scheme'] . '://' . $home['host'];
        if (isset($home['port'])) {
            $origin .= ':' . $home['port'];
        }

        return add_query_arg(
            ['enablejsapi' => '1', 'origin' => $origin, 'playsinline' => '1'],
            'https://www.youtube-nocookie.com/embed/' . $video
        );
    }

    private static function videoOwner(int $pageId): int
    {
        return absint(get_post_meta($pageId, '_kc_translation_of', true)) ?: $pageId;
    }

    private static function youtubeId(string $url): string
    {
        $parts = wp_parse_url($url);
        if (
            ! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return '';
        }

        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        $video = '';
        if ($host === 'youtu.be') {
            $video = $path;
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true)) {
            if ($path === 'watch') {
                wp_parse_str($parts['query'] ?? '', $query);
                $video = $query['v'] ?? '';
            } elseif (preg_match('~\A(?:embed|live|shorts)/([A-Za-z0-9_-]{11})\z~', $path, $matches)) {
                $video = $matches[1];
            }
        }

        return is_string($video) && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $video) ? $video : '';
    }
}
