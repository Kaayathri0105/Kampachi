<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use Kampachi_Homepage;
use Kampachi_Landings;
use Kampachi_Translations;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

final class OffersLanding extends CollectionLanding
{
    protected const KEY = 'offers';
    protected const TITLE = 'Offers Landing';
    protected const POST_TYPE = 'kc_offer';
    protected const PAGE_SLUG = 'promotion';
    protected const TAXONOMY = 'kc_offer_category';
    protected const HAS_ARCHIVE = true;

    private const HERO_FORM = 'kampachi_offer_landing_hero';
    private const HERO_META = '_kampachi_offer_landing_hero';
    private const LEGACY_IMAGES = '_kampachi_hero_image_ids';
    private const LEGACY_VIDEO = '_kampachi_hero_video_id';

    /** @return array<string, string> */
    public static function categories(int $pageId): array
    {
        $categories = [];
        foreach (self::posts($pageId) as $offer) {
            $categories += self::offerCategories($offer);
        }

        return self::orderCategories($pageId, $categories);
    }

    /** @return array<string, string> */
    public static function offerCategories(WP_Post $offer): array
    {
        $categories = [];
        $terms = get_the_terms($offer->ID, self::TAXONOMY);
        foreach (is_array($terms) ? $terms : [] as $term) {
            if ($term instanceof WP_Term) {
                $categories['term-' . $term->term_id] = $term->name;
            }
        }

        return $categories;
    }

    /** @param array<string, string> $categories */
    public static function selectedCategory(array $categories): string
    {
        $category = $_GET['offer_category'] ?? null;
        $category = is_string($category) ? sanitize_key(wp_unslash($category)) : '';

        return isset($categories[$category]) ? $category : 'all';
    }

    /** @return array{previous: ?WP_Post, next: ?WP_Post}|array{} */
    public static function adjacentOffers(int $offerId): array
    {
        $landing = self::page();
        $pageId = $landing !== null && $landing->post_status === 'publish' ? $landing->ID : 0;
        $offers = self::posts($pageId);
        $sourceId = absint(get_post_meta($offerId, '_kc_translation_of', true));
        $position = array_search($sourceId ?: $offerId, wp_list_pluck($offers, 'ID'), true);

        if ($position === false) {
            return [];
        }

        $neighbors = [
            'previous' => $offers[$position - 1] ?? null,
            'next' => $offers[$position + 1] ?? null,
        ];
        $language = $sourceId > 0 ? get_post_meta($offerId, '_kc_language', true) : '';
        if (is_string($language) && $language !== '' && class_exists(Kampachi_Translations::class)) {
            foreach ($neighbors as $direction => $offer) {
                if ($offer === null) {
                    continue;
                }

                $translationId = Kampachi_Translations::translated_post_id($offer->ID, $language);
                $translation = $translationId > 0 ? get_post($translationId) : null;
                if ($translation instanceof WP_Post && $translation->post_status === 'publish') {
                    $neighbors[$direction] = $translation;
                }
            }
        }

        return $neighbors;
    }

    public static function enqueue(): void
    {
        parent::enqueue();
        Kampachi_Homepage::enqueue_hero_editor();
    }

    protected static function renderHero(WP_Post $page): void
    {
        $settings = self::heroSettings($page->ID);
        $source = $settings['image_source'] ?? 'manual';
        echo '<div data-kc-offer-hero-form>';
        echo '<div class="kc-field"><label class="kc-label" for="kc_offer_hero_source">Image source</label>';
        echo '<select id="kc_offer_hero_source" name="' . esc_attr(self::HERO_FORM . '[image_source]')
            . '" data-kc-hero-source>';
        echo '<option value="manual"' . selected($source, 'manual', false) . '>Choose images from the Media Library</option>';
        echo '<option value="posts"' . selected($source, 'posts', false) . '>Use hero images from selected offers</option>';
        echo '</select></div>';
        Kampachi_Homepage::render_hero_fields($settings, self::HERO_FORM, $source === 'posts', showMode: false);
        echo '<div data-kc-hero-source-panel="posts"' . ($source === 'posts' ? '' : ' hidden') . '>';
        echo '<p class="description">Each offer uses its saved hero image order and links through its title.</p>';
        Kampachi_Homepage::post_picker(
            self::posts($page->ID, true),
            self::offerIds($settings['offer_ids'] ?? []),
            'offer_ids',
            'Choose an offer',
            self::HERO_FORM
        );
        echo '</div></div>';
    }

    protected static function saveLandingFields(WP_Post $page, array $landing): void
    {
        $raw = $_POST[self::HERO_FORM] ?? null;
        if (! is_array($raw)) {
            parent::saveLandingFields($page, $landing);
            return;
        }

        unset($landing['hero_image_ids'], $landing['hero_video_id']);
        Kampachi_Landings::save_fields($page, $landing);
        $raw = wp_unslash($raw);
        $videoId = isset($raw['hero_video_id']) && is_scalar($raw['hero_video_id']) ? absint($raw['hero_video_id']) : 0;
        $settings = [
            'hero_mode' => 'mixed',
            'hero_video_id' => self::videoSlide($videoId) !== [] ? $videoId : 0,
            'hero_slides' => self::imageRows($raw['hero_slides'] ?? []),
            'image_source' => ($raw['image_source'] ?? '') === 'posts' ? 'posts' : 'manual',
            'offer_ids' => self::offerIds($raw['offer_ids'] ?? []),
        ];

        if ($settings === [
            'hero_mode' => 'mixed',
            'hero_video_id' => 0,
            'hero_slides' => [],
            'image_source' => 'manual',
            'offer_ids' => [],
        ] && self::heroSettings($page->ID) === []) {
            return;
        }

        update_post_meta($page->ID, self::HERO_META, wp_slash($settings));
    }

