<?php

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

if (! defined('ABSPATH') || empty($args['slides'])) {
    return;
}

$slides = $args['slides'];
$count = count($slides);
$hasVideo = in_array('video', array_column($slides, 'type'), true);
$label = $args['label'];
?>
<section class="hero kc-shared-hero" <?php if ($args['id'] !== '') : ?>id="<?php echo esc_attr($args['id']); ?>"<?php endif; ?>
    aria-label="<?php echo esc_attr($label); ?>" data-kc-home-hero data-kc-hero-swipe>
    <?php foreach ($slides as $index => $slide) : ?>
        <div class="hero-cms-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
            aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>" data-kc-hero-slide
            <?php echo $index === 0 ? '' : 'inert'; ?>>
            <?php if ($slide['type'] === 'video') : ?>
                <video class="hero-video" muted playsinline preload="<?php echo $index === 0 ? 'metadata' : 'none'; ?>"
                    <?php echo $count === 1 ? 'loop' : ''; ?> aria-label="<?php echo esc_attr($label); ?>">
                    <source src="<?php echo esc_url($slide['url']); ?>"
                        type="<?php echo esc_attr(($slide['mime'] ?? '') ?: 'video/mp4'); ?>">
                </video>
            <?php else : ?>
                <?php
                $link = $slide['link'] ?? '';
                $title = $slide['title'] ?? '';
                $text = $slide['text'] ?? '';
                $direction = sanitize_html_class($slide['direction'] ?? 'zoom-in');
                ?>
                <?php if ($link !== '') : ?>
                    <a class="kc-hero-image-link" href="<?php echo esc_url($link); ?>" data-kc-hero-link
                        aria-label="<?php echo esc_attr($title !== '' ? $title : __('View offer', 'kampachi-native')); ?>">
                <?php endif; ?>
                <img class="hero-cms-photo hero-cms-<?php echo esc_attr($direction); ?>"
                    src="<?php echo esc_url($slide['url']); ?>" alt="<?php echo esc_attr($slide['alt'] ?? ''); ?>"
                    <?php echo $index === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"'; ?>>
                <?php if ($link !== '') : ?>
                    </a>
                <?php elseif ($title !== '' || $text !== '') : ?>
                    <div class="hero-cms-copy">
                        <?php if ($title !== '') : ?>
                            <?php echo Homepage_Content::text_element(
                                $title,
                                $slide['format'] ?? [],
                                'div',
                                ['class' => 'hero-cms-title']
                            ); ?>
                        <?php endif; ?>
                        <?php if ($text !== '') : ?>
                            <p class="hero-cms-text"><?php echo esc_html($text); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (is_callable([Kampachi_Homepage::class, 'hero_controls'])) : ?>
        <?php Kampachi_Homepage::hero_controls($count, $hasVideo); ?>
    <?php endif; ?>
</section>
