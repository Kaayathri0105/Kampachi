<?php
/**
 * 47in27 landing page: CMS-managed hero, introduction, campaign and prefectures.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('Kampachi_47in27')) {
    get_template_part('templates/landing');
    return;
}

$settings = Kampachi_47in27::options();
$currentPost = Kampachi_47in27::current_post($settings);
$isAutomatic = absint($settings['campaign_post_id']) === 0;
$campaignTitle = (string) $settings['campaign_title'];
$campaignDescription = (string) $settings['campaign_description'];
$campaignStart = (string) $settings['campaign_start'];
$campaignEnd = (string) $settings['campaign_end'];
$campaignImageId = absint($settings['campaign_image_id']);
$campaignLocations = Outlets::locationIds($settings['campaign_outlets']);

if ($currentPost instanceof WP_Post) {
    $startMeta = (string) get_post_meta($currentPost->ID, '_kampachi_date_start', true);
    $endMeta = (string) get_post_meta($currentPost->ID, '_kampachi_date_end', true);
    $postDescription = get_the_excerpt($currentPost);

    if ($isAutomatic) {
        $campaignTitle = get_the_title($currentPost);
        $campaignDescription = $postDescription !== '' ? $postDescription : $campaignDescription;
        $campaignStart = $startMeta !== '' ? $startMeta : $campaignStart;
        $campaignEnd = $endMeta !== '' ? $endMeta : $campaignEnd;
    } else {
        $campaignTitle = $campaignTitle !== '' ? $campaignTitle : get_the_title($currentPost);
        $campaignDescription = $campaignDescription !== '' ? $campaignDescription : $postDescription;
        $campaignStart = $campaignStart !== '' ? $campaignStart : $startMeta;
        $campaignEnd = $campaignEnd !== '' ? $campaignEnd : $endMeta;
    }

    if ($campaignImageId === 0 && has_post_thumbnail($currentPost)) {
        $campaignImageId = get_post_thumbnail_id($currentPost);
    }
}

$today = current_time('Y-m-d');
$showCampaign = $campaignStart !== '' && $campaignEnd !== ''
    && $campaignStart <= $today && $today <= $campaignEnd;
$startDate = $campaignStart !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $campaignStart, wp_timezone()) : false;
$endDate = $campaignEnd !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $campaignEnd, wp_timezone()) : false;
$campaignDates = $startDate instanceof DateTimeImmutable && $endDate instanceof DateTimeImmutable
    ? wp_date('j F Y', $startDate->getTimestamp()) . ' – ' . wp_date('j F Y', $endDate->getTimestamp())
    : '';
$videoId = absint($settings['hero_video_id']);
$videoUrl = $videoId > 0 ? wp_get_attachment_url($videoId) : false;
$heroSlides = is_array($settings['hero_slides']) ? $settings['hero_slides'] : [];
$heroSlides = array_values(array_filter($heroSlides, static function (mixed $slide): bool {
    return is_array($slide) && wp_attachment_is_image(absint($slide['image_id'] ?? 0));
}));
$prefectures = new WP_Query([
    'post_type' => 'kc_prefecture',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
]);
?>
<link rel="stylesheet" href="<?php echo esc_url(get_theme_file_uri('assets/site-theme.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url(get_theme_file_uri('assets/header.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url(get_theme_file_uri('assets/components/hero-banners.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url(get_theme_file_uri('assets/47in27.css')); ?>">
<?php if (get_stylesheet() === 'kampachi-wordpress-theme') : ?>
<script>window.kampachiUrlRoot = <?php echo wp_json_encode(home_url('/')); ?>;</script>
<script src="<?php echo esc_url(get_theme_file_uri('assets/components/header.js')); ?>"></script>
<?php endif; ?>
<main class="kappou-main krsb-main campaign-page kc47-page" id="home">
    <div class="paper-surface paper-world">
        <?php
        $slides = [];
        foreach ($heroSlides as $slide) {
            $imageId = absint($slide['image_id'] ?? 0);
            $url = wp_get_attachment_image_url($imageId, 'full');
            if (is_string($url) && $url !== '') {
                $slides[] = [
                    'url' => $url,
                    'alt' => (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
                    'direction' => $slide['direction'] ?? 'zoom-in',
                    'title' => $slide['title'] ?? '',
                    'text' => $slide['text'] ?? '',
                ];
            }
        }
        if ($slides === [] && is_string($videoUrl) && $videoUrl !== '') {
            $slides[] = ['url' => $videoUrl, 'type' => 'video', 'mime' => get_post_mime_type($videoId)];
        }
        kampachi_native_render_hero($slides, '', '47in27 hero banner');
        ?>

        <section class="kappou-intro kc47-intro" aria-labelledby="kc47-page-title">
            <div class="kappou-intro-content">
                <?php if ($settings['intro_subtitle'] !== '') : ?>
                    <div class="kappou-eyebrow"><?php echo wp_kses_post((string) $settings['intro_subtitle']); ?></div>
                <?php endif; ?>
                <h1 class="kappou-title" id="kc47-page-title"><?php echo wp_kses_post((string) $settings['intro_title']); ?></h1>
                <?php if ($settings['intro_description'] !== '') : ?>
                    <div class="kappou-intro-copy"><?php echo wp_kses_post((string) $settings['intro_description']); ?></div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (function_exists('kampachi_render_editor_content')) : ?>
            <?php kampachi_render_editor_content(); ?>
        <?php endif; ?>

        <section class="krsb-content kc47-current" aria-labelledby="kc47-campaign-heading">
            <?php if ($showCampaign) : ?>
                <div class="campaign-current-grid">
                    <article class="krsb-card campaign-promo-card">
                        <?php if ($campaignImageId > 0) : ?>
                            <?php echo wp_get_attachment_image($campaignImageId, 'large', false, ['loading' => 'lazy', 'class' => 'kc47-promo-photo']); ?>
                        <?php endif; ?>
                        <div class="campaign-promo-copy">
                            <?php if ($settings['campaign_subtitle'] !== '') : ?><div class="krsb-eyebrow"><?php echo wp_kses_post((string) $settings['campaign_subtitle']); ?></div><?php endif; ?>
                            <h2 id="kc47-campaign-heading"><?php echo wp_kses_post($campaignTitle); ?></h2>
                            <?php if ($campaignDates !== '') : ?><p class="kc47-dates"><strong><?php echo esc_html($campaignDates); ?></strong></p><?php endif; ?>
                            <?php if ($campaignDescription !== '') : ?><div class="kc47-description"><?php echo wp_kses_post($campaignDescription); ?></div><?php endif; ?>
                            <?php if ($campaignLocations !== []) : ?>
                                <div class="kc47-participating"><strong><?php echo esc_html(kampachi_native_label('locations')); ?></strong><ul>
                                    <?php foreach ($campaignLocations as $outletId) : ?>
                                        <li><a href="<?php echo esc_url(get_permalink($outletId)); ?>"><?php echo esc_html(Outlets::locationName($outletId)); ?></a></li>
                                    <?php endforeach; ?>
                                </ul></div>
                            <?php endif; ?>
                            <div class="campaign-promo-actions">
                                <?php foreach ([1, 2] as $number) : ?>
                                    <?php
                                    $label = (string) ($settings['cta_' . $number . '_title'] ?? '');
                                    $url = (string) ($settings['cta_' . $number . '_url'] ?? '');
                                    if ($label === '' || $url === '') {
                                        continue;
                                    }
                                    $external = str_starts_with($url, 'http') && wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url(home_url('/'), PHP_URL_HOST);
                                    ?>
                                    <a class="content-button <?php echo $number === 2 ? 'content-button--outline' : ''; ?>" href="<?php echo esc_url($url); ?>" <?php if ($external) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php echo esc_html($label); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                    <div class="campaign-terms">
                        <?php if ($settings['pin_subtitle'] !== '') : ?><div class="krsb-eyebrow"><?php echo wp_kses_post((string) $settings['pin_subtitle']); ?></div><?php endif; ?>
                        <h2><?php echo wp_kses_post((string) $settings['pin_title']); ?></h2>
                        <div class="kc47-pin-description"><?php echo wp_kses_post((string) $settings['pin_description']); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="dining-overview kc47-collection" aria-labelledby="kc47-collection-heading">
            <?php if ($settings['collection_subtitle'] !== '') : ?><div class="kc47-collection-eyebrow"><?php echo wp_kses_post((string) $settings['collection_subtitle']); ?></div><?php endif; ?>
            <h2 class="dining-overview-heading" id="kc47-collection-heading"><?php echo wp_kses_post((string) $settings['collection_title']); ?></h2>
            <?php if ($settings['collection_description'] !== '') : ?><div class="dining-overview-intro"><?php echo wp_kses_post((string) $settings['collection_description']); ?></div><?php endif; ?>
            <?php if ($prefectures->have_posts()) : ?>
                <div class="dining-grid campaign-regions">
                    <?php while ($prefectures->have_posts()) : $prefectures->the_post(); ?>
                        <article class="dining-card campaign-region-card">
                            <a class="kc47-region-link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('large', ['loading' => 'lazy']); ?>
                                <?php else : ?>
                                    <div class="kc47-region-placeholder" aria-hidden="true"></div>
                                <?php endif; ?>
                                <div class="dining-card-copy">
                                    <h3><?php the_title(); ?></h3>
                                    <p><?php echo esc_html(get_the_excerpt()); ?></p>
                                    <span class="kc-discover"><?php echo esc_html(kampachi_native_label('discover')); ?></span>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php wp_reset_postdata(); ?>
    </div>
</main>
<?php if (get_stylesheet() === 'kampachi-wordpress-theme') : ?>
<script src="<?php echo esc_url(get_theme_file_uri('assets/components/footer.js')); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url(get_theme_file_uri('assets/47in27.js')); ?>" defer></script>
