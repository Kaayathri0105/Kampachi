<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

$dishes = [];
$limit = Homepage_Content::limit($kampachiHome, 'dishes', 0, 100);
foreach ((array) ($kampachiHome['dishes'] ?? []) as $dish) {
    if (! is_array($dish)) {
        continue;
    }

    $image = wp_get_attachment_image_url(absint($dish['image_id'] ?? 0), 'large');
    if (! is_string($image) || $image === '') {
        continue;
    }

    $dishes[] = [
        'image' => $image,
        'title' => wp_strip_all_tags((string) ($dish['title'] ?? '')),
        'description' => trim((string) ($dish['description'] ?? '')),
        'format' => $dish['format'] ?? [],
    ];
    if ($limit > 0 && count($dishes) >= $limit) {
        break;
    }
}

if ($dishes === []) {
    return;
}
?>
<section class="home-signature-section" id="signature-dishes" aria-labelledby="signature-title">
    <div class="content-container">
        <div class="home-section-heading">
            <?php echo Homepage_Content::section_text(
                $kampachiHome,
                'dishes',
                'title',
                'h2',
                ['class' => 'experiences-heading', 'id' => 'signature-title']
            ); ?>
            <?php echo Homepage_Content::section_text(
                $kampachiHome,
                'dishes',
                'description',
                'div',
                ['class' => 'home-section-description home-cms-description']
            ); ?>
        </div>
        <div class="signature-carousel-wrap">
            <div class="kc-home-side-carousel" data-kc-content-carousel data-kc-carousel-slide
                data-kc-carousel-per-view="1" role="region" aria-roledescription="carousel"
                aria-label="<?php esc_attr_e('Chef’s Recommendations', 'kampachi-native'); ?>">
                <div class="kc-home-post-track" id="signature-dish-track" data-kc-carousel-track aria-live="polite">
                    <?php foreach ($dishes as $dish) : ?>
                        <?php
                        $titleFormat = $dish['format']['title'] ?? [];
                        if (($titleFormat['tag'] ?? '') === 'p') {
                            unset($titleFormat['tag']);
                        }
                        $title = Homepage_Content::text_element(
                            $dish['title'] !== '' ? $dish['title'] : __('Dish', 'kampachi-native'),
                            $titleFormat,
                            'h3',
                            ['class' => 'signature-dish-title'],
                            true
                        );
                        ?>
                        <article class="signature-dish-slide">
                            <img src="<?php echo esc_url($dish['image']); ?>" alt="" loading="lazy" decoding="async">
                            <?php if ($dish['description'] !== '') : ?>
                                <details class="signature-dish-overlay" data-kc-dish-description>
                                    <summary><?php echo $title; ?></summary>
                                    <?php echo Homepage_Content::text_element(
                                        $dish['description'],
                                        $dish['format']['description'] ?? [],
                                        'div',
                                        ['class' => 'signature-dish-description home-cms-description']
                                    ); ?>
                                </details>
                            <?php else : ?>
                                <div class="signature-dish-overlay"><?php echo $title; ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
