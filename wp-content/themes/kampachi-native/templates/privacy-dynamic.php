<?php
/** @package KampachiNative */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$pageId = get_queried_object_id();
$title = kampachi_native_meta($pageId, 'intro_title') ?: get_the_title($pageId);
$subtitle = kampachi_native_meta($pageId, 'intro_subtitle');
$intro = kampachi_native_meta($pageId, 'intro_description');
$sections = get_post_meta($pageId, '_kampachi_privacy_sections', true);
$sections = is_array($sections) ? $sections : [];
$post = get_post($pageId);
?>
<main id="main-content" class="kc-editorial-page kc-policy-page">
    <?php kampachi_native_render_page_hero($pageId); ?>
    <?php kampachi_native_render_intro($title, $subtitle, $intro); ?>
    <div class="kc-editorial-container kc-policy-content">
        <?php foreach ($sections as $section) : ?>
            <?php
            if (! is_array($section)) {
                continue;
            }
            $sectionTitle = (string) ($section['title'] ?? '');
            $sectionDescription = (string) ($section['description'] ?? '');
            if ($sectionTitle === '' && trim(wp_strip_all_tags($sectionDescription)) === '') {
                continue;
            }
            ?>
            <section class="kc-policy-section">
                <?php if ($sectionTitle !== '') : ?><h2><?php echo esc_html($sectionTitle); ?></h2><?php endif; ?>
                <div><?php echo wp_kses_post(wpautop($sectionDescription)); ?></div>
            </section>
        <?php endforeach; ?>
        <?php if ($sections === [] && $post instanceof WP_Post && trim($post->post_content) !== '') : ?>
            <div class="kc-policy-legacy-content">
                <?php echo apply_filters('the_content', $post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
    </div>
</main>
