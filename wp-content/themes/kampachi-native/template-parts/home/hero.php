<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

$videoId = absint($kampachiHome['hero_video_id'] ?? 0);
$video = $videoId > 0 ? wp_get_attachment_url($videoId) : false;
$mime = $videoId > 0 ? get_post_mime_type($videoId) : '';
$video = is_string($video) && is_string($mime) && str_starts_with($mime, 'video/') ? $video : '';
$slides = [];
foreach ((array) ($kampachiHome['hero_slides'] ?? []) as $slide) {
    if (! is_array($slide)) {
        continue;
    }

    $url = wp_get_attachment_image_url(absint($slide['image_id'] ?? 0), 'full');
    if (is_string($url) && $url !== '') {
        $slides[] = [
            'url' => $url,
            'direction' => sanitize_html_class((string) ($slide['direction'] ?? 'zoom-in')),
            'title' => wp_kses_post((string) ($slide['title'] ?? '')),
            'text' => wp_kses_post((string) ($slide['text'] ?? '')),
        ];
    }
}

if ($video === '' && $slides === []) {
    return;
}
?>
<section class="hero" id="home" aria-label="Kampachi Japanese dining">
    <?php if ($slides !== []) : ?>
        <?php foreach ($slides as $index => $slide) : ?>
            <div class="hero-cms-slide<?php echo $index === 0 ? ' is-active' : ''; ?>" aria-hidden="true">
                <div class="hero-cms-photo hero-cms-<?php echo esc_attr($slide['direction']); ?>" style="background-image:url('<?php echo esc_url($slide['url']); ?>')"></div>
                <?php if ($slide['title'] !== '') : ?>
                    <div class="hero-cms-copy">
                        <?php if ($slide['title'] !== '') : ?><div class="hero-cms-title"><?php echo $slide['title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php elseif ($video !== '') : ?>
        <video class="hero-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true" tabindex="-1">
            <source src="<?php echo esc_url($video); ?>" type="<?php echo esc_attr($mime); ?>">
        </video>
    <?php endif; ?>
</section>
