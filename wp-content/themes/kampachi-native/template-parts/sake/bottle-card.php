<?php

declare(strict_types=1);

use Kampachi\CMS\Outlets;
use Kampachi\CMS\SakeBottles;

if (! defined('ABSPATH') || ! isset($args['bottle']) || ! is_array($args['bottle'])) {
    return;
}

$bottle = $args['bottle'];
$pricing = SakeBottles::pricing($bottle);
$discountLabel = $pricing !== null ? rtrim(rtrim(number_format($pricing['percent'], 2), '0'), '.') : '';
$outlets = SakeBottles::outletIds($bottle['outlet_ids'] ?? []);
$image = $bottle['image_id'] > 0 ? wp_get_attachment_image($bottle['image_id'], 'large', false, [
    'class' => 'kc-sake-bottle-photo',
    'alt' => $bottle['name'],
    'loading' => 'lazy',
    'decoding' => 'async',
]) : '';
?>
<article class="kc-sake-bottle-card">
    <div class="kc-sake-bottle-card-image">
        <?php if ($image !== '') : ?>
            <?php
            // Escaped by wp_get_attachment_image.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $image;
            ?>
        <?php else : ?>
            <span class="kc-sake-bottle-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
    </div>
    <div class="kc-sake-bottle-card-body">
        <?php if ($bottle['drink_type'] !== '') : ?>
            <p class="kc-catering-card-subtitle"><?php echo esc_html($bottle['drink_type']); ?></p>
        <?php endif; ?>
        <h3><?php echo esc_html($bottle['name']); ?></h3>
        <?php if ($bottle['size'] !== '') : ?>
            <p class="kc-sake-bottle-size"><?php echo esc_html($bottle['size']); ?></p>
        <?php endif; ?>
        <?php if ($bottle['description'] !== '') : ?>
            <div class="kc-sake-bottle-description"><?php echo wp_kses_post(wpautop($bottle['description'])); ?></div>
        <?php endif; ?>
        <?php if ($pricing !== null) : ?>
            <div class="kc-sake-price" aria-label="Bottle price">
                <?php if ($pricing['percent'] > 0) : ?>
                    <del>RM <?php echo esc_html(number_format($pricing['original'], 2)); ?></del>
                <?php endif; ?>
                <strong>RM <?php echo esc_html(number_format($pricing['discounted'], 2)); ?></strong>
                <?php if ($pricing['percent'] > 0) : ?>
                    <span class="kc-sake-discount-label"><?php echo esc_html($discountLabel); ?>% OFF</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($outlets !== []) : ?>
            <div class="kc-sake-bottle-availability">
                <h4><?php echo esc_html(kampachi_native_label('locations')); ?></h4>
                <ul>
                    <?php foreach ($outlets as $outletId) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink($outletId)); ?>">
                                <?php echo esc_html(Outlets::locationName($outletId)); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</article>
