<?php
/**
 * Kampachi native theme integration.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

function kampachi_native_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', ['search-form', 'caption', 'gallery', 'style', 'script']);
    register_nav_menus([
        'primary' => __('Primary Navigation', 'kampachi-native'),
        'footer' => __('Footer Navigation', 'kampachi-native'),
    ]);
}
add_action('after_setup_theme', 'kampachi_native_setup');


function kampachi_native_nav_label(string $title): string
{
    return '<span class="nav-label-en">' . esc_html($title) . '</span>';
}

/**
 * Give the native WordPress menu fallback the same primary label markup.
 */
function kampachi_native_primary_nav_label(
    string $title,
    WP_Post $item,
    stdClass $args,
    int $depth
): string {
    if (($args->theme_location ?? '') !== 'primary' || $depth !== 0) {
        return $title;
    }

    return kampachi_native_nav_label($title);
}
add_filter('nav_menu_item_title', 'kampachi_native_primary_nav_label', 10, 4);

/**
 * Load the requested Cormorant Garamond and the final header/footer skin.
 * Late priority keeps it after module-specific stylesheets on WordPress pages.
 */
function kampachi_native_krsb_assets(): void
{
    wp_enqueue_style(
        'kampachi-cormorant-garamond',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&display=swap',
        [],
        null
    );

    $relative = 'assets/krsb-refresh.css';
    $path = get_theme_file_path($relative);

    if (!is_file($path)) {
        return;
    }

    wp_enqueue_style(
        'kampachi-krsb-refresh',
        get_theme_file_uri($relative),
        ['kampachi-shell', 'kampachi-cormorant-garamond'],
        (string) filemtime($path)
    );

    // Apply the same cinematic Ken Burns treatment across all image-based heroes.
    // Enqueue after the module styles; leave video banners and their playback intact.
    $heroStylesheet = 'assets/hero-kenburns.css';
    $heroPath = get_theme_file_path($heroStylesheet);

    if (is_file($heroPath)) {
        wp_enqueue_style(
            'kampachi-unified-hero-kenburns',
            get_theme_file_uri($heroStylesheet),
            ['kampachi-krsb-refresh'],
            (string) md5_file($heroPath)
        );
    }
}
add_action('wp_enqueue_scripts', 'kampachi_native_krsb_assets', 100);

/**
 * Match the Google Fonts resource hints supplied with the design reference.
 * WordPress emits these as preconnect tags in the document head.
 */
function kampachi_native_krsb_font_hints(array $urls, string $relationType): array
{
    if ($relationType !== 'preconnect') {
        return $urls;
    }

    $urls[] = 'https://fonts.googleapis.com';
    $urls[] = [
        'href' => 'https://fonts.gstatic.com',
        'crossorigin' => 'anonymous',
    ];

    return $urls;
}
add_filter('wp_resource_hints', 'kampachi_native_krsb_font_hints', 10, 2);


require_once get_theme_file_path('inc/class-homepage-content.php');

function kampachi_native_assets(): void
{
    wp_enqueue_style('kampachi-native', get_stylesheet_uri(), [], '2.7.1');
    $languageSource = absint(get_post_meta(get_queried_object_id(), '_kc_translation_of', true));
    if (is_front_page()
        || get_post_field('post_name', get_queried_object_id()) === 'home'
        || ($languageSource > 0 && (
            get_post_field('post_name', $languageSource) === 'home' || $languageSource === absint(get_option('page_on_front'))
        ))
    ) {
        $homeCss = get_theme_file_path('assets/home-cms.css');
        wp_enqueue_style('kampachi-home-cms', get_theme_file_uri('assets/home-cms.css'), ['kampachi-native'], (string) md5_file($homeCss));
    }
    foreach (['typography', 'site-theme', 'header', 'shell', 'discover'] as $name) {
        $relative = 'assets/' . $name . '.css';
        $path = get_theme_file_path($relative);
        if (is_file($path)) {
            wp_enqueue_style('kampachi-' . $name, get_theme_file_uri($relative), [], (string) filemtime($path));
        }
    }
    // Ensure scroll appearance and navigation controls work on EVERY CMS page,
    // not just legacy templates which manually include these scripts.
    foreach ([
        'kampachi-native-header-behavior' => 'assets/components/header-behavior.js',
        'kampachi-native-header-scroll' => 'assets/header-scroll.js',
        'kampachi-native-outlet-carousel' => 'assets/outlet-carousel.js',
        'kampachi-native-footer-enquiry' => 'assets/footer-enquiry.js',
    ] as $handle => $relative) {
        $path = get_theme_file_path($relative);
        if (is_file($path)) {
            wp_enqueue_script($handle, get_theme_file_uri($relative), [], (string) filemtime($path), true);
        }
    }
}
add_action('wp_enqueue_scripts', 'kampachi_native_assets');

