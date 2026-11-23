<?php
/**
 * Shared Kampachi WordPress header.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\OffersLanding;
use Kampachi\CMS\Outlets;

if (! defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<script>window.kampachiNativeShell = true;</script>
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Kampachi home">
            <?php
            $headerLogoId = absint(kampachi_setting('header_logo_id'));
            if ($headerLogoId <= 0) {
                $headerLogoId = absint(get_theme_mod('custom_logo'));
            }
            $headerLogo = $headerLogoId > 0 ? wp_get_attachment_image_url($headerLogoId, 'full') : false;
            ?>
            <?php if (is_string($headerLogo) && $headerLogo !== '') : ?>
                <img class="brand-logo" src="<?php echo esc_url($headerLogo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php else : ?>
                <span class="brand-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
            <?php endif; ?>
        </a>
        <div class="header-actions">
            <?php
            $headerSettings = get_option('kampachi_cms_settings', []);
            $headerSettings = is_array($headerSettings) ? $headerSettings : [];
            $configuredBookings = array_key_exists('header_booking_items', $headerSettings)
                && is_array($headerSettings['header_booking_items']);
            $bookingLinks = [];
            if ($configuredBookings && class_exists(Outlets::class)) {
                foreach ($headerSettings['header_booking_items'] as $bookingEntry) {
                    if (! is_array($bookingEntry)) {
                        continue;
                    }
                    $outletId = absint($bookingEntry['outlet_id'] ?? 0);
                    $bookingTitle = Outlets::locationName($outletId);
                    if ($bookingTitle === '' || isset($bookingLinks[$bookingTitle])) {
                        continue;
                    }
                    $bookingUrl = isset($bookingEntry['url']) && is_string($bookingEntry['url'])
                        ? trim($bookingEntry['url']) : '';
                    if ($bookingUrl === '') {
                        $bookingUrl = kampachi_native_meta($outletId, 'booking_url');
                    }
                    if ($bookingUrl === '' || ! preg_match('~^https?://~i', $bookingUrl)
                        || ! is_string(wp_parse_url($bookingUrl, PHP_URL_HOST))) {
                        continue;
                    }
                    $bookingLinks[$bookingTitle] = [
                        'title' => $bookingTitle,
                        'url' => $bookingUrl,
                    ];
                }
            } elseif (class_exists(Outlets::class)) {
                foreach (Outlets::publishedLocations() as $legacyOutlet) {
                    $legacyUrl = kampachi_native_meta($legacyOutlet->ID, 'booking_url');
                    $bookingLinks[] = [
                        'title' => Outlets::locationName($legacyOutlet),
                        'url' => $legacyUrl !== '' ? $legacyUrl : get_permalink($legacyOutlet),
                    ];
                }
            }
            ?>
            <?php if (kampachi_setting('reservation_enabled', '1') !== '0' && $bookingLinks !== []) : ?>
                <div class="reservation-wrap">
                    <button class="reservation-link reservation-trigger" type="button" aria-controls="reservation-menu" aria-expanded="false">
                        <span class="reservation-label"><?php echo esc_html(kampachi_setting('reservation_label', 'RESERVATION')); ?></span>
                        <span class="reservation-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="reservation-menu" id="reservation-menu" aria-label="Choose a Kampachi outlet" aria-hidden="true" inert hidden>
                        <?php foreach ($bookingLinks as $bookingLink) : ?>
                            <a class="reservation-option" href="<?php echo esc_url($bookingLink['url']); ?>"><?php echo esc_html($bookingLink['title']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php
            $languageLinks = class_exists('Kampachi_Translations')
                ? Kampachi_Translations::links(get_queried_object_id())
                : [];
            $currentLang = (string) get_post_meta(get_queried_object_id(), '_kc_language', true);
            $currentLang = $currentLang !== '' ? $currentLang : 'en';
            ?>
            <?php if (count($languageLinks) > 1) : ?>
                <div class="language-wrap">
                    <button class="language-button" type="button" aria-label="Choose language" aria-expanded="false">
                        <span class="language-flag" aria-hidden="true">🌐</span>
                        <span class="kc-language-code"><?php echo esc_html(strtoupper($currentLang)); ?></span>
                    </button>
                    <div class="language-popover" aria-label="Language options">
                        <?php foreach ($languageLinks as $code => $entry) : ?>
                            <a class="language-option" href="<?php echo esc_url($entry['url']); ?>" lang="<?php echo esc_attr($code); ?>" hreflang="<?php echo esc_attr($code); ?>" <?php echo $code === $currentLang ? 'aria-current="page"' : ''; ?>><?php echo esc_html($entry['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <button class="menu-button" type="button" aria-label="Open navigation menu" aria-controls="site-menu" aria-expanded="false"><span class="menu-lines" aria-hidden="true"></span></button>
        </div>
    </div>
    <?php
    $sectionNav = kampachi_native_header_post_navigation();
    $hasOfferCategories = false;
    if ($sectionNav === null && class_exists(OffersLanding::class)) {
        $offerLandingId = OffersLanding::queriedPageId();
        $isOfferListing = $offerLandingId > 0 || is_post_type_archive('kc_offer');
        $offerLanding = $offerLandingId > 0 ? get_post($offerLandingId) : null;
        if (is_singular('kc_offer') && class_exists(Kampachi_Landings::class)) {
            $offerLanding = Kampachi_Landings::page('promotion');
        }

        $offerCategories = $isOfferListing || is_singular('kc_offer')
            ? OffersLanding::categories($offerLanding?->ID ?? 0)
            : [];
        $currentCategory = $isOfferListing ? OffersLanding::selectedCategory($offerCategories) : '';
        $offerUrl = $offerLanding?->post_status === 'publish'
            ? get_permalink($offerLanding)
            : get_post_type_archive_link('kc_offer');
        if (count($offerCategories) > 1 && is_string($offerUrl) && $offerUrl !== '') {
            $hasOfferCategories = true;
            $sectionNav = ['label' => __('Offer categories', 'kampachi-native'), 'links' => []];
            foreach ($offerCategories as $key => $label) {
                $categoryUrl = $key === 'all' ? $offerUrl : add_query_arg('offer_category', $key, $offerUrl);
                $sectionNav['links'][] = [
                    'label' => $label,
                    'url' => $categoryUrl . '#offer-categories',
                    'current' => $key === $currentCategory ? 'true' : '',
                    'offerCategory' => $key,
                ];
            }
        }
    }
    ?>
    <?php if (! empty($sectionNav['links'])) : ?>
        <nav class="header-section-nav<?php echo $hasOfferCategories ? ' offer-category-nav' : ''; ?>"
             aria-label="<?php echo esc_attr($sectionNav['label']); ?>">
            <ul>
                <?php foreach ($sectionNav['links'] as $link) : ?>
                    <li>
                        <a class="nav-link" href="<?php echo esc_url($link['url']); ?>"
                            <?php if (isset($link['offerCategory'])) : ?>
                                data-offer-category-link="<?php echo esc_attr($link['offerCategory']); ?>"
                            <?php endif; ?>
                            <?php if ($link['current'] !== '') : ?>
                                aria-current="<?php echo esc_attr($link['current']); ?>"
                            <?php endif; ?>>
                            <?php echo kampachi_native_nav_label($link['label']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</header>
<nav class="menu-overlay" id="site-menu" aria-label="Main navigation" aria-hidden="false">
    <div class="primary-nav">
        <?php
        $headerNav = array_key_exists('header_nav_items', $headerSettings)
            ? (is_array($headerSettings['header_nav_items']) ? $headerSettings['header_nav_items'] : [])
            : (! has_nav_menu('primary') && class_exists('Kampachi_Header_Editor') ? Kampachi_Header_Editor::menu_items() : null);
        ?>
        <?php if (is_array($headerNav)) : ?>
            <ul class="nav-list">
                <?php foreach ($headerNav as $navIndex => $navItem) : ?>
                    <?php
                    if (! is_array($navItem)) {
                        continue;
                    }
                    $navTitle = isset($navItem['title']) && is_string($navItem['title']) ? $navItem['title'] : '';
                    $navUrl = isset($navItem['url']) && is_string($navItem['url']) ? $navItem['url'] : '';
                    $navChildren = isset($navItem['children']) && is_array($navItem['children']) ? $navItem['children'] : [];
                    if ($navTitle === '') {
                        continue;
                    }

                    if ($navChildren !== [] && class_exists('Kampachi_Header_Editor')
                        && Outlets::isLocationMenu($navTitle, $navUrl)) {
                        $navChildren = Kampachi_Header_Editor::location_children();
                    }

                    $hasChildren = $navChildren !== [];
                    ?>
                    <li class="nav-item<?php echo $hasChildren ? ' nav-item--dropdown' : ''; ?>">
                        <?php if ($hasChildren) : ?><div class="nav-item-row"><?php endif; ?>
                            <?php if ($navUrl !== '') : ?>
                                <a class="nav-link" href="<?php echo esc_url($navUrl); ?>"><?php echo kampachi_native_nav_label($navTitle); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
                            <?php else : ?>
                                <span class="nav-link"><?php echo kampachi_native_nav_label($navTitle); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <?php endif; ?>
                            <?php if ($hasChildren) : ?>
                                <button class="nav-dropdown-toggle" type="button" aria-controls="header-submenu-<?php echo esc_attr((string) $navIndex); ?>" aria-expanded="false" aria-label="Show <?php echo esc_attr($navTitle); ?>" data-menu-label="<?php echo esc_attr($navTitle); ?>"><span class="nav-dropdown-chevron" aria-hidden="true"></span></button>
                            </div>
                            <ul class="nav-sublist" id="header-submenu-<?php echo esc_attr((string) $navIndex); ?>" hidden>
                                <?php foreach ($navChildren as $navChild) : ?>
                                    <?php if (is_array($navChild) && ! empty($navChild['title']) && ! empty($navChild['url'])) : ?>
                                        <li><a class="nav-sublink" href="<?php echo esc_url((string) $navChild['url']); ?>"><?php echo esc_html((string) $navChild['title']); ?></a></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (has_nav_menu('primary')) : ?>
            <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'nav-list', 'fallback_cb' => false, 'depth' => 2]); ?>
        <?php endif; ?>
    </div>
</nav>
