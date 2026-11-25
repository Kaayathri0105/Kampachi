<?php
/**
 * CMS-driven Catering & Private Events landing page.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Site_Language;

use Kampachi\CMS\Catering;
use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

$pageId = get_queried_object_id();
$page = get_post($pageId);
if (! $page instanceof WP_Post) {
    return;
}

$title = kampachi_native_meta($pageId, 'intro_title') ?: get_the_title($pageId);
$subtitle = kampachi_native_meta($pageId, 'intro_subtitle');
$description = kampachi_native_meta($pageId, 'intro_description');
$valuesTitle = kampachi_native_meta($pageId, 'values_title');
$valuesSubtitle = kampachi_native_meta($pageId, 'values_subtitle');
$valuesDescription = kampachi_native_meta($pageId, 'values_description');

$values = get_post_meta($pageId, '_kampachi_values_list', true);
$values = is_array($values) ? $values : [];

$images = [];
$galleryIds = Catering::image_ids(get_post_meta($pageId, '_kampachi_values_image_ids', true));
foreach ($galleryIds as $imageId) {
    $url = wp_get_attachment_image_url($imageId, 'full');
    if (is_string($url)) {
        $images[] = [
            'url' => $url,
            'alt' => (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
        ];
    }
}
$events = kampachi_native_collection_query(Catering::POST_TYPE);
$totalEvents = $events->post_count;
$heroOwnerId = kampachi_native_hero_owner_id($pageId, [
    '_kampachi_catering_hero_media_ids',
    '_kampachi_hero_image_ids',
    '_kampachi_hero_video_id',
]);
$hero = [];
foreach (Catering::hero_media_ids($heroOwnerId) as $mediaId) {
    $isImage = wp_attachment_is_image($mediaId);
    $url = $isImage ? wp_get_attachment_image_url($mediaId, 'full') : wp_get_attachment_url($mediaId);

    if (! is_string($url) || $url === '') {
        continue;
    }

    $hero[] = [
        'type' => $isImage ? 'image' : 'video',
        'url' => $url,
        'mime' => (string) get_post_mime_type($mediaId),
        'alt' => (string) get_post_meta($mediaId, '_wp_attachment_image_alt', true),
        'direction' => class_exists(\Kampachi\CMS\HeroMotion::class)
            ? \Kampachi\CMS\HeroMotion::direction($heroOwnerId, $mediaId)
            : 'zoom-in',
    ];
}

?>
<main class="kc-catering-page" id="main-content">
    <?php kampachi_native_render_hero($hero, '', 'Catering & Events hero'); ?>

    <section class="kc-catering-intro<?php echo $hero !== [] ? ' kc-catering-intro--with-hero' : ''; ?>" aria-labelledby="kc-catering-heading">
        <div class="kc-catering-container kc-catering-intro-inner">
            <p class="kc-catering-eyebrow"><?php echo esc_html($subtitle); ?></p>
            <h1 id="kc-catering-heading"><?php echo esc_html($title); ?></h1>
            <div class="kc-catering-copy"><?php echo wp_kses_post(wpautop($description)); ?></div>
        </div>
    </section>

    <section class="kc-catering-events" aria-label="Catering and private event services">
        <div class="kc-catering-container">
            <?php if ($events->have_posts()) : ?>
                <div class="kc-catering-grid">
                    <?php
                    $number = 0;
                    while ($events->have_posts()) :
                        $events->the_post();
                        $number++;
                        $eventId = get_the_ID();
                        $eventTitle = get_the_title();
                        $eventSubtitle = Catering::event_field($eventId, 'subtitle');
                        $eventBadge = Catering::event_field($eventId, 'label');
                        $ctaLabel = Catering::event_field($eventId, 'cta_label') ?: kampachi_native_label('discover');
                        $eventUrl = get_permalink($eventId);
                        $ctaUrl = Catering::event_field($eventId, 'cta_url') ?: $eventUrl;
                        $hasCustomCta = $ctaUrl !== $eventUrl;
                        $summary = get_the_excerpt($eventId);
                        $eventImages = [];
                        foreach (Catering::image_ids(get_post_meta($eventId, '_kampachi_event_image_ids', true)) as $imageId) {
                            $url = wp_get_attachment_image_url($imageId, 'large');
                            if (is_string($url)) {
                                $eventImages[] = [
                                    'url' => $url,
                                    'alt' => (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
                                ];
                            }
                        }
                        if ($eventImages === [] && has_post_thumbnail($eventId)) {
                            $thumbnail = get_the_post_thumbnail_url($eventId, 'large');
                            if (is_string($thumbnail)) {
                                $eventImages[] = ['url' => $thumbnail, 'alt' => $eventTitle];
                            }
                        }
                        $outletIds = Catering::outlet_ids($eventId);
                        ?>
                        <article class="kc-catering-card kc-linked-card">
                            <div class="kc-catering-card-image" data-kc-carousel>
                                <?php foreach ($eventImages as $index => $image) : ?>
                                    <img
                                        class="kc-catering-card-photo<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                        data-kc-image
                                        src="<?php echo esc_url($image['url']); ?>"
                                        alt="<?php echo esc_attr($image['alt'] ?: $eventTitle); ?>"
                                        loading="lazy"
                                        <?php if ($index > 0) : ?>aria-hidden="true"<?php endif; ?>
                                    >
                                <?php endforeach; ?>
                                <?php if ($eventImages === []) : ?><span class="kc-catering-photo-empty" aria-hidden="true"></span><?php endif; ?>

                                <?php if (count($eventImages) > 1) : ?>
                                    <div class="kc-catering-photo-controls" aria-label="Event photo gallery">
                                        <button type="button" data-kc-prev aria-label="Previous event image">‹</button>
                                        <button type="button" data-kc-next aria-label="Next event image">›</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="kc-catering-card-body">

                                <h2>
                                    <?php if ($hasCustomCta) : ?>
                                        <a class="kc-linked-card-link" href="<?php echo esc_url($eventUrl); ?>">
                                            <?php echo esc_html($eventTitle); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($eventTitle); ?>
                                    <?php endif; ?>
                                </h2>
                                <?php if ($summary !== '') : ?><p class="kc-catering-card-description"><?php echo esc_html($summary); ?></p><?php endif; ?>
                                <?php if ($outletIds !== []) : ?>
                                    <div class="kc-catering-available-outlets" aria-label="<?php echo esc_attr(kampachi_native_label('locations')); ?>">
                                        <span><?php echo esc_html(kampachi_native_label('locations')); ?>:</span>
                                        <?php foreach ($outletIds as $outletId) :
                                            $outletUrl = Site_Language::url((string) get_permalink($outletId));
                                            if ($outletUrl === '') {
                                                continue;
                                            }
                                        ?>
                                            <a class="kc-linked-card-action" href="<?php echo esc_url($outletUrl); ?>"><?php echo esc_html(Site_Language::text(Outlets::locationName($outletId))); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <a class="kc-discover kc-discover--card <?php echo $hasCustomCta ? 'kc-linked-card-action' : 'kc-linked-card-link'; ?>"
                                    href="<?php echo esc_url($ctaUrl); ?>"
                                    aria-label="<?php echo esc_attr($ctaLabel . ' ' . $eventTitle); ?>">
                                    <?php echo esc_html($ctaLabel); ?>
                                </a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
            <?php else : ?>
                <?php if (current_user_can('edit_posts')) : ?>
                    <p class="kc-catering-empty">No published event posts yet. Add an event in the WordPress Catering &amp; Events module.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="kc-catering-values" aria-labelledby="kc-catering-values-heading">
        <div class="kc-catering-container">
            <header class="kc-catering-values-heading">
                <p class="kc-catering-eyebrow"><?php echo esc_html($valuesSubtitle); ?></p>
                <h2 id="kc-catering-values-heading"><?php echo esc_html($valuesTitle); ?></h2>
                <?php if ($valuesDescription !== '') : ?><div class="kc-catering-values-copy"><?php echo wp_kses_post(wpautop($valuesDescription)); ?></div><?php endif; ?>
            </header>
            <?php if ($images !== []) : ?>
                <div class="kc-catering-showcase" data-kc-carousel aria-label="Kampachi trust and value gallery" role="region" aria-roledescription="carousel">
                    <?php foreach ($images as $index => $image) : ?>
                        <img
                            class="kc-catering-showcase-photo<?php echo $index === 0 ? ' is-active' : ''; ?>"
                            data-kc-image
                            src="<?php echo esc_url($image['url']); ?>"
                            alt="<?php echo esc_attr($image['alt']); ?>"
                            loading="lazy"
                            <?php if ($index > 0) : ?>aria-hidden="true"<?php endif; ?>
                        >
                    <?php endforeach; ?>
                    <?php if (count($images) > 1) : ?>
                        <div class="kc-catering-showcase-controls">
                            <button type="button" data-kc-prev aria-label="Previous gallery photo">←</button>
                            <div class="kc-catering-dots" role="group" aria-label="Select gallery photo">
                                <?php foreach ($images as $index => $image) : ?>
                                    <button type="button" data-kc-dot="<?php echo esc_attr((string) $index); ?>" aria-label="Show photo <?php echo esc_attr((string) ($index + 1)); ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            <span class="kc-catering-showcase-count" data-kc-count><?php echo esc_html(sprintf('%02d / %02d', 1, count($images))); ?></span>
                            <button type="button" data-kc-next aria-label="Next gallery photo">→</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($values !== []) : ?>
                <ul class="kc-catering-values-list">
                    <?php foreach ($values as $value) : ?>
                        <?php
                        if (! is_array($value)) {
                            continue;
                        }
                        $valueTitle = isset($value['title']) && is_string($value['title']) ? $value['title'] : '';
                        $valueDescription = isset($value['description']) && is_string($value['description']) ? $value['description'] : '';
                        if ($valueTitle === '' && $valueDescription === '') {
                            continue;
                        }
                        ?>
                        <li>
                            <?php if ($valueTitle !== '') : ?><strong><?php echo esc_html($valueTitle); ?>:</strong><?php endif; ?>
                            <?php echo esc_html($valueDescription); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</main>