function kampachi_native_enquiry_message(string $status): string
{
    return match ($status) {
        'sent' => __('Thank you. Your enquiry has been sent.', 'kampachi-native'),
        'invalid' => __('Please complete all required fields and try again.', 'kampachi-native'),
        'failed' => __('We could not send your enquiry. Please contact us directly by email.', 'kampachi-native'),
        'limited' => __('Too many submissions. Please try again later.', 'kampachi-native'),
        'unavailable' => __('Online enquiries are temporarily unavailable. Please contact us directly.', 'kampachi-native'),
        default => '',
    };
}

function kampachi_native_meta(int $postId, string $key): string
{
    $value = get_post_meta($postId, '_kampachi_' . $key, true);

    return is_scalar($value) ? (string) $value : '';
}

function kampachi_native_design(string $slug): bool
{
    $allowed = [
        'home', 'catering-events', 'contact-us', 'careers', 'privacy-policy',
        'about-us', '47in27', 'promotion', 'sake', 'eq', 'pavilion-kl', 'plaza-33',
    ];
    if (! in_array($slug, $allowed, true)) {
        return false;
    }

    $file = get_theme_file_path('templates/' . $slug . '.php');
    if (! is_file($file)) {
        return false;
    }

    require $file;

    return true;
}

/**
 * Render editor content for the current WP page, if present.
 */
