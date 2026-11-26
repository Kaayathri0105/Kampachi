<?php

/**
 * Redirect legacy award detail URLs to the Awards & Accolades landing page.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Awards;
use Kampachi\Native\Site_Language;

if (! defined('ABSPATH')) {
    exit;
}

$landing = Kampachi_Landings::page(Awards::PAGE_SLUG);
$localizedLanding = $landing instanceof WP_Post ? (Site_Language::posts([$landing])[0] ?? $landing) : null;
$landingUrl = $localizedLanding instanceof WP_Post && $localizedLanding->post_status === 'publish'
    ? get_permalink($localizedLanding)
    : false;

wp_safe_redirect($landingUrl ?: home_url('/' . Awards::PAGE_SLUG . '/'), 301);
exit;
