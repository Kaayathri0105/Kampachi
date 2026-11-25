<?php

/**
 * CMS-only landing page; empty fields do not restore sample content or photos.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Simplified_Chinese;

use Kampachi\CMS\AwardsLanding;
use Kampachi\CMS\OutletsLanding;
use Kampachi\CMS\PressLanding;

if (! defined('ABSPATH')) {
    exit;
}

$postId = get_queried_object_id();
$page = get_post($postId);

if (! $page instanceof WP_Post) {
    return;
}

$sourceId = absint(get_post_meta($postId, '_kc_translation_of', true));
$slug = $sourceId > 0 ? (string) get_post_field('post_name', $sourceId) : $page->post_name;
$title = kampachi_native_meta($postId, 'intro_title') ?: get_the_title($postId);
$subtitle = kampachi_native_meta($postId, 'intro_subtitle');
$description = kampachi_native_meta($postId, 'intro_description');
$archiveEditor = match ($slug) {
    'press-media' => PressLanding::class,
    'awards-accolades' => AwardsLanding::class,
    default => null,
};

if ($archiveEditor !== null && class_exists($archiveEditor)) {
    $isAwards = $archiveEditor === AwardsLanding::class;
    if ($isAwards && trim($description) === '' && trim($page->post_content) === '') {
        $description = __('Explore the awards and accolades Kampachi has received over the years.', 'kampachi-native');
    }

    get_template_part('templates/press-media', null, [
        'pageId' => $postId,
        'isAwards' => $isAwards,
        'title' => $title,
        'description' => $description !== '' ? $description : $page->post_content,
    ]);
    return;
}

$collectionType = [
    'sake' => 'kc_sake',
    'outlets' => 'kc_outlet',
    'careers' => 'kc_job',
    'press-media' => 'kc_press',
    'awards-accolades' => 'kc_award',
][$slug] ?? '';
$landingEditor = match ($collectionType) {
    'kc_outlet' => OutletsLanding::class,
    default => null,
};
?>
<main class="kc-landing-page kc-module-<?php echo esc_attr(sanitize_html_class($slug)); ?>">
    <?php kampachi_native_render_page_hero($postId); ?>
    <?php kampachi_native_render_intro($title, $subtitle, $description); ?>
    <?php if (trim($page->post_content) !== '') : ?>
        <section class="kc-section"><div class="kc-editor-area"><?php echo apply_filters('the_content', $page->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section>
    <?php endif; ?>
    <?php if ($collectionType !== '') : ?>
        <?php
        $entries = $landingEditor !== null && class_exists($landingEditor)
            ? $landingEditor::posts($postId)
            : (new WP_Query([
                'post_type' => $collectionType,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
                'no_found_rows' => true,
            ]))->posts;
        $entries = Simplified_Chinese::posts($entries);
        ?>
        <?php if ($entries !== []) : ?>
            <section class="kc-section" id="collection">
                <div class="kc-section-inner kc-module-grid">
                    <?php foreach ($entries as $entry) : ?>
                        <?php kampachi_native_render_post_card($entry->ID); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
