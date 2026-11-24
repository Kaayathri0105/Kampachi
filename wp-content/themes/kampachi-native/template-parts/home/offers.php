<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

$offers = Homepage_Content::posts($kampachiHome + ['offer_ids' => []], 'offer_ids', 'kc_offer');
if ($offers === []) {
    return;
}

$perView = Homepage_Content::items_per_view(
    $kampachiHome,
    'offers',
    absint($kampachiHome['offers_limit'] ?? 3),
    min(count($offers), 3)
);
?>
<section class="experiences-section" id="experiences" aria-labelledby="experiences-title">
    <div class="content-container">
        <?php echo Homepage_Content::section_text(
            $kampachiHome,
            'offers',
            'title',
            'h2',
            ['class' => 'experiences-heading', 'id' => 'experiences-title'],
            kampachi_setting('offers_title')
        ); ?>
        <?php echo Homepage_Content::section_text(
            $kampachiHome,
            'offers',
            'description',
            'div',
            ['class' => 'experiences-subtitle home-cms-description']
        ); ?>
        <div class="kc-home-side-carousel" data-kc-content-carousel data-kc-carousel-slide
            data-kc-carousel-per-view="<?php echo esc_attr((string) $perView); ?>"
            role="region" aria-roledescription="carousel" aria-label="Offers and events">
            <div class="experience-grid kc-home-post-track" id="home-offers-track" data-kc-carousel-track
                style="--kc-carousel-columns:<?php echo esc_attr((string) $perView); ?>">
                <?php foreach ($offers as $index => $offer) : ?>
                    <?php
                    $imageUrl = Homepage_Content::image($offer->ID, 'offer');
                    $description = get_the_excerpt($offer);
                    $discover = kampachi_native_label('discover');
                    ?>
                    <article class="experience-card kc-linked-card"<?php echo $index >= $perView ? ' hidden' : ''; ?>>
                        <?php if ($imageUrl !== '') : ?>
                            <img src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr($offer->post_title); ?>" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <h3><?php echo esc_html($offer->post_title); ?></h3>
                        <p><?php echo esc_html(wp_strip_all_tags($description)); ?></p>
                        <a class="kc-discover kc-linked-card-link" href="<?php echo esc_url(get_permalink($offer)); ?>"
                            aria-label="<?php echo esc_attr($discover . ' ' . get_the_title($offer)); ?>">
                            <?php echo esc_html($discover); ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="view-all-wrap">
            <a class="view-all-button" href="<?php echo esc_url(home_url('/promotion/')); ?>"><?php echo esc_html(kampachi_native_label('more')); ?></a>
        </div>
    </div>
</section>
