<?php
/**
 * Shared Kampachi WordPress footer.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Site_Language;

if (! defined('ABSPATH')) {
    exit;
}
?>
<?php
$footerLogoId = absint(kampachi_setting('footer_logo_id'));
$footerLogo = $footerLogoId > 0 ? wp_get_attachment_image_url($footerLogoId, 'full') : false;
if (! is_string($footerLogo) || $footerLogo === '') {
    $headerLogoId = absint(kampachi_setting('header_logo_id')) ?: absint(get_theme_mod('custom_logo'));
    $footerLogo = $headerLogoId > 0 ? wp_get_attachment_image_url($headerLogoId, 'full') : false;
}
$footerLogo = is_string($footerLogo) ? $footerLogo : '';
$publicEmail = kampachi_setting('contact_email');
$publicPhone = kampachi_setting('contact_phone');
$footerCompany = Site_Language::shell_text('footer', 'footer_company', kampachi_setting('footer_company', get_bloginfo('name')));
$companyName = trim(preg_replace(
    '/^(?:copyright\s*)?(?:©|\(c\))\s*\d{4}\s*|\s*(?:©|\(c\))\s*\d{4}\s*$/iu',
    '',
    $footerCompany
) ?? $footerCompany);
if ($companyName === '') {
    $companyName = get_bloginfo('name');
}

$privacyUrl = get_privacy_policy_url();
if ($privacyUrl === '') {
    foreach (['privacy-policy', 'privacy-and-policy'] as $slug) {
        $privacyPage = class_exists(Kampachi_Landings::class)
            ? Kampachi_Landings::page($slug)
            : null;
        $privacyPage ??= get_page_by_path($slug, OBJECT, 'page');
        if ($privacyPage instanceof WP_Post && $privacyPage->post_status === 'publish') {
            $privacyUrl = get_permalink($privacyPage);
            break;
        }
    }
}
$privacyUrl = Site_Language::url((string) $privacyUrl);

/*
 * Footer icons from Bootstrap Icons (https://icons.getbootstrap.com/).
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2019-2024 The Bootstrap Authors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
$footerSocials = [
    'facebook_url' => [
        'Facebook',
        'M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951' .
        'v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98' .
        'h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16' .
        'c3.824-.604 6.75-3.934 6.75-7.951',
    ],
    'instagram_url' => [
        'Instagram',
        'M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923' .
        'A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001' .
        'c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372' .
        'C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372' .
        'a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942' .
        'C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941' .
        'a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372' .
        'C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718' .
        'c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92' .
        'c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232' .
        'c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919' .
        'c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047' .
        's-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276' .
        'a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92' .
        'c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231' .
        'c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598' .
        'c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328' .
        'a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122' .
        'a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441' .
        'a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334',
    ],
    'youtube_url' => [
        'YouTube',
        'M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42' .
        'c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075' .
        'c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558' .
        'a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052' .
        'l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26' .
        'a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104' .
        'A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778' .
        'l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402' .
        'a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007' .
        'A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z',
    ],
    'tiktok_url' => [
        'TikTok',
        'M9 0h1.98c.144.715.54 1.617 1.235 2.512C12.895 3.389 13.797 4 15 4v2c-1.753 0-3.07-.814-4-1.829' .
        'V11a5 5 0 1 1-5-5v2a3 3 0 1 0 3 3z',
    ],
];
$publicAddress = Site_Language::shell_text('footer', 'footer_address', kampachi_setting('footer_address'));
$enquiryText = trim(kampachi_setting('footer_cta_text'));
if ($enquiryText === '') {
    $enquiryText = __('Click to Enquire', 'kampachi-native');
}
$enquiryText = Site_Language::shell_text('footer', 'footer_cta_text', $enquiryText);
$enquiryUrl = esc_url_raw(kampachi_setting('footer_cta_url'));
if ($enquiryUrl === '') {
    $enquiryUrl = home_url('/contact-us/#enquiry');
}
$enquiryUrl = Site_Language::url($enquiryUrl);
$footerBackgroundId = absint(kampachi_setting('footer_background_id'));
$footerBackground = $footerBackgroundId > 0
    ? wp_get_attachment_image_url($footerBackgroundId, 'full') : false;
if (! is_string($footerBackground) || $footerBackground === '') {
    $footerBackground = get_theme_file_uri('assets/images/footer-brush-texture.png');
}
$footerBackgroundStyle = '--kampachi-footer-bg: url(' . wp_json_encode(
    $footerBackground,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . ');';
?>
<footer class="site-footer paper-surface site-footer--brush" id="contact" style="<?php echo esc_attr($footerBackgroundStyle); ?>">
    <div class="footer-inner">
        <div class="footer-top">
            <a class="footer-brand" href="<?php echo esc_url(Site_Language::url(home_url('/')) ?: home_url('/')); ?>" aria-label="<?php esc_attr_e('Kampachi home', 'kampachi-native'); ?>">
                <?php if ($footerLogo !== '') : ?>
                    <img src="<?php echo esc_url($footerLogo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" loading="lazy">
                <?php else : ?>
                    <span><?php echo esc_html(get_bloginfo('name')); ?></span>
                <?php endif; ?>
            </a>
            <nav class="footer-navigation" aria-label="Footer navigation">
                <h2 class="footer-heading"><?php echo esc_html(Site_Language::shell_text('footer', 'footer_explore_title', kampachi_setting('footer_explore_title'))); ?></h2>
                <?php if (class_exists(\Kampachi\CMS\FooterNavigation::class) && \Kampachi\CMS\FooterNavigation::isConfigured()) : ?>
                    <?php \Kampachi\CMS\FooterNavigation::renderLinks(); ?>
                <?php elseif (has_nav_menu('footer')) : ?>
                    <?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'menu_class' => 'footer-links', 'fallback_cb' => false]); ?>
                <?php endif; ?>
            </nav>
            <section class="footer-address">
                <h2 class="footer-heading"><?php echo esc_html(Site_Language::shell_text('footer', 'footer_office_title', kampachi_setting('footer_office_title'))); ?></h2>
                <address><?php echo nl2br(esc_html($publicAddress)); ?></address>
            </section>
            <section class="footer-contact">
                <h2 class="footer-heading"><?php echo esc_html(Site_Language::shell_text('footer', 'footer_contact_title', kampachi_setting('footer_contact_title'))); ?></h2>
                <?php if (is_email($publicEmail)) : ?>
                    <a href="<?php echo esc_url('mailto:' . $publicEmail); ?>">
                        <svg class="kc-footer-icon" width="16" height="16" viewBox="0 0 16 16"
                            fill="currentColor" aria-hidden="true" focusable="false">
                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1
                                v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105
                                zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741
                                M1 11.105l4.708-2.897L1 5.383z"/>
                        </svg>
                        <span><?php echo esc_html($publicEmail); ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($publicPhone !== '') : ?>
                    <a href="<?php echo esc_url('tel:' . preg_replace('/[^\d+]/', '', $publicPhone)); ?>">
                        <svg class="kc-footer-icon" width="16" height="16" viewBox="0 0 16 16"
                            fill="currentColor" aria-hidden="true" focusable="false">
                            <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77
                                a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45
                                l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547
                                a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19
                                a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98
                                c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178
                                l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034
                                c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009
                                c-.362-1.03-.037-2.137.703-2.877z"/>
                        </svg>
                        <span><?php echo esc_html($publicPhone); ?></span>
                    </a>
                <?php endif; ?>
                <div class="kc-footer-socials">
                    <?php foreach ($footerSocials as $setting => [$label, $iconPath]) : ?>
                        <?php $socialUrl = kampachi_setting($setting); ?>
                        <?php if ($socialUrl !== '') : ?>
                            <a href="<?php echo esc_url($socialUrl); ?>" target="_blank" rel="noopener noreferrer"
                                aria-label="<?php echo esc_attr($label); ?>">
                                <svg class="kc-footer-icon" width="16" height="16" viewBox="0 0 16 16"
                                    fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="<?php echo esc_attr($iconPath); ?>"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php get_template_part('templates/footer-enquiry', null, ['label' => $enquiryText, 'url' => $enquiryUrl]); ?>
        </div>
        <div class="footer-bottom">
            <p class="copyright"><?php echo esc_html(Site_Language::shell_text('footer', 'footer_copyright', __('Copyright', 'kampachi-native'))); ?> &copy; <?php echo esc_html(wp_date('Y') . ' ' . $companyName); ?></p>
            <?php if (is_string($privacyUrl) && $privacyUrl !== '') : ?>
                <a class="footer-privacy" href="<?php echo esc_url($privacyUrl); ?>"><?php echo esc_html(Site_Language::shell_text('footer', 'footer_privacy', __('Privacy & Policy', 'kampachi-native'))); ?></a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
