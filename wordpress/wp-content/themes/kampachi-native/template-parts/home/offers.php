<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

$limit = Homepage_Content::limit($kampachiHome, 'offers', 3, 3);
$offers = Homepage_Content::posts($kampachiHome, 'offer_ids', 'kc_offer', $limit);
if ($limit === 0 || $offers === []) {
    return;
}
?>
<section class="experiences-section" id="experiences" aria-labelledby="experiences-title">
    <div class="content-container">
        <h2 class="experiences-heading" id="experiences-title"><?php echo esc_html(wp_strip_all_tags(Homepage_Content::content($kampachiHome, 'offers', 'title', kampachi_setting('offers_title')))); ?></h2>
        <div class="experiences-subtitle home-cms-description"><?php echo Homepage_Content::description($kampachiHome, 'offers'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <div class="experience-grid">
            <?php foreach ($offers as $offer) : ?>
                <?php
                $imageUrl = Homepage_Content::image($offer->ID, 'offer');
                $description = get_the_excerpt($offer);
                ?>
                <article class="experience-card">
                    <?php if ($imageUrl !== '') : ?>
                        <img src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr($offer->post_title); ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <h3><?php echo esc_html($offer->post_title); ?></h3>
                    <p><?php echo esc_html(wp_strip_all_tags($description)); ?></p>
                    <a class="experience-card-link" href="<?php echo esc_url(get_permalink($offer)); ?>"><?php echo esc_html(kampachi_native_label('discover')); ?> <span aria-hidden="true">↗</span></a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="view-all-wrap">
            <a class="experience-card-link view-all-button" href="<?php echo esc_url(home_url('/promotion/')); ?>"><?php echo esc_html(kampachi_native_label('more')); ?> <span aria-hidden="true">↗</span></a>
        </div>
    </div>
</section>
