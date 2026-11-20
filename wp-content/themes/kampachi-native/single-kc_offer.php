<?php

/**
 * EQ-inspired offer detail layout in the Kampachi visual language.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Offers;
use Kampachi\CMS\Outlets;

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
    $menus = Offers::menus($offerId);
    $details = Offers::details($offerId);
    $outlets = Offers::outletIds($offerId);
    $hasOutlets = $outlets !== [];
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
            </header>

            <?php if ($details !== [] || $hasOutlets || $menus !== [] || $hasSchedule) : ?>
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
                        <?php if ($hasOutlets) : ?>
                            <section class="kc-offer-detail-section kc-offer-outlets" aria-labelledby="kc-offer-outlets-title">
                                <h3 id="kc-offer-outlets-title"><?php echo esc_html(kampachi_native_label('locations')); ?></h3>
                                <div class="kc-offer-rich-text">
                                    <ul class="kc-offer-outlet-list" role="list">
                                        <?php foreach ($outlets as $outletId) : ?>
                                            <li><span class="kc-offer-list-dash" aria-hidden="true">–</span><a href="<?php echo esc_url(get_permalink($outletId)); ?>"><?php echo esc_html(Outlets::locationName($outletId)); ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </section>
                        <?php endif; ?>
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
            <div class="kc-offer-back"><a href="<?php echo esc_url(home_url('/promotion/')); ?>">← <?php echo esc_html(kampachi_native_label('back_offers')); ?></a></div>
        </div>
    </main>
    <?php
endwhile;

get_footer();
