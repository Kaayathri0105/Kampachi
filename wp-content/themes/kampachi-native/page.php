<?php
/**
 * Native WordPress pages — a single theme shell, no embedded HTML headers.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

get_header();

while (have_posts()) {
    the_post();
    $sourceId = absint(get_post_meta(get_the_ID(), '_kc_translation_of', true));
    $slug = $sourceId > 0
        ? get_post_field('post_name', $sourceId)
        : get_post_field('post_name', get_the_ID());

    if ($slug === 'catering-events' && class_exists(\Kampachi\CMS\Catering::class)) {
        get_template_part('templates/catering-dynamic');
    } elseif ($slug === 'sake' && class_exists(\Kampachi\CMS\Sake::class)) {
        get_template_part('templates/sake-dynamic');
    } elseif ($slug === '47in27') {
        get_template_part('templates/47in27');
    } elseif ($slug === 'promotion') {
        get_template_part('templates/promotion');
    } elseif ($slug === 'careers') {
        get_template_part('templates/careers-dynamic');
    } elseif ($slug === 'about-us') {
        get_template_part('templates/about-dynamic');
    } elseif ($slug === 'privacy-policy') {
        get_template_part('templates/privacy-dynamic');
    } elseif ($slug === 'contact-us') {
        get_template_part('templates/contact-dynamic');
    } elseif (
        $slug === 'home'
        || ($sourceId > 0 && is_callable([\Kampachi_Homepage::class, 'master_page'])
            && $sourceId === \Kampachi_Homepage::master_page()?->ID)
    ) {
        get_template_part('templates/home');
    } else {
        get_template_part('templates/landing');
    }
}

get_footer();
