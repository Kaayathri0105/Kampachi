<?php
/**
 * One complete outlet page, editable from Outlets > individual WordPress post.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

while (have_posts()) :
    the_post();
    $outletId = get_the_ID();
    $subtitle = Outlets::meta($outletId, 'subtitle');
    $intro = Outlets::meta($outletId, 'outlet_intro');
    $dishes = Outlets::dishes($outletId);
    $menus = Outlets::menus($outletId);
    $hours = Outlets::meta($outletId, 'hours');
    $hoursNotes = Outlets::meta($outletId, 'outlet_hours_description');
    $phone = Outlets::meta($outletId, 'phone');
    $address = Outlets::meta($outletId, 'address');
    $mapUrl = Outlets::meta($outletId, 'map_url');
    $embedUrl = Outlets::map_embed_url($outletId);
    $hero = [];

    foreach (Outlets::hero_ids($outletId) as $mediaId) {
        $image = wp_attachment_is_image($mediaId);
        $url = $image ? wp_get_attachment_image_url($mediaId, 'full') : wp_get_attachment_url($mediaId);
        $mime = get_post_mime_type($mediaId);
        if (! is_string($url) || $url === '') {
            continue;
        }
        $hero[] = [
            'type' => $image ? 'image' : 'video',
            'url' => $url,
            'mime' => is_string($mime) ? $mime : 'video/mp4',
            'alt' => (string) get_post_meta($mediaId, '_wp_attachment_image_alt', true),
        'direction' => class_exists(\Kampachi\CMS\HeroMotion::class)
            ? \Kampachi\CMS\HeroMotion::direction($outletId, $mediaId)
            : 'zoom-in',
        ];
    }
    ?>
    <main class="kc-catering-page kc-outlet-page" id="main-content">
        <?php kampachi_native_render_hero($hero, '', 'Outlet hero'); ?>

        <section class="kc-catering-intro<?php echo $hero !== [] ? ' kc-catering-intro--with-hero' : ''; ?>" aria-labelledby="kc-outlet-heading">
            <div class="kc-catering-container kc-catering-intro-inner">
                <p class="kc-catering-eyebrow"><?php echo esc_html($subtitle); ?></p>
                <h1 id="kc-outlet-heading"><?php echo esc_html(get_the_title()); ?></h1>
                <?php if ($intro !== '') : ?>
                    <div class="kc-catering-copy"><?php echo wp_kses_post(wpautop($intro)); ?></div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($dishes !== []) : ?>
            <section class="kc-outlet-section" aria-labelledby="kc-signature-heading">
                <div class="kc-catering-container">
                    <div class="kc-outlet-section-heading"><h2 id="kc-signature-heading"><?php echo esc_html(kampachi_native_label('signature_dishes')); ?></h2></div>
                    <div class="kc-outlet-dishes">
                        <?php foreach ($dishes as $dish) : ?>
                            <?php $photoUrl = wp_get_attachment_image_url($dish['image_id'], 'large'); ?>
                            <?php if (! is_string($photoUrl) || $photoUrl === '') { continue; } ?>
                            <figure class="kc-outlet-dish">
                                <img src="<?php echo esc_url($photoUrl); ?>" alt="<?php echo esc_attr($dish['title'] !== '' ? $dish['title'] : $dish['description']); ?>" loading="lazy">
                                <figcaption>
                                    <?php if ($dish['title'] !== '') : ?><h3><?php echo esc_html($dish['title']); ?></h3><?php endif; ?>
                                    <p><?php echo esc_html($dish['description']); ?></p>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (trim((string) get_post_field('post_content', $outletId)) !== '') : ?>
            <section class="kc-outlet-section kc-outlet-additional"><div class="kc-catering-container kc-editor-area"><?php the_content(); ?></div></section>
        <?php endif; ?>

        <?php if ($hours !== '' || $hoursNotes !== '' || $phone !== '' || $address !== '' || $menus !== []) : ?>
            <section class="kc-outlet-section kc-outlet-information" aria-labelledby="kc-outlet-information-heading">
                <div class="kc-catering-container">
                    <div class="kc-outlet-section-heading"><h2 id="kc-outlet-information-heading"><?php echo esc_html(kampachi_native_label('visit_us')); ?></h2></div>
                    <div class="kc-outlet-information-grid">
                        <?php if ($hours !== '' || $hoursNotes !== '') : ?>
                            <div class="kc-outlet-information-item"><h3><?php echo esc_html(kampachi_native_label('opening_hours')); ?></h3>
                                <?php if ($hours !== '') : ?><p><?php echo nl2br(esc_html($hours)); ?></p><?php endif; ?>
                                <?php if ($hoursNotes !== '') : ?><p><?php echo nl2br(esc_html($hoursNotes)); ?></p><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($phone !== '') : ?>
                            <div class="kc-outlet-information-item"><h3><?php echo esc_html(kampachi_native_label('contact')); ?></h3>
                                <a href="<?php echo esc_url('tel:' . preg_replace('/[^+0-9]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
                            </div>
                        <?php endif; ?>
                        <?php if ($address !== '') : ?>
                            <div class="kc-outlet-information-item"><h3><?php echo esc_html(kampachi_native_label('address')); ?></h3><p><?php echo nl2br(esc_html($address)); ?></p></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($menus !== []) : ?>
                        <div class="kc-outlet-visit-menus" aria-labelledby="kc-outlet-menu-heading">
                            <h3 id="kc-outlet-menu-heading"><?php echo esc_html(kampachi_native_label('menus')); ?></h3>
                            <div class="kc-outlet-menus">
                                <?php foreach ($menus as $menu) : ?>
                                    <a href="<?php echo esc_url($menu['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <span><?php echo esc_html($menu['title']); ?></span><span aria-hidden="true">↗</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($embedUrl !== '' || $mapUrl !== '') : ?>
            <section class="kc-outlet-section kc-outlet-map-section" aria-labelledby="kc-outlet-map-heading"><div class="kc-catering-container">
                <div class="kc-outlet-section-heading"><h2 id="kc-outlet-map-heading"><?php echo esc_html(kampachi_native_label('location')); ?></h2></div>
                <?php if ($embedUrl !== '') : ?>
                    <iframe src="<?php echo esc_url($embedUrl); ?>" title="<?php echo esc_attr(get_the_title() . ' Google Map'); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                <?php endif; ?>
                <?php if ($mapUrl !== '') : ?><p><a class="kc-outlet-directions" href="<?php echo esc_url($mapUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(kampachi_native_label('directions')); ?> ↗</a></p><?php endif; ?>
            </div></section>
        <?php endif; ?>
    </main>
<?php endwhile; ?>
