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
        'description' => wp_kses_post((string) ($dish['description'] ?? '')),
    ];
    if ($limit > 0 && count($dishes) >= $limit) {
        break;
    }
}

if ($dishes === []) {
    return;
}
$first = $dishes[0];
?>
<section class="home-signature-section" id="signature-dishes" aria-labelledby="signature-title">
    <div class="content-container">
        <div class="home-section-heading">
            <h2 class="experiences-heading" id="signature-title"><?php echo esc_html(wp_strip_all_tags(Homepage_Content::content($kampachiHome, 'dishes', 'title'))); ?></h2>
            <div class="home-section-description home-cms-description"><?php echo Homepage_Content::description($kampachiHome, 'dishes'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </div>
        <div class="signature-layout">
            <figure class="signature-feature-photo">
                <img id="signature-dish-image" src="<?php echo esc_url($first['image']); ?>" alt="<?php echo esc_attr($first['title']); ?>" loading="lazy" decoding="async">
                <figcaption id="signature-dish-caption" aria-live="polite"><?php echo esc_html($first['title']); ?></figcaption>
            </figure>
            <div class="signature-dish-list">
                <?php foreach ($dishes as $index => $dish) : ?>
                    <article class="signature-dish-row">
                        <p class="signature-dish-number"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></p>
                        <div>
                            <h3>
                                <button class="signature-dish-select" type="button" aria-controls="signature-dish-image" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                    data-image="<?php echo esc_url($dish['image']); ?>" data-alt="<?php echo esc_attr($dish['title']); ?>" data-caption="<?php echo esc_attr($dish['title']); ?>">
                                    <?php echo esc_html($dish['title']); ?>
                                </button>
                            </h3>
                            <div class="home-cms-description"><?php echo $dish['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
