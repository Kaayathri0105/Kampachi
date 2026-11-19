<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use Kampachi_Landings;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

final class OffersLanding
{
    private const ACTION = 'kampachi_save_offers_landing';
    private const META_KEY = '_kampachi_offers_landing';
    private const TAXONOMY = 'kc_offer_category';
    private const PAGE_SLUG = 'promotion';

    public static function boot(): void
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
        add_action('wp_head', [self::class, 'renderOpenGraph'], 6);
        add_filter('wpseo_opengraph_image', [self::class, 'filterSocialImage']);
        add_filter('rank_math/opengraph/facebook/image', [self::class, 'filterSocialImage']);
        add_filter('aioseo_facebook_tags', [self::class, 'filterFacebookTags']);
    }

    public static function load(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        Kampachi_Landings::enqueue_media();
        $directory = dirname(__DIR__) . '/assets/';
        wp_enqueue_style(
            'kampachi-offers-landing',
            plugins_url('../assets/offers-landing-admin.css', __FILE__),
            [],
            (string) filemtime($directory . 'offers-landing-admin.css')
        );
        wp_enqueue_script(
            'kampachi-offers-landing',
            plugins_url('../assets/offers-landing-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable', 'kampachi-landings-admin'],
            (string) filemtime($directory . 'offers-landing-admin.js'),
            true
        );
    }

    private static function page(): ?WP_Post
    {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');

        return $page instanceof WP_Post && $page->post_status !== 'trash' ? $page : null;
    }

    private static function adminUrl(): string
    {
        return admin_url('edit.php?post_type=kc_offer&page=kampachi-landing-promotion');
    }

    /** @return array<string, mixed> */
    private static function settings(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }

        $settings = get_post_meta($pageId, self::META_KEY, true);
        if (! is_array($settings)) {
            $source = absint(get_post_meta($pageId, '_kc_translation_of', true));
            $settings = $source > 0 ? get_post_meta($source, self::META_KEY, true) : [];
        }

        return is_array($settings) ? $settings : [];
    }

    /** @return list<WP_Post> */
    public static function posts(int $pageId, bool $includeHidden = false): array
    {
        $settings = self::settings($pageId);
        $hidden = $includeHidden ? [] : array_fill_keys(self::ids($settings['hidden'] ?? []), true);
        $posts = get_posts([
            'post_type' => 'kc_offer',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC'],
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'suppress_filters' => false,
            'meta_query' => [['key' => '_kc_translation_of', 'compare' => 'NOT EXISTS']],
        ]);

        $available = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post && ! isset($hidden[$post->ID])) {
                $available[$post->ID] = $post;
            }
        }

        $ordered = [];
        foreach (self::ids($settings['order'] ?? []) as $id) {
            if (isset($available[$id])) {
                $ordered[] = $available[$id];
                unset($available[$id]);
            }
        }

        return array_merge($ordered, array_values($available));
    }

    /**
     * @param array<string, string> $categories
     * @return array<string, string>
     */
    public static function orderCategories(int $pageId, array $categories): array
    {
        asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
        $categories = ['all' => 'All Offers'] + $categories;
        $settings = self::settings($pageId);
        $order = self::categoryKeys($settings['categories'] ?? []);

        return array_replace(array_intersect_key(array_fill_keys($order, ''), $categories), $categories);
    }

    /** @return array<string, string> */
    private static function availableCategories(): array
    {
        $result = [];
        $terms = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false]);
        foreach (is_array($terms) ? $terms : [] as $term) {
            if ($term instanceof WP_Term) {
                $result['term-' . $term->term_id] = $term->name;
            }
        }

        return $result;
    }

    public static function render(): void
    {
        if (! current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to edit Offers Landing.', 'kampachi-cms'), '', ['response' => 403]);
        }

        $page = self::page();
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-offers-landing">';
        echo '<header class="kc-page-header"><h1>Offers Landing</h1>';
        if ($page !== null && current_user_can('edit_post', $page->ID)) {
            $url = $page->post_status === 'publish' ? get_permalink($page) : get_preview_post_link($page);
            echo '<a class="button kc-view-site" href="' . esc_url($url) . '" target="_blank" rel="noopener">';
            echo $page->post_status === 'publish' ? 'View landing page' : 'Preview landing page';
            echo '</a>';
        }
        echo '</header>';

        if ($page === null || ! current_user_can('edit_post', $page->ID)) {
            echo '<p>' . ($page === null ? 'Create a WordPress page with the slug <code>promotion</code> first.'
                : 'You need edit access to the Promotions page.') . '</p></div>';

            return;
        }

        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Offers Landing saved.</p></div>';
        }

        echo '<nav class="kc-section-nav" aria-label="Offers Landing sections">';
        foreach (['hero' => 'Hero', 'introduction' => 'Introduction', 'items' => 'Dynamic Items', 'seo' => 'SEO & Social Sharing'] as $id => $label) {
            echo '<a href="#kc-offers-' . esc_attr($id) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav><form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" id="kc-offers-landing-form">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        wp_nonce_field(self::ACTION);

        echo '<section id="kc-offers-hero" class="kc-panel"><h2>Hero</h2>';
        echo '<input type="hidden" name="kampachi_landing[hero_touched]" id="kc-offers-hero-touched" value="0">';
        Kampachi_Landings::render_hero($page);
        echo '</section>';

        self::renderIntroduction($page->ID);
        self::renderItems($page->ID);
        self::renderSeo($page);
        echo '<div class="kc-save-bar"><strong>Offers Landing</strong>';
        echo '<button type="submit" class="button button-primary">Save Landing Page</button></div></form></div>';
    }

    private static function renderIntroduction(int $pageId): void
    {
        $title = (string) get_post_meta($pageId, '_kampachi_intro_title', true);
        $subtitle = (string) get_post_meta($pageId, '_kampachi_intro_subtitle', true);
        $body = (string) get_post_meta($pageId, '_kampachi_intro_description', true);
        echo '<section id="kc-offers-introduction" class="kc-panel"><h2>Introduction</h2>';
        echo '<div class="kc-field"><label class="kc-label" for="kc-offers-heading">Heading</label>';
        echo '<input class="widefat" type="text" id="kc-offers-heading" name="kampachi_landing[intro_title]" value="'
            . esc_attr($title) . '"></div>';
        echo '<div class="kc-field"><label class="kc-label" for="kc-offers-subheading">Subheading</label>';
        echo '<textarea class="widefat" id="kc-offers-subheading" name="kampachi_landing[intro_subtitle]" rows="2">'
            . esc_textarea($subtitle) . '</textarea></div>';
        echo '<div class="kc-field"><label class="kc-label" for="kc_offers_intro_body">Introduction Body</label>';
        wp_editor($body, 'kc_offers_intro_body', [
            'textarea_name' => 'kampachi_landing[intro_description]',
            'textarea_rows' => 7,
            'media_buttons' => current_user_can('upload_files'),
        ]);
        echo '</div></section>';
    }

    private static function renderItems(int $pageId): void
    {
        $posts = self::posts($pageId, true);
        $categories = self::orderCategories($pageId, self::availableCategories());
        $settings = self::settings($pageId);
        $ids = array_map(static fn (WP_Post $post): int => $post->ID, $posts);
        $hidden = array_intersect(self::ids($settings['hidden'] ?? []), $ids);

        echo '<section id="kc-offers-items" class="kc-panel" data-kc-landing-items><h2>Dynamic Items</h2>';
        echo '<p>Drag offers to change their order on the landing page. Clear Show to hide an offer there.</p>';
        foreach (['order' => $ids, 'hidden' => $hidden, 'categories' => array_keys($categories)] as $key => $values) {
            echo '<input type="hidden" name="kampachi_offers_landing[' . esc_attr($key) . ']" data-kc-offers-field="'
                . esc_attr($key) . '" value="' . esc_attr(implode(',', $values)) . '">';
        }

        echo '<h3 id="kc-offers-category-heading">Category Tab Order</h3>';
        echo '<ul class="kc-offers-landing-category-order" data-kc-offers-category-order'
            . ' aria-labelledby="kc-offers-category-heading">';
        foreach ($categories as $key => $label) {
            echo '<li data-category="' . esc_attr($key) . '">';
            self::reorderControls($label);
            echo '<span>' . esc_html($label) . '</span></li>';
        }
        echo '</ul><div class="nav-tab-wrapper" role="group" aria-label="Filter offers by category" data-kc-offers-tabs>';
        foreach ($categories as $key => $label) {
            $active = $key === 'all';
            echo '<button type="button" class="nav-tab' . ($active ? ' nav-tab-active' : '')
                . '" data-category="' . esc_attr($key) . '" aria-pressed="' . ($active ? 'true' : 'false') . '">'
                . esc_html($label) . '</button>';
        }
        echo '</div><div class="kc-field">';
        echo '<label class="kc-label" for="kc-offers-landing-search">Find an offer</label>';
        echo '<input type="search" id="kc-offers-landing-search" placeholder="Search All Offers" data-kc-offers-search>';
        echo '</div><ul class="kc-offers-landing-posts" data-kc-offers-order aria-label="Offers on the landing page">';
        foreach ($posts as $post) {
            self::renderOffer($post, in_array($post->ID, $hidden, true));
        }
        echo '</ul><p role="status" data-kc-offers-empty' . ($posts === [] ? '' : ' hidden')
            . '>No offers match this view.</p></section>';
    }

    private static function reorderControls(string $label): void
    {
        echo '<span class="dashicons dashicons-move kc-offers-landing-handle" aria-hidden="true"></span>';
        echo '<span class="kc-offers-landing-move">';
        foreach (['up' => '↑', 'down' => '↓'] as $direction => $glyph) {
            echo '<button type="button" class="button" data-kc-landing-move="' . esc_attr($direction)
                . '" aria-label="' . esc_attr(sprintf('Move %s %s', $label, $direction)) . '">' . esc_html($glyph)
                . '</button>';
        }
        echo '</span>';
    }

    private static function renderOffer(WP_Post $post, bool $hidden): void
    {
        $terms = get_the_terms($post->ID, self::TAXONOMY);
        $keys = [];
        $names = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            if ($term instanceof WP_Term) {
                $keys[] = 'term-' . $term->term_id;
                $names[] = $term->name;
            }
        }
        $title = get_the_title($post);
        echo '<li data-offer="' . esc_attr((string) $post->ID) . '" data-categories="'
            . esc_attr(implode(' ', $keys)) . '">';
        self::reorderControls($title);
        echo '<div class="kc-offers-landing-post-label"><strong>' . esc_html($title) . '</strong>';
        echo '<span class="description">' . esc_html(implode(', ', $names)) . '</span></div>';
        echo '<label><input type="checkbox" data-kc-offers-visible' . checked(! $hidden, true, false)
            . ' aria-label="' . esc_attr('Show ' . $title) . '"> Show</label></li>';
    }

    private static function renderSeo(WP_Post $page): void
    {
        $id = absint(self::settings($page->ID)['image'] ?? 0);
        $image = $id > 0 ? wp_get_attachment_image_url($id, 'full') : false;
        echo '<section id="kc-offers-seo" class="kc-panel"><h2>SEO &amp; Social Sharing</h2>';
        Seo::renderBox($page);
        echo '<div class="kc-field kc-offers-landing-og"><h3>OG Image</h3>';
        echo '<input type="hidden" name="kampachi_offers_landing[image]" value="' . esc_attr((string) $id)
            . '" data-kc-offers-og-id>';
        echo '<button type="button" class="button" data-kc-offers-og-choose>Upload / Choose OG Image</button> ';
        echo '<button type="button" class="button" data-kc-offers-og-clear' . ($image ? '' : ' hidden')
            . '>Remove</button>';
        echo '<img class="kc-offers-landing-og-preview" data-kc-offers-og-preview alt="Social sharing image preview"'
            . ($image ? ' src="' . esc_url($image) . '"' : ' hidden') . '>';
        echo '<p class="description">Recommended image size is 1200 × 630 px.</p></div></section>';
    }

    public static function save(): void
    {
        $page = self::page();
        if ($page === null || ! current_user_can('edit_pages') || ! current_user_can('edit_post', $page->ID)) {
            wp_die(esc_html__('You do not have permission to save Offers Landing.', 'kampachi-cms'), '', ['response' => 403]);
        }

        check_admin_referer(self::ACTION);
        $landing = isset($_POST['kampachi_landing']) && is_array($_POST['kampachi_landing'])
            ? wp_unslash($_POST['kampachi_landing'])
            : [];
        if (($landing['hero_touched'] ?? '') !== '1') {
            if (! metadata_exists('post', $page->ID, '_kampachi_hero_image_ids')) {
                unset($landing['hero_image_ids']);
            }
            if (! metadata_exists('post', $page->ID, '_kampachi_hero_video_id')) {
                unset($landing['hero_video_id']);
            }
        }
        Kampachi_Landings::save_fields($page, $landing);
        $heroImages = get_post_meta($page->ID, '_kampachi_hero_image_ids', true);
        HeroMotion::save($page->ID, is_array($heroImages) ? $heroImages : []);
        Seo::savePost($page->ID, $page);

        $raw = isset($_POST['kampachi_offers_landing']) && is_array($_POST['kampachi_offers_landing'])
            ? wp_unslash($_POST['kampachi_offers_landing'])
            : [];
        if ($raw !== []) {
            self::saveItems($page->ID, $raw);
        }

        wp_safe_redirect(add_query_arg('updated', '1', self::adminUrl()));
        exit;
    }

    private static function saveItems(int $pageId, array $raw): void
    {
        $settings = self::settings($pageId);
        $eligible = array_map(static fn (WP_Post $post): int => $post->ID, self::posts($pageId, true));
        $categories = ['all' => 'All Offers'] + self::availableCategories();

        foreach (['order', 'hidden'] as $key) {
            if (is_string($raw[$key] ?? null)) {
                $settings[$key] = array_values(array_intersect(self::ids($raw[$key]), $eligible));
            }
        }
        if (is_string($raw['categories'] ?? null)) {
            $settings['categories'] = array_values(array_intersect(self::categoryKeys($raw['categories']), array_keys($categories)));
        }
        if (is_string($raw['image'] ?? null)) {
            $image = absint($raw['image']);
            $settings['image'] = $image > 0 && wp_attachment_is_image($image) ? $image : 0;
        }
        update_post_meta($pageId, self::META_KEY, $settings);
    }

    /** @return list<int> */
    private static function ids(mixed $value): array
    {
        $values = is_string($value) ? explode(',', $value) : $value;
        $ids = [];
        foreach (is_array($values) ? $values : [] as $id) {
            if (is_scalar($id) && preg_match('/^\d+$/D', (string) $id) === 1 && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    /** @return list<string> */
    private static function categoryKeys(mixed $value): array
    {
        $keys = is_string($value) ? explode(',', $value) : $value;
        $keys = array_filter(
            is_array($keys) ? $keys : [],
            static fn (mixed $key): bool => is_string($key) && preg_match('/^(?:all|term-[1-9]\d*)$/D', $key) === 1
        );

        return array_values(array_unique($keys));
    }

    public static function queriedPageId(): int
    {
        if (is_post_type_archive('kc_offer')) {
            $page = self::page();

            return $page !== null && $page->post_status === 'publish' ? $page->ID : 0;
        }

        $post = is_singular('page') ? get_queried_object() : null;
        if (! $post instanceof WP_Post) {
            return 0;
        }
        $sourceId = absint(get_post_meta($post->ID, '_kc_translation_of', true));
        $slug = $sourceId > 0 ? get_post_field('post_name', $sourceId) : $post->post_name;

        return $slug === self::PAGE_SLUG ? $post->ID : 0;
    }

    public static function filterSocialImage(mixed $image): mixed
    {
        $id = self::queriedPageId();
        $attachment = $id > 0 ? absint(self::settings($id)['image'] ?? 0) : 0;
        $url = $attachment > 0 ? wp_get_attachment_image_url($attachment, 'full') : false;

        return is_string($url) && $url !== '' ? $url : $image;
    }

    public static function filterFacebookTags(array $tags): array
    {
        $image = self::filterSocialImage('');
        if (is_string($image) && $image !== '') {
            $tags['og:image'] = $image;
        }

        return $tags;
    }

    public static function renderOpenGraph(): void
    {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || function_exists('aioseo')) {
            return;
        }

        $id = self::queriedPageId();
        if ($id === 0) {
            return;
        }
        $tags = [
            'og:type' => 'website',
            'og:url' => is_post_type_archive('kc_offer') ? get_post_type_archive_link('kc_offer') : get_permalink($id),
            'og:title' => Seo::filterTitle(get_the_title($id)),
            'og:description' => Seo::description($id),
            'og:image' => self::filterSocialImage(''),
        ];

        foreach ($tags as $property => $value) {
            if (is_string($value) && $value !== '') {
                echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($value) . '">' . "\n";
            }
        }
    }
}
