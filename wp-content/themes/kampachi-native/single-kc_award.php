<?php
/**
 * Awards and Accolades entry details.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Awards;

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $awardId = get_the_ID();
    $image = kampachi_native_post_image($awardId, 'full');
    $organisation = kampachi_native_meta($awardId, 'award_organisation');
    $year = kampachi_native_meta($awardId, 'award_year');
    $subtitle = implode(' · ', array_filter([$organisation, $year]));
    $sourceUrl = esc_url(kampachi_native_meta($awardId, 'award_source_url'), ['https', 'http']);
    $landing = get_page_by_path(Awards::PAGE_SLUG, OBJECT, 'page');
    ?>
    <main class="kc-detail-page kc-award-detail">
        <?php if ($image !== '') : ?>
            <?php kampachi_native_render_hero([['url' => $image]], '', get_the_title() . ' hero'); ?>
        <?php endif; ?>
        <?php kampachi_native_render_intro(get_the_title(), $subtitle); ?>
        <article class="kc-editor-area">
            <?php if ($landing instanceof WP_Post && $landing->post_status === 'publish') : ?>
                <a class="kc-back" href="<?php echo esc_url(get_permalink($landing)); ?>">
                    <span aria-hidden="true">←</span> <?php echo esc_html(kampachi_native_label('back_awards')); ?>
                </a>
            <?php endif; ?>
            <?php the_content(); ?>
            <?php if ($sourceUrl !== '') : ?>
                <div class="kc-page-actions">
                    <a href="<?php echo $sourceUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>"
                        target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html(kampachi_native_label('view_award_source')); ?>
                        <span aria-hidden="true">↗</span>
                    </a>
                </div>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
