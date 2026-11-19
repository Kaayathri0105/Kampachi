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

$sakes = new WP_Query([
    'post_type' => Sake::POST_TYPE,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
]);
$total = $sakes->post_count;
?>
<main class="kc-catering-page kc-sake-page" id="main-content">
    <?php if ($hero !== []) : ?>
        <section class="kc-catering-hero" data-kc-hero aria-roledescription="carousel" aria-label="Sake hero images and videos">
            <div class="kc-catering-hero-track">
                <?php foreach ($hero as $index => $media) : ?>
                    <div class="kc-catering-hero-slide kc-motion-<?php echo esc_attr((string) ($media['direction'] ?? 'zoom-in')); ?><?php echo $index === 0 ? ' is-active' : ''; ?>" data-kc-hero-slide <?php if ($index > 0) : ?>aria-hidden="true"<?php endif; ?>>
                        <?php if ($media['type'] === 'video') : ?>
                            <video muted playsinline preload="<?php echo $index === 0 ? 'metadata' : 'none'; ?>" <?php if (count($hero) === 1) : ?>loop<?php endif; ?> data-kc-hero-video aria-label="Sake hero video <?php echo esc_attr((string) ($index + 1)); ?>">
                                <source src="<?php echo esc_url($media['url']); ?>" type="<?php echo esc_attr($media['mime']); ?>">
                            </video>
                        <?php else : ?>
                            <img src="<?php echo esc_url($media['url']); ?>" alt="<?php echo esc_attr($media['alt']); ?>" <?php if ($index === 0) : ?>loading="eager" fetchpriority="high"<?php else : ?>loading="lazy"<?php endif; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <span class="kc-catering-hero-shade" aria-hidden="true"></span>
            <div class="kc-catering-hero-controls" aria-label="Hero slideshow controls">
                <?php if (count($hero) > 1) : ?>
                    <button type="button" data-kc-hero-prev aria-label="Previous hero item">←</button>
                    <span class="kc-catering-hero-count" data-kc-hero-count>01 / <?php echo esc_html(sprintf('%02d', count($hero))); ?></span>
                    <button type="button" data-kc-hero-next aria-label="Next hero item">→</button>
                <?php endif; ?>
                <button type="button" data-kc-hero-toggle aria-label="Pause hero slideshow" aria-pressed="true">Pause</button>
            </div>
        </section>
    <?php endif; ?>

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
                        <article class="kc-catering-card kc-sake-card">
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

                                <a class="kc-catering-discover" href="<?php echo esc_url(get_permalink($sakeId)); ?>" aria-label="<?php echo esc_attr(sprintf('Discover bottles from %s', $name)); ?>"><?php echo esc_html(kampachi_native_label('discover')); ?> <span aria-hidden="true">↗</span></a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>
    </section>
</main>
