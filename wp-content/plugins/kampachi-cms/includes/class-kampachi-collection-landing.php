<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use Kampachi_Landings;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

abstract class CollectionLanding
{
    protected const KEY = '';
    protected const TITLE = '';
    protected const ITEMS_LABEL = '';
    protected const ITEMS_TITLE = 'Dynamic Items';
    protected const ITEMS_HELP = '';
    protected const POST_TYPE = '';
    protected const PAGE_SLUG = '';
    protected const PAGE_TITLE = '';
    protected const TAXONOMY = '';
    protected const HAS_ARCHIVE = false;
    protected const HIGHLIGHT_META = '';
    protected const HAS_BODY_EDITOR = false;

    private static function action(): string
    {
        return 'kampachi_save_' . static::KEY . '_landing';
    }

    private static function inputName(): string
    {
        return 'kampachi_' . static::KEY . '_landing';
    }

    private static function metaKey(): string
    {
        return '_' . self::inputName();
    }

    public static function boot(): void
    {
        add_action('admin_post_' . self::action(), [static::class, 'save']);
        add_action('wp_head', [static::class, 'renderOpenGraph'], 6);
        add_filter('wpseo_opengraph_image', [static::class, 'filterSocialImage']);
        add_filter('rank_math/opengraph/facebook/image', [static::class, 'filterSocialImage']);
        add_filter('aioseo_facebook_tags', [static::class, 'filterFacebookTags']);

        if (static::PAGE_TITLE !== '') {
            add_action('admin_post_' . self::createAction(), [static::class, 'create']);
        }
    }

