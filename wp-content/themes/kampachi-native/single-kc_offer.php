<?php

/**
 * EQ-inspired offer detail layout in the Kampachi visual language.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Offers;
use Kampachi\CMS\OffersLanding;

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists(Offers::class)) {
    require get_theme_file_path('single.php');
    return;
}

get_header();

while (have_posts()) :
    the_post();
    $offerId = get_the_ID();
    $hero = Offers::hero($offerId);
    $introduction = Offers::introduction($offerId);
    $dates = Offers::dateRange($offerId);
    $times = Offers::timeRange($offerId);
    $hasSchedule = $dates !== '' || $times !== '';
    $details = Offers::details($offerId);
    $menus = Offers::menus($offerId);
    $ctas = Offers::ctas($offerId);
    $neighbors = OffersLanding::adjacentOffers($offerId);
    ?>
    <main class="kc-offer-page<?php echo $hero === [] ? ' kc-offer-page--no-hero' : ''; ?>" id="main-content">
        <?php kampachi_native_render_hero($hero, '', get_the_title() . ' hero'); ?>

        <div class="kc-offer-inner">
            <nav class="kc-offer-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo esc_url(home_url('/promotion/')); ?>"><?php echo esc_html(kampachi_native_label('offers')); ?></a><span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html(get_the_title()); ?></span></nav>
            <header class="kc-offer-introduction" id="offer-content">
                <h1><?php echo esc_html(get_the_title()); ?></h1>
                <?php if ($introduction !== '') : ?>
                    <div class="kc-offer-copy"><?php echo wp_kses_post(wpautop($introduction)); ?></div>
                <?php endif; ?>
                <?php if ($ctas !== []) : ?>
                    <div class="kc-offer-cta">
                        <?php foreach ($ctas as $cta) : ?>
                            <a class="view-all-button kc-offer-cta-link" href="<?php echo esc_url($cta['url']); ?>">
                                <?php echo esc_html($cta['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($details !== [] || $hasSchedule || $menus !== []) : ?>
                <section class="kc-offer-details" aria-labelledby="kc-offer-details-title">
                    <div class="kc-offer-details-heading"><h2 id="kc-offer-details-title"><?php echo esc_html(kampachi_native_label('offer_details')); ?></h2></div>
                    <div class="kc-offer-detail-sections">
                        <?php if ($hasSchedule) : ?>
                            <section class="kc-offer-detail-section kc-offer-schedule" aria-labelledby="kc-offer-schedule-title">
                                <h3 id="kc-offer-schedule-title"><?php echo esc_html(kampachi_native_label('schedule')); ?></h3>
                                <div class="kc-offer-rich-text">
                                    <?php if ($dates !== '') : ?>
                                        <p class="kc-offer-schedule-date"><?php echo esc_html($dates); ?></p>
                                    <?php endif; ?>
                                    <?php if ($times !== '') : ?>
                                        <p class="kc-offer-schedule-time"><?php echo esc_html($times); ?></p>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                        <?php foreach ($details as $detail) : ?>
                            <section class="kc-offer-detail-section">
                                <?php if ($detail['title'] !== '') :
                                    ?><h3><?php echo esc_html($detail['title']); ?></h3><?php
                                endif; ?>
                                <?php if ($detail['description'] !== '') : ?>
                                    <?php
                                    // Older imports stored an arrow inside the booking link's text.
                                    $description = str_replace(
                                        ' — Book or enquire ↗</a>',
                                        ' — Book or enquire</a>',
                                        $detail['description']
                                    );
                                    ?>
                                    <div class="kc-offer-rich-text"><?php echo wp_kses_post(wpautop($description)); ?></div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                        <?php if ($menus !== []) : ?>
                            <section class="kc-offer-detail-section kc-offer-menus" aria-labelledby="kc-offer-menus-title">
                                <h3 id="kc-offer-menus-title"><?php echo esc_html(kampachi_native_label('menus')); ?></h3>
                                <div class="kc-offer-menu-links">
                                    <?php foreach ($menus as $menu) : ?>
                                        <a class="kc-offer-menu-link" href="<?php echo esc_url($menu['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($menu['title']); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if (($neighbors['previous'] ?? null) !== null || ($neighbors['next'] ?? null) !== null) : ?>
                <nav class="kc-offer-pagination" aria-label="<?php esc_attr_e('Offers navigation', 'kampachi-native'); ?>">
                    <?php foreach ([
                        'previous' => [__('Previous', 'kampachi-native'), 'prev'],
                        'next' => [__('Next', 'kampachi-native'), 'next'],
                    ] as $direction => [$label, $relation]) : ?>
                        <?php $neighbor = $neighbors[$direction]; ?>
                        <?php if ($neighbor !== null) : ?>
                            <a class="kc-offer-pagination-link" href="<?php echo esc_url(get_permalink($neighbor)); ?>"
                                rel="<?php echo esc_attr($relation); ?>">
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php else : ?>
                            <button class="kc-offer-pagination-link" type="button" disabled>
                                <?php echo esc_html($label); ?>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
    </main>
    <?php
endwhile;

get_footer();