function kampachi_render_editor_content(): void
{
    $post = get_post(get_queried_object_id());
    if (! $post instanceof WP_Post || ! in_array($post->post_type, ['page', 'kc_landing'], true)) {
        return;
    }

    if (trim($post->post_content) === '') {
        return;
    }

    echo '<div class="kampachi-editor-content">';
    echo apply_filters('the_content', $post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</div>';
}

/**
 * Read a text option from the standalone Kampachi CMS plugin.
 */
function kampachi_setting(string $key, string $default = ''): string
{
    $settings = get_option('kampachi_cms_settings', []);
    if (! is_array($settings)) {
        return $default;
    }

    $value = $settings[$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
}

function kampachi_native_label(string $key): string
{
    return class_exists(\Kampachi\CMS\Content::class)
        ? \Kampachi\CMS\Content::label($key)
        : '';
}

function kampachi_native_landing_slug(): string
{
    if (! is_singular(['page', 'kc_landing'])) {
        return '';
    }

    $pageId = get_queried_object_id();
    $sourceId = absint(get_post_meta($pageId, '_kc_translation_of', true));

    return (string) get_post_field('post_name', $sourceId ?: $pageId);
}

function kampachi_native_collection_query(string $postType): WP_Query
{
    return new WP_Query([
        'post_type' => $postType,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ]);
}

/** @return array{label: string, links: list<array{label: string, url: string, current: string}>}|null */
function kampachi_native_header_post_navigation(): ?array
{
    $landingSlug = kampachi_native_landing_slug();
    $sections = [
        'sake' => ['kc_sake', __('Sake breweries and brands', 'kampachi-native'), __('All Sake', 'kampachi-native')],
        'catering-events' => [
            'kc_event',
            __('Catering and private dining events', 'kampachi-native'),
            __('All Events', 'kampachi-native'),
        ],
    ];

    foreach ($sections as $slug => [$postType, $label, $landingLabel]) {
        $onLanding = $landingSlug === $slug;
        if (! post_type_exists($postType) || (! $onLanding && ! is_singular($postType))) {
            continue;
        }

        $posts = kampachi_native_collection_query($postType)->posts;
        if ($posts === []) {
            return null;
        }

        $currentId = get_queried_object_id();
        $sourceId = absint(get_post_meta($currentId, '_kc_translation_of', true)) ?: $currentId;
        $landing = $onLanding
            ? get_post($currentId)
            : (class_exists(Kampachi_Landings::class) ? Kampachi_Landings::page($slug) : null);
        $landing ??= get_page_by_path($slug);

        $links = [];
        if ($landing instanceof WP_Post && $landing->post_status === 'publish') {
            $url = get_permalink($landing);
            if (is_string($url) && $url !== '') {
                $links[] = ['label' => $landingLabel, 'url' => $url, 'current' => $onLanding ? 'page' : ''];
            }
        }

        foreach ($posts as $post) {
            $isCurrent = ! $onLanding && $post->ID === $sourceId;
            $linkId = $isCurrent && get_post_status($currentId) === 'publish' ? $currentId : $post->ID;
            $title = get_the_title($linkId);
            $url = get_permalink($linkId);
            if ($title !== '' && is_string($url) && $url !== '') {
                $links[] = ['label' => $title, 'url' => $url, 'current' => $isCurrent ? 'page' : ''];
            }
        }

        return ['label' => $label, 'links' => $links];
    }

    return null;
}

/**
 * The shared presentation layer is loaded for every non-homepage module.
 */
function kampachi_native_module_assets(): void
{
    $slug = kampachi_native_landing_slug();
    $isCateringPage = $slug === 'catering-events';
    $isSakePage = $slug === 'sake';
    $is47in27Page = $slug === '47in27';
    $styles = [
        'kampachi-hero-banners' => 'assets/components/hero-banners.css',
    ];

    if ($isCateringPage || is_singular('kc_event') || $isSakePage || is_singular('kc_sake') || is_singular('kc_outlet')) {
        $styles['kampachi-catering-pages'] = 'assets/catering-pages.css';
    }

    if (is_singular('kc_outlet')) {
        $styles['kampachi-outlet-pages'] = 'assets/outlet-pages.css';
    }

    if ($isSakePage || is_singular('kc_sake')) {
        $styles['kampachi-sake-pages'] = 'assets/sake-pages.css';
    }

    if ($is47in27Page) {
        $styles['kampachi-47in27-layout'] = 'assets/47in27.css';
    }

    if (in_array($slug, ['press-media', 'awards-accolades'], true)) {
        $styles['kampachi-press-layout'] = 'assets/press-media.css';
    }

    $styles['kampachi-module-pages'] = 'assets/native-pages.css';
    $styles['kampachi-editorial-pages'] = 'assets/editorial-pages.css';

    foreach ($styles as $handle => $relative) {
        $path = get_theme_file_path($relative);

        if (is_file($path)) {
            wp_enqueue_style($handle, get_theme_file_uri($relative), ['kampachi-shell'], (string) filemtime($path));
        }
    }

    if (is_front_page()) {
        return;
    }

    $scripts = [
        'kampachi-navigation' => 'assets/47in27.js',
        'kampachi-modules' => 'assets/native-pages.js',
    ];

    if (in_array($slug, ['press-media', 'awards-accolades'], true)) {
        $scripts['kampachi-press-archive'] = 'assets/press-archive.js';
    }

    if ($isCateringPage || is_singular('kc_event') || $isSakePage || is_singular('kc_sake') || is_singular('kc_outlet')) {
        $scripts['kampachi-catering-pages'] = 'assets/catering-pages.js';
    }

    foreach ($scripts as $handle => $relative) {
        $path = get_theme_file_path($relative);

        if (is_file($path)) {
            wp_enqueue_script($handle, get_theme_file_uri($relative), [], (string) filemtime($path), true);
        }
    }
}
add_action('wp_enqueue_scripts', 'kampachi_native_module_assets', 20);

/**
 * Resolve external reference imagery strictly from the supplied Kampachi source.
 */
function kampachi_native_post_image(int $postId, string $size = 'large'): string
{
    if (get_post_type($postId) === 'kc_offer' && class_exists(\Kampachi\CMS\Offers::class)) {
        return \Kampachi\CMS\Offers::coverImage($postId, $size);
    }

    if (has_post_thumbnail($postId)) {
        $image = get_the_post_thumbnail_url($postId, $size);

        if (is_string($image)) {
            return $image;
        }
    }

    $url = get_post_meta($postId, '_kampachi_source_image_url', true);

    if (! is_string($url)) {
        return '';
    }

    $host = wp_parse_url($url, PHP_URL_HOST);

    return in_array($host, ['kampachi.com.my', 'www.kampachi.com.my'], true) ? $url : '';
}

/**
 * Render page banners with the same layout and controls as the homepage.
 *
 * @param list<array<string, mixed>> $slides
 */
function kampachi_native_render_hero(
    array $slides,
    string $videoUrl = '',
    string $label = 'Kampachi hero',
    string $id = '',
    bool $autoplayVideo = false
): void {
    if ($videoUrl !== '') {
        array_unshift($slides, ['url' => $videoUrl, 'mime' => 'video/mp4']);
    }

    $availableSlides = [];
    foreach ($slides as $slide) {
        if (! is_array($slide) || ! is_string($slide['url'] ?? null) || $slide['url'] === '') {
            continue;
        }

        $mime = (string) ($slide['mime'] ?? '');
        $slide['type'] = $slide['type'] ?? (str_starts_with($mime, 'video/') ? 'video' : 'image');
        $availableSlides[] = $slide;
    }

    if ($availableSlides !== []) {
        get_template_part('template-parts/hero', null, [
            'slides' => $availableSlides,
            'label' => $label,
            'id' => $id,
            'autoplay_video' => $autoplayVideo,
        ]);
    }
}

/**
 * Resolve the page which owns hero media. A translated page can inherit its
 * source page's images until an editor explicitly saves an override.
 *
 * @param array<int, string> $mediaKeys
 */
function kampachi_native_hero_owner_id(int $pageId, array $mediaKeys): int
{
    if ($pageId <= 0) {
        return $pageId;
    }

    foreach ($mediaKeys as $key) {
        if (metadata_exists('post', $pageId, $key)) {
            return $pageId;
        }
    }

    $sourceId = absint(get_post_meta($pageId, '_kc_translation_of', true));
    $source = $sourceId > 0 ? get_post($sourceId) : null;

    if (! $source instanceof WP_Post || ! in_array($source->post_type, ['page', 'kc_landing'], true)) {
        return $pageId;
    }

    return $sourceId;
}

/**
 * @param array<int, string> $fallbackImages Deprecated; only CMS media is rendered.
 */
function kampachi_native_render_page_hero(int $postId, array $fallbackImages = []): void
{
    $ownerId = kampachi_native_hero_owner_id($postId, [
        '_kampachi_hero_image_ids',
        '_kampachi_hero_video_id',
    ]);
    $images = get_post_meta($ownerId, '_kampachi_hero_image_ids', true);
    $ids = is_array($images) ? $images : [];
    $slides = [];

    foreach ($ids as $id) {
        $url = wp_get_attachment_image_url(absint($id), 'full');

        if (is_string($url) && $url !== '') {
            $slides[] = [
                'url' => $url,
                'direction' => class_exists(\Kampachi\CMS\HeroMotion::class)
                    ? \Kampachi\CMS\HeroMotion::direction($ownerId, absint($id))
                    : 'zoom-in',
            ];
        }
    }

    $videoId = absint(get_post_meta($ownerId, '_kampachi_hero_video_id', true));
    $videoMime = $videoId > 0 ? get_post_mime_type($videoId) : '';
    $videoUrl = is_string($videoMime) && str_starts_with($videoMime, 'video/')
        ? wp_get_attachment_url($videoId)
        : false;

    // Saved CMS images are preferred over any previously selected video.
    if ($slides !== []) {
        kampachi_native_render_hero($slides, '', get_the_title($postId) . ' hero');
        return;
    }

    if (is_string($videoUrl) && $videoUrl !== '') {
        kampachi_native_render_hero([], $videoUrl, get_the_title($postId) . ' hero');
        return;
    }

    // Empty CMS media stays empty, including calls from legacy templates.
}

function kampachi_native_render_intro(string $title, string $subtitle = '', string $description = ''): void
{
    echo '<section class="kc-landing-intro"><div class="kc-landing-inner">';

    if ($subtitle !== '') {
        echo '<p class="kc-kicker">' . esc_html($subtitle) . '</p>';
    }

    echo '<h1>' . esc_html($title) . '</h1>';

    if ($description !== '') {
        echo '<div class="kc-intro-description">' . wp_kses_post(wpautop($description)) . '</div>';
    }

    echo '</div></section>';
}

function kampachi_native_render_post_card(int $postId): void
{
    $title = get_the_title($postId);
    if (class_exists(Outlets::class) && get_post_type($postId) === Outlets::POST_TYPE) {
        $title = Outlets::locationName($postId);
        if ($title === '') {
            return;
        }
    }

    $image = kampachi_native_post_image($postId);
    $category = get_the_terms($postId, 'kc_offer_category');
    $label = is_array($category) && isset($category[0]) ? $category[0]->name : '';
    $badge = get_post_meta($postId, '_kampachi_badge', true);

    if (is_string($badge) && $badge !== '') {
        $label = $badge;
    }

    $discover = kampachi_native_label('discover');
    echo '<article class="kc-module-card kc-linked-card">';
    echo '<div class="kc-module-card-image">';

    if ($image !== '') {
        echo '<img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '" loading="lazy">';
    } else {
        echo '<span class="kc-card-placeholder" aria-hidden="true"></span>';
    }


    echo '</div><div class="kc-module-card-content">';
    $subtitle = kampachi_native_meta($postId, 'subtitle');


    echo '<h2>' . esc_html($title) . '</h2>';
    $excerpt = get_the_excerpt($postId);

    if ($excerpt !== '') {
        echo '<p>' . esc_html($excerpt) . '</p>';
    }

    echo '<a class="kc-discover kc-discover--card kc-linked-card-link" href="' . esc_url(get_permalink($postId))
        . '" aria-label="' . esc_attr($discover . ' ' . $title) . '">' . esc_html($discover) . '</a>';
    echo '</div></article>';
}

/**
 * Keep legacy static promotion-page URLs on the editable CPT detail pages.
 */
function kampachi_native_legacy_offer_redirect(): void
{
    if (! is_page()) {
        return;
    }

    $post = get_queried_object();

    if (! $post instanceof WP_Post || ! str_starts_with($post->post_name, 'offers-')) {
        return;
    }

    $slug = substr($post->post_name, strlen('offers-'));
    $offer = get_page_by_path($slug, OBJECT, 'kc_offer');

    if ($offer instanceof WP_Post && $offer->post_status === 'publish') {
        wp_safe_redirect(get_permalink($offer), 301);
        exit;
    }
}
add_action('template_redirect', 'kampachi_native_legacy_offer_redirect');

/**
 * Resolve old outlet-page links to their published CMS-managed outlet posts.
 * Historic WordPress Pages are kept (not deleted) for safe migration.
 */
function kampachi_native_outlet_link(string $legacySlug): string
{
    $names = [
        'eq' => ['eq', 'eq-kuala-lumpur'],
        'pavilion-kl' => ['pavilion-kl', 'pavilion-kuala-lumpur'],
        'plaza-33' => ['plaza-33', 'plaza33'],
    ];

    foreach ($names[$legacySlug] ?? [$legacySlug] as $slug) {
        $outlet = get_page_by_path($slug, OBJECT, 'kc_outlet');
        if ($outlet instanceof WP_Post && $outlet->post_status === 'publish') {
            $permalink = get_permalink($outlet);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }
    }

    return home_url('/' . $legacySlug . '/');
}

function kampachi_native_legacy_outlet_redirect(): void
{
    if (! is_page()) {
        return;
    }

    $page = get_queried_object();
    if (! $page instanceof WP_Post || ! in_array($page->post_name, ['eq', 'pavilion-kl', 'plaza-33'], true)) {
        return;
    }

    $target = kampachi_native_outlet_link($page->post_name);
    if (untrailingslashit($target) !== untrailingslashit(get_permalink($page))) {
        wp_safe_redirect($target, 301);
        exit;
    }
}
add_action('template_redirect', 'kampachi_native_legacy_outlet_redirect');
