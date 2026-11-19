<?php

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

/** @var array $kampachiHome */
$kampachiSakeSlides = Homepage_Content::sake_slides($kampachiHome);
if ($kampachiSakeSlides === []) {
    return;
}
$perView = Homepage_Content::items_per_view($kampachiHome, 'sake', 1, count($kampachiSakeSlides));
?>
<section class="home-sake-section" id="sake" aria-labelledby="home-sake-section-title">
    <div class="content-container home-sake-heading">
        <?php echo Homepage_Content::section_text(
            $kampachiHome,
            'sake',
            'title',
            'h2',
            ['id' => 'home-sake-section-title']
        ); ?>
        <?php echo Homepage_Content::section_text(
            $kampachiHome,
            'sake',
            'description',
            'div',
            ['class' => 'home-section-description home-cms-description']
        ); ?>
    </div>
    <div class="home-sake-carousel-shell" data-kc-content-carousel data-kc-carousel-autoplay="5000"
        data-kc-carousel-per-view="<?php echo esc_attr((string) $perView); ?>"
        role="region" aria-roledescription="carousel" aria-label="Artisanal sake">
        <div class="kc-home-post-track" id="home-sake-track" data-kc-carousel-track
            style="--kc-carousel-columns:<?php echo esc_attr((string) min($perView, 3)); ?>">
            <?php foreach ($kampachiSakeSlides as $slide) : ?>
                <article class="home-sake-layout<?php echo $slide['src'] === '' ? ' home-sake-layout--text-only' : ''; ?>">
                    <div class="home-sake-copy">
                        <h3><?php echo esc_html($slide['title']); ?></h3>
                        <?php if ($slide['description'] !== '') : ?>
                            <p><?php echo esc_html($slide['description']); ?></p>
                        <?php endif; ?>
                        <a class="home-sake-link" href="<?php echo esc_url($slide['href']); ?>">
                            <?php echo esc_html($slide['link']); ?>
                        </a>
                    </div>
                    <?php if ($slide['src'] !== '') : ?>
                        <div class="home-sake-carousel">
                            <img class="home-sake-image" src="<?php echo esc_url($slide['src']); ?>"
                                alt="<?php echo esc_attr($slide['alt']); ?>" loading="lazy" decoding="async">
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