    public static function load(): void
    {
        add_action('admin_enqueue_scripts', [static::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        Kampachi_Landings::enqueue_media();
        $directory = dirname(__DIR__) . '/assets/';
        wp_enqueue_style(
            'kampachi-collection-landing',
            plugins_url('../assets/collection-landing-admin.css', __FILE__),
            [],
            (string) filemtime($directory . 'collection-landing-admin.css')
        );
        wp_enqueue_script(
            'kampachi-collection-landing',
            plugins_url('../assets/collection-landing-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable', 'kampachi-landings-admin'],
            (string) filemtime($directory . 'collection-landing-admin.js'),
            true
        );
    }

    protected static function page(): ?WP_Post
    {
        $page = Kampachi_Landings::page(static::PAGE_SLUG);

        return $page instanceof WP_Post && $page->post_status !== 'trash' ? $page : null;
    }

    protected static function adminUrl(): string
    {
        return add_query_arg(
            ['post_type' => static::POST_TYPE, 'page' => 'kampachi-landing-' . static::PAGE_SLUG],
            admin_url('edit.php')
        );
    }

    /** @return array<string, mixed> */
    private static function settings(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }

        $settings = get_post_meta($pageId, self::metaKey(), true);
        if (! is_array($settings)) {
            $source = absint(get_post_meta($pageId, '_kc_translation_of', true));
            $settings = $source > 0 ? get_post_meta($source, self::metaKey(), true) : [];
        }

        return is_array($settings) ? $settings : [];
    }

    /** @return list<WP_Post> */
    public static function posts(int $pageId, bool $includeHidden = false): array
    {
        $settings = self::settings($pageId);
        $hidden = $includeHidden ? [] : array_fill_keys(self::ids($settings['hidden'] ?? []), true);
        $posts = get_posts([
            'post_type' => static::POST_TYPE,
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
        $terms = get_terms(['taxonomy' => static::TAXONOMY, 'hide_empty' => false]);
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
            wp_die(
                esc_html__('You do not have permission to edit this landing page.', 'kampachi-cms'),
                '',
                ['response' => 403]
            );
        }

        $page = self::page();
        $canEdit = $page !== null && current_user_can('edit_post', $page->ID);
        echo '<div class="wrap kc-home-admin kc-shared-admin kc-collection-landing">';
        echo '<header class="kc-page-header"><h1>' . esc_html(static::TITLE) . '</h1>';
        if ($canEdit) {
            $url = $page->post_status === 'publish' ? get_permalink($page) : get_preview_post_link($page);
            echo '<a class="button kc-view-site" href="' . esc_url($url) . '" target="_blank" rel="noopener">';
            echo $page->post_status === 'publish' ? 'View landing page' : 'Preview landing page';
            echo '</a>';
            if (static::HAS_BODY_EDITOR) {
                echo '<a class="button" href="' . esc_url(get_edit_post_link($page->ID, 'raw') ?: '') . '">'
                    . esc_html__('Edit full page content', 'kampachi-cms') . '</a>';
            }
        }
        echo '</header>';

        if ($page === null) {
            static::renderMissingPage();
            echo '</div>';

            return;
        }
        if (! $canEdit) {
            echo '<p>You need edit access to this landing page.</p></div>';

            return;
        }

        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(static::TITLE) . ' saved.</p></div>';
        }

        echo '<nav class="kc-section-nav" aria-label="' . esc_attr(static::TITLE . ' sections') . '">';
        $sections = [
            'hero' => 'Hero',
            'introduction' => 'Introduction',
            'items' => static::ITEMS_TITLE,
            'seo' => 'SEO & Social Sharing',
        ];
        foreach ($sections as $id => $label) {
            echo '<a href="#kc-collection-' . esc_attr($id) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav><form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" data-kc-collection-landing>';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::action()) . '">';
        wp_nonce_field(self::action());

        echo '<section id="kc-collection-hero" class="kc-panel"><h2>Hero</h2>';
        static::renderHero($page);
        echo '</section>';

        self::renderIntroduction($page);
        self::renderItems($page->ID);
        self::renderSeo($page);
        echo '<div class="kc-save-bar"><strong>' . esc_html(static::TITLE) . '</strong>';
        echo '<button type="submit" class="button button-primary">Save Landing Page</button></div></form></div>';
    }

    protected static function renderHero(WP_Post $page): void
    {
        echo '<input type="hidden" name="kampachi_landing[hero_touched]" value="0" data-kc-collection-hero-touched>';
        Kampachi_Landings::render_hero($page);
    }

    protected static function renderMissingPage(): void
    {
        if (static::PAGE_TITLE === '') {
            echo '<p>Create a WordPress page with the slug <code>' . esc_html(static::PAGE_SLUG) . '</code> first.</p>';

            return;
        }

        if (! self::canCreate()) {
            echo '<p>Ask a user who can publish pages to set up this landing page.</p>';

            return;
        }

        echo '<section class="kc-panel"><h2>Set up the landing page</h2>';
        echo '<p>Create and publish this landing page with the current published ' . esc_html(self::itemsLabel()) . '.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::createAction()) . '">';
        wp_nonce_field(self::createAction());
        submit_button('Create ' . static::TITLE . ' Page');
        echo '</form></section>';
    }

    private static function createAction(): string
    {
        return 'kampachi_create_' . static::KEY . '_landing';
    }

    private static function canCreate(): bool
    {
        return static::PAGE_TITLE !== '' && current_user_can('edit_pages') && current_user_can('publish_pages');
    }

    public static function create(): void
    {
        if (! self::canCreate()) {
            wp_die(
                esc_html__('You do not have permission to publish this landing page.', 'kampachi-cms'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(self::createAction());
        if (self::page() === null) {
            $result = wp_insert_post([
                'post_type' => Kampachi_Landings::POST_TYPE,
                'post_status' => 'publish',
                'post_name' => static::PAGE_SLUG,
                'post_title' => static::PAGE_TITLE,
            ], true);
            if (is_wp_error($result)) {
                wp_die(esc_html($result->get_error_message()));
            }
        }

        wp_safe_redirect(static::adminUrl());
        exit;
    }

    private static function itemsLabel(): string
    {
        return static::ITEMS_LABEL !== '' ? static::ITEMS_LABEL : static::KEY;
    }

    private static function renderIntroduction(WP_Post $page): void
    {
        $pageId = $page->ID;
        $title = (string) get_post_meta($pageId, '_kampachi_intro_title', true);
        $subtitle = (string) get_post_meta($pageId, '_kampachi_intro_subtitle', true);
        $body = (string) get_post_meta($pageId, '_kampachi_intro_description', true);
        echo '<section id="kc-collection-introduction" class="kc-panel"><h2>Introduction</h2>';
        echo '<div class="kc-field"><label class="kc-label" for="kc-collection-heading">Heading</label>';
        echo '<input class="widefat" type="text" id="kc-collection-heading" name="kampachi_landing[intro_title]" value="'
            . esc_attr($title) . '"></div>';
        echo '<div class="kc-field"><label class="kc-label" for="kc-collection-subheading">Subheading</label>';
        echo '<textarea class="widefat" id="kc-collection-subheading" name="kampachi_landing[intro_subtitle]" rows="2">'
            . esc_textarea($subtitle) . '</textarea></div>';

        if (static::HAS_BODY_EDITOR) {
            if (trim($body) !== '') {
                self::renderIntroEditor(
                    $body,
                    'kc_collection_intro_summary',
                    'intro_description',
                    __('Introduction Summary', 'kampachi-cms')
                );
            }
            echo '<input type="hidden" name="kampachi_landing[page_content_version]" value="'
                . esc_attr(hash('sha256', $page->post_content)) . '">';
        }

        self::renderIntroEditor(
            static::HAS_BODY_EDITOR ? $page->post_content : $body,
            'kc_collection_intro_body',
            static::HAS_BODY_EDITOR ? 'page_content' : 'intro_description',
            __('Introduction Body', 'kampachi-cms')
        );
        echo '</section>';
    }

    private static function renderIntroEditor(string $body, string $id, string $field, string $label): void
    {
        echo '<div class="kc-field"><label class="kc-label" for="' . esc_attr($id) . '">'
            . esc_html($label) . '</label>';
        wp_editor($body, $id, [
            'textarea_name' => 'kampachi_landing[' . $field . ']',
            'textarea_rows' => 7,
            'media_buttons' => current_user_can('upload_files'),
        ]);
        echo '</div>';
    }

    private static function renderItems(int $pageId): void
    {
        $posts = self::posts($pageId, true);
        $categories = static::TAXONOMY !== '' ? self::orderCategories($pageId, self::availableCategories()) : [];
        $settings = self::settings($pageId);
        $ids = array_map(static fn (WP_Post $post): int => $post->ID, $posts);
        $hidden = array_intersect(self::ids($settings['hidden'] ?? []), $ids);

        echo '<section id="kc-collection-items" class="kc-panel"><h2>' . esc_html(static::ITEMS_TITLE) . '</h2>';
        $itemsLabel = self::itemsLabel();
        $help = static::ITEMS_HELP ?: 'Drag ' . $itemsLabel
            . ' to change their order. Clear Show to hide an item on this landing page.';
        echo '<p>' . esc_html($help) . '</p>';
        $fields = ['order' => $ids, 'hidden' => $hidden] + static::extraItemFields();
        if ($categories !== []) {
            $fields['categories'] = array_keys($categories);
        }
        foreach ($fields as $key => $values) {
            echo '<input type="hidden" name="' . esc_attr(self::inputName() . '[' . $key . ']')
                . '" data-kc-collection-field="' . esc_attr($key) . '" value="' . esc_attr(implode(',', $values)) . '">';
        }
        if ($categories !== []) {
            self::renderCategories($categories);
        }
        echo '<div class="kc-field"><label class="kc-label" for="kc-collection-landing-search">Search '
            . esc_html($itemsLabel) . '</label>';
        echo '<input type="search" id="kc-collection-landing-search" data-kc-collection-search></div>';
        echo '<ul class="kc-collection-landing-posts" data-kc-collection-order aria-label="'
            . esc_attr(ucfirst($itemsLabel) . ' on the landing page') . '">';
        foreach ($posts as $post) {
            self::renderItem($post, in_array($post->ID, $hidden, true));
        }
        echo '</ul><p role="status" data-kc-collection-empty' . ($posts === [] ? '' : ' hidden')
            . '>No ' . esc_html($itemsLabel) . ' match this view.</p></section>';
    }

    /** @return array<string, array<int, int|string>> */
    protected static function extraItemFields(): array
    {
        return static::HIGHLIGHT_META !== '' ? ['highlight_changes' => []] : [];
    }

    protected static function renderItemControls(WP_Post $post): void
    {
        if (static::HIGHLIGHT_META === '') {
            return;
        }

        echo '<label><input type="checkbox" data-kc-collection-highlight'
            . checked(static::isHighlight($post->ID), true, false)
            . disabled(! current_user_can('edit_post', $post->ID), true, false)
            . ' aria-label="' . esc_attr('Highlight ' . get_the_title($post)) . '"> Highlight</label>';
    }

    /** @param array<string, string> $categories */
    private static function renderCategories(array $categories): void
    {
        echo '<h3 id="kc-collection-category-heading">Category Tab Order</h3>';
        echo '<ul class="kc-collection-landing-category-order" data-kc-collection-category-order'
            . ' aria-labelledby="kc-collection-category-heading">';
        foreach ($categories as $key => $label) {
            echo '<li data-category="' . esc_attr($key) . '">';
            self::reorderControls($label);
            echo '<span>' . esc_html($label) . '</span></li>';
        }
        echo '</ul><div class="nav-tab-wrapper" role="group" aria-label="Filter offers by category" data-kc-collection-tabs>';
        foreach ($categories as $key => $label) {
            $active = $key === 'all';
            echo '<button type="button" class="nav-tab' . ($active ? ' nav-tab-active' : '')
                . '" data-category="' . esc_attr($key) . '" aria-pressed="' . ($active ? 'true' : 'false') . '">'
                . esc_html($label) . '</button>';
        }
        echo '</div>';
    }

    private static function reorderControls(string $label): void
    {
        echo '<span class="dashicons dashicons-move kc-collection-landing-handle" aria-hidden="true"></span>';
        echo '<span class="kc-collection-landing-move">';
        foreach (['up' => '↑', 'down' => '↓'] as $direction => $glyph) {
            echo '<button type="button" class="button" data-kc-landing-move="' . esc_attr($direction)
                . '" aria-label="' . esc_attr(sprintf('Move %s %s', $label, $direction)) . '">' . esc_html($glyph)
                . '</button>';
        }
        echo '</span>';
    }

    private static function renderItem(WP_Post $post, bool $hidden): void
    {
        $terms = static::TAXONOMY !== '' ? get_the_terms($post->ID, static::TAXONOMY) : [];
        $keys = [];
        $names = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            if ($term instanceof WP_Term) {
                $keys[] = 'term-' . $term->term_id;
                $names[] = $term->name;
            }
        }
        $title = static::POST_TYPE === Outlets::POST_TYPE ? Outlets::locationName($post) : get_the_title($post);
        echo '<li data-item="' . esc_attr((string) $post->ID) . '" data-categories="'
            . esc_attr(implode(' ', $keys)) . '">';
        self::reorderControls($title);
        echo '<div class="kc-collection-landing-post-label"><strong>' . esc_html($title) . '</strong>';
        echo '<span class="description">' . esc_html(implode(', ', $names)) . '</span></div>';
        echo '<div class="kc-collection-item-options"><label><input type="checkbox" data-kc-collection-visible'
            . checked(! $hidden, true, false) . ' aria-label="' . esc_attr('Show ' . $title) . '"> Show</label>';
        static::renderItemControls($post);
        echo '</div></li>';
    }

    private static function renderSeo(WP_Post $page): void
    {
        $id = absint(self::settings($page->ID)['image'] ?? 0);
        $image = $id > 0 ? wp_get_attachment_image_url($id, 'full') : false;
        echo '<section id="kc-collection-seo" class="kc-panel"><h2>SEO &amp; Social Sharing</h2>';
        Seo::renderBox($page);
        echo '<div class="kc-field kc-collection-landing-og"><h3>OG Image</h3>';
        echo '<input type="hidden" name="' . esc_attr(self::inputName() . '[image]') . '" value="' . esc_attr((string) $id)
            . '" data-kc-collection-og-id>';
        echo '<button type="button" class="button" data-kc-collection-og-choose>Upload / Choose OG Image</button> ';
        echo '<button type="button" class="button" data-kc-collection-og-clear' . ($image ? '' : ' hidden')
            . '>Remove</button>';
        echo '<img class="kc-collection-landing-og-preview" data-kc-collection-og-preview alt="Social sharing image preview"'
            . ($image ? ' src="' . esc_url($image) . '"' : ' hidden') . '>';
        echo '<p class="description">Recommended image size is 1200 × 630 px.</p></div></section>';
    }

    public static function save(): void
    {
        $page = self::page();
        if ($page === null || ! current_user_can('edit_pages') || ! current_user_can('edit_post', $page->ID)) {
            wp_die(
                esc_html__('You do not have permission to save this landing page.', 'kampachi-cms'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(self::action());
        $landing = isset($_POST['kampachi_landing']) && is_array($_POST['kampachi_landing'])
            ? wp_unslash($_POST['kampachi_landing'])
            : [];
        if (static::HAS_BODY_EDITOR) {
            self::savePageContent($page, $landing);
        }
        static::saveLandingFields($page, $landing);
        Seo::savePost($page->ID, $page);

        $input = $_POST[self::inputName()] ?? null;
        $raw = is_array($input) ? wp_unslash($input) : [];
        if ($raw !== []) {
            self::saveItems($page->ID, $raw);
        }

        wp_safe_redirect(add_query_arg('updated', '1', static::adminUrl()));
        exit;
    }

    private static function savePageContent(WP_Post $page, array $landing): void
    {
        $content = $landing['page_content'] ?? null;
        $version = $landing['page_content_version'] ?? null;
        if (! is_string($content) || $content === $page->post_content || hash('sha256', $content) === $version) {
            return;
        }

        if (hash('sha256', $page->post_content) !== $version) {
            wp_die(
                esc_html__('The page content has changed. Reload the landing editor before saving it again.', 'kampachi-cms'),
                '',
                ['response' => 409]
            );
        }

        $updated = wp_update_post(wp_slash(['ID' => $page->ID, 'post_content' => $content]), true);
        if (is_wp_error($updated)) {
            wp_die(esc_html($updated->get_error_message()));
        }
    }

    protected static function saveLandingFields(WP_Post $page, array $landing): void
    {
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
    }

    private static function saveItems(int $pageId, array $raw): void
    {
        $settings = self::settings($pageId);
        $eligible = array_map(static fn (WP_Post $post): int => $post->ID, self::posts($pageId, true));
        foreach (['order', 'hidden'] as $key) {
            if (is_string($raw[$key] ?? null)) {
                $settings[$key] = array_values(array_intersect(self::ids($raw[$key]), $eligible));
            }
        }
        if (static::TAXONOMY !== '' && is_string($raw['categories'] ?? null)) {
            $categories = ['all', ...array_keys(self::availableCategories())];
            $settings['categories'] = array_values(array_intersect(self::categoryKeys($raw['categories']), $categories));
        }
        if (is_string($raw['image'] ?? null)) {
            $image = absint($raw['image']);
            $settings['image'] = $image > 0 && wp_attachment_is_image($image) ? $image : 0;
        }
        update_post_meta($pageId, self::metaKey(), $settings);
        static::saveItemControls($raw, $eligible);
    }

    /** @param list<int> $eligible */
    protected static function saveItemControls(array $raw, array $eligible): void
    {
        $changes = $raw['highlight_changes'] ?? null;
        if (static::HIGHLIGHT_META === '' || ! is_string($changes) || $changes === '') {
            return;
        }

        $allowed = array_fill_keys($eligible, true);
        foreach (explode(',', $changes) as $change) {
            if (! preg_match('/^([1-9]\d*):([01])$/D', $change, $match)) {
                continue;
            }

            $postId = (int) $match[1];
            if (isset($allowed[$postId]) && current_user_can('edit_post', $postId)) {
                if ($match[2] === '1') {
                    update_post_meta($postId, static::HIGHLIGHT_META, '1');
                } else {
                    delete_post_meta($postId, static::HIGHLIGHT_META);
                }
            }
        }
    }

    public static function isHighlight(int $postId): bool
    {
        return static::HIGHLIGHT_META !== '' && get_post_meta($postId, static::HIGHLIGHT_META, true) === '1';
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
        if (self::isArchive()) {
            $page = self::page();

            return $page !== null && $page->post_status === 'publish' ? $page->ID : 0;
        }

        $post = is_singular(['page', Kampachi_Landings::POST_TYPE]) ? get_queried_object() : null;
        if (! $post instanceof WP_Post) {
            return 0;
        }
        $sourceId = absint(get_post_meta($post->ID, '_kc_translation_of', true));
        $slug = $sourceId > 0 ? get_post_field('post_name', $sourceId) : $post->post_name;

        return $slug === static::PAGE_SLUG ? $post->ID : 0;
    }

    private static function isArchive(): bool
    {
        return static::HAS_ARCHIVE && is_post_type_archive(static::POST_TYPE);
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
            'og:url' => self::isArchive() ? get_post_type_archive_link(static::POST_TYPE) : get_permalink($id),
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
