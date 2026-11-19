<?php

declare(strict_types=1);

if (! defined('ABSPATH') || empty($args['slides'])) {
    return;
}

$slides = $args['slides'];
$count = count($slides);
?>
<section class="kc-landing-hero kc-offers-landing-hero" aria-label="Offers &amp; Events hero"
    data-kc-home-hero data-kc-hero-swipe>
    <?php foreach ($slides as $index => $slide) : ?>
        <div class="kc-landing-slide kc-motion-<?php echo esc_attr($slide['direction'] ?? 'none'); ?><?php echo $index === 0 ? ' is-active' : ''; ?>"
            aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>" data-kc-hero-slide
            <?php echo $index === 0 ? '' : 'inert'; ?>>
            <?php if (isset($slide['mime'])) : ?>
                <video class="kc-landing-video" muted playsinline preload="metadata"
                    <?php echo $count === 1 ? 'loop' : ''; ?> aria-label="Offers &amp; Events banner video">
                    <source src="<?php echo esc_url($slide['url']); ?>" type="<?php echo esc_attr($slide['mime']); ?>">
                </video>
            <?php else : ?>
                <img src="<?php echo esc_url($slide['url']); ?>" alt="<?php echo esc_attr($slide['alt'] ?? ''); ?>"
                    <?php echo $index === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"'; ?>>
                <?php if (($slide['title'] ?? '') !== '') : ?>
                    <div class="kc-offer-hero-copy">
                        <?php if (! empty($slide['link'])) : ?>
                            <a class="kc-offer-hero-title" href="<?php echo esc_url($slide['link']); ?>" data-kc-hero-link>
                                <?php echo esc_html($slide['title']); ?>
                            </a>
                        <?php else : ?>
                            <span class="kc-offer-hero-title"><?php echo esc_html($slide['title']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php Kampachi_Homepage::hero_controls($count, isset($slides[0]['mime'])); ?>
</section>
