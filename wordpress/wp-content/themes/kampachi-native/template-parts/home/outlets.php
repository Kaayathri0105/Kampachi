<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\CMS\Outlets;
use Kampachi\Native\Homepage_Content;

$outlets = class_exists(Outlets::class) ? Outlets::publishedLocations() : [];
$hasCarousel = count($outlets) > 3;

if ($outlets === []) {
    return;
}
?>
<section class="outlets-section" id="outlets" aria-labelledby="outlets-title">
    <div class="content-container outlets-container">
        <?php echo Homepage_Content::section_text(
            $kampachiHome,
            'outlets',
            'title',
            'h2',
            ['class' => 'outlets-heading', 'id' => 'outlets-title']
        ); ?>
        <?php echo Homepage_Content::section_text(
            $kampachiHome,
            'outlets',
            'description',
            'div',
            ['class' => 'outlets-intro home-cms-description']
        ); ?>
        <div class="kc-home-side-carousel">
            <?php if ($hasCarousel) : ?>
                <div class="outlets-carousel-controls" aria-label="Restaurant carousel controls" hidden>
                    <button class="outlets-carousel-prev" type="button" aria-label="Previous restaurants" aria-controls="outlets-track">
                        <span aria-hidden="true">&#8592;</span>
                    </button>
                    <button class="outlets-carousel-next" type="button" aria-label="Next restaurants" aria-controls="outlets-track">
                        <span aria-hidden="true">&#8594;</span>
                    </button>
                </div>
            <?php endif; ?>
            <div class="outlets-grid<?php echo $hasCarousel ? ' outlets-grid--carousel' : ''; ?>" id="outlets-track"<?php if ($hasCarousel) : ?> tabindex="0" role="region" aria-label="Restaurants - scroll horizontally"<?php endif; ?>>
                <?php foreach ($outlets as $index => $outlet) : ?>
                    <?php
                    $imageUrl = Homepage_Content::image($outlet->ID, 'outlet');
                    $excerpt = get_the_excerpt($outlet);
                    $name = Outlets::locationName($outlet);
                    ?>
                    <article class="outlet-card">
                        <div class="outlet-card__media">
                            <?php if ($imageUrl !== '') : ?>
                                <img class="outlet-card__image" src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span class="outlet-card__number" aria-hidden="true"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></span>
                        </div>
                        <div class="outlet-card__content">
                            <h3><?php echo esc_html($name); ?></h3>
                            <?php if ($excerpt !== '') : ?><p class="outlet-card__description"><?php echo esc_html($excerpt); ?></p><?php endif; ?>
                            <a class="kc-discover kc-discover--card" href="<?php echo esc_url(get_permalink($outlet)); ?>" aria-label="<?php echo esc_attr('Discover Kampachi at ' . $name); ?>">
                                <?php echo esc_html(kampachi_native_label('discover')); ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
