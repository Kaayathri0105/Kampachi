<?php
/**
 * Press and Media article details.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $articleId = get_the_ID();
    $image = kampachi_native_post_image($articleId, 'full');
    $publication = kampachi_native_meta($articleId, 'press_publication');
    $sourceUrl = esc_url(kampachi_native_meta($articleId, 'press_source_url'), ['https', 'http']);
    $landing = Kampachi_Landings::page('press-media');
    ?>
    <main class="kc-detail-page kc-press-detail">
        <?php if ($image !== '') : ?>
            <?php kampachi_native_render_hero([['url' => $image]], '', get_the_title() . ' hero'); ?>
        <?php endif; ?>
        <?php kampachi_native_render_intro(get_the_title(), $publication); ?>
        <article class="kc-editor-area">
            <?php if ($landing instanceof WP_Post && $landing->post_status === 'publish') : ?>
                <a class="kc-back" href="<?php echo esc_url(get_permalink($landing)); ?>">
                    <span aria-hidden="true">←</span> <?php echo esc_html(kampachi_native_label('back_press')); ?>
                </a>
            <?php endif; ?>
            <?php the_content(); ?>
            <?php if ($sourceUrl !== '') : ?>
                <div class="kc-page-actions">
                    <a href="<?php echo $sourceUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>"
                        target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html(kampachi_native_label('view_original_article')); ?>
                    </a>
                </div>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
