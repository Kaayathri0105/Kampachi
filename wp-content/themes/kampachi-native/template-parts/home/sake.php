<?php

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

/** @var array $kampachiHome */
$kampachiSakeSlides = Homepage_Content::sake_slides($kampachiHome);
if ($kampachiSakeSlides === []) {
    return;
}
$first = $kampachiSakeSlides[0];
$sakeSlideCount = count($kampachiSakeSlides);
?>
<section class="home-sake-section" id="sake" aria-labelledby="home-sake-section-title">
    <div class="content-container home-sake-heading">
        <h2 id="home-sake-section-title"><?php echo esc_html(wp_strip_all_tags(Homepage_Content::content($kampachiHome, 'sake', 'title'))); ?></h2>
        <div class="home-section-description home-cms-description"><?php echo Homepage_Content::description($kampachiHome, 'sake'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    </div>
    <div class="home-sake-carousel-shell" role="region" aria-roledescription="carousel" aria-label="Artisanal sake slides">
        <?php if ($sakeSlideCount > 1) : ?>
            <div class="home-sake-carousel-controls" role="group" aria-label="Sake carousel controls" hidden>
                <button id="home-sake-prev" type="button" aria-label="Previous sake slide" aria-controls="home-sake-layout">
                    <span aria-hidden="true">&#8592;</span>
                </button>
                <button id="home-sake-next" type="button" aria-label="Next sake slide" aria-controls="home-sake-layout">
                    <span aria-hidden="true">&#8594;</span>
                </button>
            </div>
        <?php endif; ?>
        <div class="content-container home-sake-layout<?php echo $first['src'] === '' ? ' home-sake-layout--text-only' : ''; ?>" id="home-sake-layout">
            <div class="home-sake-copy">
                <h2 id="home-sake-title"><?php echo esc_html($first['title']); ?></h2>
                <p id="home-sake-description"<?php echo $first['description'] === '' ? ' hidden' : ''; ?>><?php echo esc_html($first['description']); ?></p>
                <a class="home-sake-link" id="home-sake-link" href="<?php echo esc_url($first['href']); ?>"><?php echo esc_html($first['link']); ?></a>
            </div>
            <div class="home-sake-carousel"<?php echo $first['src'] === '' ? ' hidden' : ''; ?>>
                <img class="home-sake-image" id="home-sake-image"<?php if ($first['src'] !== '') : ?> src="<?php echo esc_url($first['src']); ?>"<?php endif; ?> alt="<?php echo esc_attr($first['alt']); ?>" decoding="async">
            </div>
        </div>
        <?php if ($sakeSlideCount > 1) : ?>
            <div class="home-sake-dots" role="group" aria-label="Choose a sake slide">
                <?php foreach ($kampachiSakeSlides as $index => $slide) : ?>
                    <button class="home-sake-dot" type="button" aria-label="<?php echo esc_attr(sprintf('Show sake slide %d of %d', $index + 1, $sakeSlideCount)); ?>"
                        aria-controls="home-sake-layout" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