    public static function heroSettings(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }

        $saved = get_post_meta($pageId, self::HERO_META, true);
        if (is_array($saved)) {
            return $saved;
        }

        $sourceId = absint(get_post_meta($pageId, '_kc_translation_of', true));
        if ($sourceId > 0 && in_array(get_post_type($sourceId), ['page', Kampachi_Landings::POST_TYPE], true)) {
            $saved = get_post_meta($sourceId, self::HERO_META, true);
            if (is_array($saved)) {
                return $saved;
            }

            if (! metadata_exists('post', $pageId, self::LEGACY_IMAGES)
                && ! metadata_exists('post', $pageId, self::LEGACY_VIDEO)) {
                $pageId = $sourceId;
            }
        }

        if (! metadata_exists('post', $pageId, self::LEGACY_IMAGES)
            && ! metadata_exists('post', $pageId, self::LEGACY_VIDEO)) {
            return [];
        }

        $legacyIds = get_post_meta($pageId, self::LEGACY_IMAGES, true);
        $rows = [];
        foreach (is_array($legacyIds) ? $legacyIds : [] as $id) {
            $imageId = absint($id);
            $rows[] = ['image_id' => $imageId, 'direction' => HeroMotion::direction($pageId, $imageId)];
        }
        $rows = self::imageRows($rows);

        return [
            'hero_mode' => self::manualImages($rows) !== [] ? 'images' : 'video',
            'hero_video_id' => absint(get_post_meta($pageId, self::LEGACY_VIDEO, true)),
            'hero_slides' => $rows,
            'image_source' => 'manual',
            'offer_ids' => [],
        ];
    }

    /** @return list<array<string, string>> */
    public static function heroSlides(array $settings): array
    {
        $mode = Kampachi_Homepage::hero_mode($settings);
        $video = $mode !== 'images' ? self::videoSlide(absint($settings['hero_video_id'] ?? 0)) : [];
        $images = [];
        if ($mode !== 'video') {
            $images = ($settings['image_source'] ?? '') === 'posts'
                ? self::offerImages(self::offerIds($settings['offer_ids'] ?? []))
                : self::manualImages(self::imageRows($settings['hero_slides'] ?? []));
        }

        return Kampachi_Homepage::displayed_slides(['hero_mode' => $mode], $video === [] ? [] : [$video], $images);
    }

    /** @return array{url: string, mime: string}|array{} */
    private static function videoSlide(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        $mime = get_post_mime_type($id);
        $url = wp_get_attachment_url($id);

        return is_string($mime) && str_starts_with($mime, 'video/') && is_string($url) && $url !== ''
            ? ['url' => $url, 'mime' => $mime]
            : [];
    }

    /** @return list<array{image_id: int, direction: string, title: string}> */
    private static function imageRows(mixed $input): array
    {
        $rows = [];
        foreach (is_array($input) ? $input : [] as $row) {
            if (! is_array($row) || ! isset($row['image_id']) || ! is_scalar($row['image_id'])) {
                continue;
            }

            $id = absint($row['image_id']);
            if ($id > 0 && wp_attachment_is_image($id)) {
                $rows[] = [
                    'image_id' => $id,
                    'direction' => HeroMotion::normalize($row['direction'] ?? null),
                    'title' => is_string($row['title'] ?? null) ? sanitize_text_field($row['title']) : '',
                ];
            }
        }

        return $rows;
    }

    /** @return list<int> */
    private static function offerIds(mixed $input): array
    {
        $ids = [];
        foreach (is_string($input) ? explode(',', $input) : (is_array($input) ? $input : []) as $value) {
            $id = is_scalar($value) ? absint($value) : 0;
            if ($id > 0 && ! isset($ids[$id]) && get_post_type($id) === Offers::POST_TYPE
                && get_post_status($id) === 'publish') {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /** @return list<array<string, string>> */
    private static function manualImages(array $rows): array
    {
        $slides = [];
        foreach ($rows as $row) {
            $url = wp_get_attachment_image_url($row['image_id'], 'full');
            if (is_string($url) && $url !== '') {
                $slides[] = ['url' => $url, 'direction' => $row['direction'], 'title' => $row['title']];
            }
        }

        return $slides;
    }

    /** @return list<array<string, string>> */
    private static function offerImages(array $offerIds): array
    {
        $slides = [];
        foreach ($offerIds as $offerId) {
            $title = get_the_title($offerId);
            $link = get_permalink($offerId);
            if (! is_string($link) || $link === '') {
                continue;
            }

            foreach (Offers::hero($offerId) as $media) {
                if ($media['type'] === 'image') {
                    $slides[] = [
                        'url' => $media['url'],
                        'direction' => $media['direction'],
                        'alt' => $media['alt'],
                        'title' => $title,
                        'link' => $link,
                    ];
                }
            }
        }

        return $slides;
    }
}
