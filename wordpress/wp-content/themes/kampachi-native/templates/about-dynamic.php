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
$images = get_post_meta($pageId, '_kampachi_about_image_ids', true);
$images = is_array($images) ? $images : [];
?>
<main id="main-content" class="kc-editorial-page kc-about-page">
    <?php kampachi_native_render_page_hero($pageId); ?>
    <?php kampachi_native_render_intro($title, $subtitle, $intro); ?>
    <?php if ($images !== []) : ?>
        <section class="kc-editorial-container kc-about-gallery" aria-label="Kampachi photo gallery">
            <?php foreach ($images as $imageId) : ?>
                <?php
                $imageId = absint($imageId);
                if (! wp_attachment_is_image($imageId)) {
                    continue;
                }
                ?>
                <figure class="kc-about-photo">
                    <?php echo wp_get_attachment_image($imageId, 'large', false, ['loading' => 'lazy', 'sizes' => '(max-width: 720px) 100vw, 48vw']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php $caption = wp_get_attachment_caption($imageId); ?>
                    <?php if (is_string($caption) && $caption !== '') : ?><figcaption><?php echo esc_html($caption); ?></figcaption><?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php $post = get_post($pageId); ?>
    <?php
    if ($post instanceof WP_Post && trim($post->post_content) !== '') :
        ?>
        <section class="kc-editorial-container kc-about-story">
            <?php echo apply_filters('the_content', $post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
    <?php endif; ?>
</main>
