<?php

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

/** @var array $kampachiHome */

$videoId = absint($kampachiHome['hero_video_id'] ?? 0);
$videoUrl = $videoId > 0 ? wp_get_attachment_url($videoId) : false;
$mime = $videoId > 0 ? (string) get_post_mime_type($videoId) : '';
$video = is_string($videoUrl) && $videoUrl !== '' && str_starts_with($mime, 'video/')
    ? ['url' => $videoUrl, 'mime' => $mime]
    : null;

$images = [];
foreach ((array) ($kampachiHome['hero_slides'] ?? []) as $slide) {
    if (! is_array($slide)) {
        continue;
    }

    $url = wp_get_attachment_image_url(absint($slide['image_id'] ?? 0), 'full');
    if (is_string($url) && $url !== '') {
        $images[] = [
            'url' => $url,
            'direction' => sanitize_html_class((string) ($slide['direction'] ?? 'zoom-in')),
            'title' => (string) ($slide['title'] ?? ''),
            'format' => $slide['format']['title'] ?? [],
        ];
    }
}

$videoSlides = $video === null ? [] : [$video];
$slides = is_callable([Kampachi_Homepage::class, 'displayed_slides'])
    ? Kampachi_Homepage::displayed_slides($kampachiHome, $videoSlides, $images)
    : ($images !== [] ? $images : $videoSlides);
$count = count($slides);

if ($count === 0) {
    return;
}
?>
<section class="hero" id="home" aria-label="Kampachi Japanese dining" data-kc-home-hero>
    <?php foreach ($slides as $index => $slide) : ?>
        <div class="hero-cms-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
            aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>" data-kc-hero-slide>
            <?php if (isset($slide['mime'])) : ?>
                <video class="hero-video" muted playsinline preload="metadata"
                    <?php echo $count === 1 ? 'loop' : ''; ?> aria-label="Kampachi dining video">
                    <source src="<?php echo esc_url($slide['url']); ?>" type="<?php echo esc_attr($slide['mime']); ?>">
                </video>
            <?php else : ?>
                <div class="hero-cms-photo hero-cms-<?php echo esc_attr($slide['direction']); ?>"
                    style="background-image:url('<?php echo esc_url($slide['url']); ?>')"></div>
                <?php if ($slide['title'] !== '') : ?>
                    <div class="hero-cms-copy">
                        <?php echo Homepage_Content::text_element(
                            $slide['title'],
                            $slide['format'],
                            'div',
                            ['class' => 'hero-cms-title']
                        ); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (is_callable([Kampachi_Homepage::class, 'hero_controls'])) : ?>
        <?php Kampachi_Homepage::hero_controls($count, isset($slides[0]['mime'])); ?>
    <?php endif; ?>
</section>
