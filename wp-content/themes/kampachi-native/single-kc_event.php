<?php
/**
 * Single catering-event details page.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Site_Language;

use Kampachi\CMS\Catering;
use Kampachi\CMS\Outlets;

if (! class_exists(Catering::class)) {
    get_template_part('single');
    return;
}

get_header();

while (have_posts()) {
    the_post();
    $postId = get_the_ID();
    $subtitle = Catering::event_field($postId, 'subtitle');
    $summary = Catering::event_field($postId, 'description');
    $outletIds = Catering::outlet_ids($postId);
    $ctaLabel = Catering::event_field($postId, 'cta_label') ?: kampachi_native_label('enquire');
    $ctaUrl = Catering::event_field($postId, 'cta_url');
    if ($ctaUrl === '') {
        $ctaUrl = home_url('/contact-us/');
    }

    $ctaUrl = Site_Language::url($ctaUrl);
    $images = [];
    foreach (Catering::image_ids(get_post_meta($postId, '_kampachi_event_image_ids', true)) as $imageId) {
        $url = wp_get_attachment_image_url($imageId, 'full');
        if (is_string($url)) {
            $images[] = [
                'url' => $url,
                'alt' => (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
                'direction' => class_exists(\Kampachi\CMS\HeroMotion::class)
                    ? \Kampachi\CMS\HeroMotion::direction($postId, $imageId)
                    : 'zoom-in',
            ];
        }
    }
    if ($images === [] && has_post_thumbnail($postId)) {
        $url = get_the_post_thumbnail_url($postId, 'full');
        if (is_string($url)) {
            $images[] = ['url' => $url, 'alt' => get_the_title()];
        }
    }
    ?>
    <main class="kc-catering-page kc-catering-detail" id="main-content">
        <?php kampachi_native_render_hero($images, '', get_the_title() . ' hero'); ?>
        <section class="kc-catering-detail-content">
            <div class="kc-catering-container">
                <?php $landingUrl = Site_Language::url(home_url('/catering-events/')); ?>
                <?php if ($landingUrl !== '') : ?>
                    <a class="kc-catering-detail-back" href="<?php echo esc_url($landingUrl); ?>">← <?php echo esc_html(kampachi_native_label('back_events')); ?></a>
                <?php endif; ?>
                <?php if ($subtitle !== '') : ?><p class="kc-catering-eyebrow"><?php echo esc_html($subtitle); ?></p><?php endif; ?>
                <h1><?php the_title(); ?></h1>
                <?php if ($summary !== '') : ?><p class="kc-catering-detail-summary"><?php echo esc_html($summary); ?></p><?php endif; ?>
                <?php get_template_part('template-parts/whatsapp-ctas', null, [
                    'outlet_ids' => $outletIds,
                    'existing_urls' => [$ctaUrl],
                ]); ?>
                <div class="kc-catering-detail-text"><?php the_content(); ?></div>
                <?php if ($outletIds !== []) : ?>
                    <div class="kc-catering-detail-outlets"><h2><?php echo esc_html(kampachi_native_label('locations')); ?></h2>
                        <?php foreach ($outletIds as $outletId) :
                            $outletUrl = Site_Language::url((string) get_permalink($outletId));
                            if ($outletUrl === '') {
                                continue;
                            }
                            ?>
                            <a href="<?php echo esc_url($outletUrl); ?>"><?php echo esc_html(Site_Language::text(Outlets::locationName($outletId))); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($ctaUrl !== '') : ?>
                    <p><a class="kc-catering-detail-cta" href="<?php echo esc_url($ctaUrl); ?>"><?php echo esc_html($ctaLabel); ?></a></p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php
}

get_footer();
