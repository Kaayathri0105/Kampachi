<?php
/**
 * Brewery / brand profile and its CMS-managed bottle collection.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Site_Language;

use Kampachi\CMS\Outlets;
use Kampachi\CMS\Sake;
use Kampachi\CMS\SakeBottles;

if (! defined('ABSPATH')) {
    exit;
}

while (have_posts()) :
    the_post();
    $id = get_the_ID();
    $images = Sake::gallery($id, 'full');
    foreach ($images as &$image) {
        $image['direction'] = class_exists(\Kampachi\CMS\HeroMotion::class)
            ? \Kampachi\CMS\HeroMotion::direction($id, absint($image['id'] ?? 0))
            : 'zoom-in';
    }
    unset($image);
    $bottles = SakeBottles::all($id);
    $outletIds = array_merge(...array_column($bottles, 'outlet_ids'));
    if ($outletIds === []) {
        $outletIds = Outlets::locationIds(get_post_meta($id, '_kampachi_sake_outlet_ids', true));
    }
    $subtitle = kampachi_native_meta($id, 'subtitle');
    $description = Sake::field($id, 'description');
    $ctaText = Sake::field($id, 'enquiry_label') ?: kampachi_native_label('enquire');
    $ctaUrl = Sake::field($id, 'enquiry_url') ?: home_url('/contact-us/');
    $ctaUrl = Site_Language::url($ctaUrl);
    ?>
    <main class="kc-catering-page kc-sake-page kc-sake-detail<?php echo $images === [] ? ' kc-sake-detail--without-gallery' : ''; ?>" id="main-content">
        <?php kampachi_native_render_hero($images, '', get_the_title() . ' hero'); ?>
        <section class="kc-catering-detail-content">
            <div class="kc-catering-container">
                <div class="kc-sake-brand-intro">
                    <?php $landingUrl = Site_Language::url(home_url('/sake/')); ?>
                    <?php if ($landingUrl !== '') : ?>
                        <a class="kc-catering-detail-back" href="<?php echo esc_url($landingUrl); ?>">← <?php echo esc_html(kampachi_native_label('back_sake')); ?></a>
                    <?php endif; ?>
                    <p class="kc-catering-card-subtitle"><?php echo esc_html($subtitle); ?></p>
                    <h1><?php echo esc_html(get_the_title()); ?></h1>
                    <?php if ($description !== '') : ?>
                        <div class="kc-catering-detail-summary"><?php echo wp_kses_post(wpautop($description)); ?></div>
                    <?php endif; ?>
                    <?php get_template_part('template-parts/whatsapp-ctas', null, [
                        'outlet_ids' => $outletIds,
                        'existing_urls' => [$ctaUrl],
                    ]); ?>
                    <div class="kc-catering-detail-text kc-editor-area"><?php the_content(); ?></div>
                </div>
                <section class="kc-sake-bottle-collection" aria-label="<?php esc_attr_e('Sake bottles', 'kampachi-native'); ?>">
                    <?php if ($bottles !== []) : ?>
                        <div class="kc-sake-bottle-grid">
                            <?php foreach ($bottles as $bottle) : ?>
                                <?php get_template_part('template-parts/sake/bottle-card', null, ['bottle' => $bottle]); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <?php if ($ctaUrl !== '') : ?>
                    <div class="kc-sake-enquiry">
                        <a class="kc-catering-detail-cta" href="<?php echo esc_url($ctaUrl); ?>"><?php echo esc_html($ctaText); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
<?php endwhile; ?>
