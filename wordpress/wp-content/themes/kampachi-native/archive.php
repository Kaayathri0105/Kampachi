<?php
/**
 * Consistent native WordPress archive for all Kampachi collections.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

get_header();

if (is_post_type_archive('kc_offer')) {
    get_template_part('templates/promotion');
} else {
    ?>
    <main class="kc-collection-page kc-landing-page">
        <?php kampachi_native_render_intro(wp_strip_all_tags(get_the_archive_title()), 'Kampachi Collection'); ?>
        <section class="kc-section"><div class="kc-section-inner">
            <?php if (have_posts()) : ?>
                <div class="kc-module-grid">
                    <?php while (have_posts()) : ?>
                        <?php the_post(); ?>
                        <?php kampachi_native_render_post_card(get_the_ID()); ?>
                    <?php endwhile; ?>
                </div>
                <nav class="kc-pagination" aria-label="Collection pages"><?php the_posts_pagination(); ?></nav>
            <?php endif; ?>
        </div></section>
    </main>
    <?php
}

get_footer();
