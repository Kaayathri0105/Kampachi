<?php
/**
 * WordPress-powered Sake landing page.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Sake;
use Kampachi\CMS\SakeBottles;

if (! defined('ABSPATH')) {
    exit;
}

$pageId = get_queried_object_id();
$page = get_post($pageId);

if (! $page instanceof WP_Post || ! class_exists(Sake::class)) {
    return;
}

$title = kampachi_native_meta($pageId, 'intro_title') ?: get_the_title($pageId);
$subtitle = kampachi_native_meta($pageId, 'intro_subtitle');
$description = kampachi_native_meta($pageId, 'intro_description');

$heroOwnerId = kampachi_native_hero_owner_id($pageId, [
    '_kampachi_sake_hero_media_ids',
    '_kampachi_hero_image_ids',
    '_kampachi_hero_video_id',
]);
$hero = [];
foreach (Sake::hero_ids($heroOwnerId) as $mediaId) {
    $image = wp_attachment_is_image($mediaId);
    $mime = get_post_mime_type($mediaId);
    $url = $image ? wp_get_attachment_image_url($mediaId, 'full') : wp_get_attachment_url($mediaId);

    if (! is_string($url) || $url === '') {
        continue;
    }

    $hero[] = [
        'type' => $image ? 'image' : 'video',
        'url' => $url,
        'mime' => is_string($mime) ? $mime : 'video/mp4',
        'alt' => (string) get_post_meta($mediaId, '_wp_attachment_image_alt', true),
        'direction' => class_exists(\Kampachi\CMS\HeroMotion::class)
            ? \Kampachi\CMS\HeroMotion::direction($heroOwnerId, $mediaId)
            : 'zoom-in',
    ];
}

$sakes = kampachi_native_collection_query(Sake::POST_TYPE);
$total = $sakes->post_count;
?>
<main class="kc-catering-page kc-sake-page" id="main-content">
    <?php kampachi_native_render_hero($hero, '', 'Sake hero', autoplayVideo: true); ?>

    <section class="kc-catering-intro<?php echo $hero !== [] ? ' kc-catering-intro--with-hero' : ''; ?>" aria-labelledby="kc-sake-heading">
        <div class="kc-catering-container kc-catering-intro-inner">
            <p class="kc-catering-eyebrow"><?php echo esc_html($subtitle); ?></p>
            <h1 id="kc-sake-heading"><?php echo esc_html($title); ?></h1>
            <div class="kc-catering-copy"><?php echo wp_kses_post(wpautop($description)); ?></div>
        </div>
    </section>

    <section class="kc-catering-events" aria-label="Kampachi Sake breweries and brands">
        <div class="kc-catering-container">
            <?php if ($sakes->have_posts()) : ?>
                <div class="kc-catering-grid">
                    <?php
                            while ($sakes->have_posts()) :
                        $sakes->the_post();
                        $sakeId = get_the_ID();
                        $name = get_the_title();
                        $images = Sake::gallery($sakeId);
                                                $summary = get_the_excerpt($sakeId);

                        ?>
                        <article class="kc-catering-card kc-sake-card kc-linked-card">
                            <div class="kc-catering-card-image" data-kc-carousel>
                                <?php foreach ($images as $index => $image) : ?>
                                    <img class="kc-catering-card-photo<?php echo $index === 0 ? ' is-active' : ''; ?>" data-kc-image src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?: $name); ?>" loading="lazy" <?php if ($index > 0) : ?>aria-hidden="true"<?php endif; ?>>
                                <?php endforeach; ?>
                                <?php if ($images === []) : ?><span class="kc-catering-photo-empty" aria-hidden="true"></span><?php endif; ?>

                                <?php if (count($images) > 1) : ?>
                                    <div class="kc-catering-photo-controls" aria-label="Sake photo gallery">
                                        <button type="button" data-kc-prev aria-label="Previous Sake image">‹</button>
                                        <button type="button" data-kc-next aria-label="Next Sake image">›</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="kc-catering-card-body">

                                <h2><?php echo esc_html($name); ?></h2>
                                <?php if ($summary !== '') : ?><p class="kc-catering-card-description"><?php echo esc_html($summary); ?></p><?php endif; ?>

                                <a class="kc-discover kc-discover--card kc-linked-card-link" href="<?php echo esc_url(get_permalink($sakeId)); ?>" aria-label="<?php echo esc_attr(sprintf('Discover bottles from %s', $name)); ?>"><?php echo esc_html(kampachi_native_label('discover')); ?></a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>
    </section>
</main>
