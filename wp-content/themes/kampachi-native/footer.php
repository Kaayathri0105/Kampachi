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
    // Xiaohongshu and Dianping marks from Simple Icons (https://simpleicons.org/).
    'xiaohongshu_url' => [
        'Xiaohongshu (小红书)',
        'M22.405 9.879c.002.016.01.02.07.019h.725' .
        'a.797.797 0 0 0 .78-.972.794.794 0 0 0-.884-.618.795.795 0 0 0-.692.794c0 .101-.002.666.001.777z' .
        'm-11.509 4.808c-.203.001-1.353.004-1.685.003a2.528 2.528 0 0 1-.766-.126.025.025 0 0 0-.03.014' .
        'L7.7 16.127a.025.025 0 0 0 .01.032' .
        'c.111.06.336.124.495.124.66.01 1.32.002 1.981 0 .01 0 .02-.006.023-.015l.712-1.545' .
        'a.025.025 0 0 0-.024-.036zM.477 9.91c-.071 0-.076.002-.076.01a.834.834 0 0 0-.01.08' .
        'c-.027.397-.038.495-.234 3.06-.012.24-.034.389-.135.607-.026.057-.033.042.003.112.046.092.681 1.523.787 1.74.008.015.011.02.017.02.008 0 .033-.026.047-.044.147-.187.268-.391.371-.606.306-.635.44-1.325.486-1.706.014-.11.021-.22.03-.33' .
        'l.204-2.616.022-.293c.003-.029 0-.033-.03-.034zm7.203 3.757a1.427 1.427 0 0 1-.135-.607' .
        'c-.004-.084-.031-.39-.235-3.06a.443.443 0 0 0-.01-.082c-.004-.011-.052-.008-.076-.008h-1.48' .
        'c-.03.001-.034.005-.03.034l.021.293' .
        'c.076.982.153 1.964.233 2.946.05.4.186 1.085.487 1.706.103.215.223.419.37.606.015.018.037.051.048.049.02-.003.742-1.642.804-1.765.036-.07.03-.055.003-.112' .
        'zm3.861-.913h-.872a.126.126 0 0 1-.116-.178l1.178-2.625a.025.025 0 0 0-.023-.035l-1.318-.003' .
        'a.148.148 0 0 1-.135-.21l.876-1.954a.025.025 0 0 0-.023-.035h-1.56c-.01 0-.02.006-.024.015' .
        'l-.926 2.068c-.085.169-.314.634-.399.938' .
        'a.534.534 0 0 0-.02.191.46.46 0 0 0 .23.378.981.981 0 0 0 .46.119h.59c.041 0-.688 1.482-.834 1.972' .
        'a.53.53 0 0 0-.023.172.465.465 0 0 0 .23.398c.15.092.342.12.475.12l1.66-.001c.01 0 .02-.006.023-.015' .
        'l.575-1.28a.025.025 0 0 0-.024-.035zm-6.93-4.937H3.1a.032.032 0 0 0-.034.033' .
        'c0 1.048-.01 2.795-.01 6.829 0 .288-.269.262-.28.262h-.74' .
        'c-.04.001-.044.004-.04.047.001.037.465 1.064.555 1.263.01.02.03.033.051.033.157.003.767.009.938-.014.153-.02.3-.06.438-.132.3-.156.49-.419.595-.765.052-.172.075-.353.075-.533.002-2.33 0-4.66-.007-6.991' .
        'a.032.032 0 0 0-.032-.032zm11.784 6.896c0-.014-.01-.021-.024-.022h-1.465' .
        'c-.048-.001-.049-.002-.05-.049v-4.66c0-.072-.005-.07.07-.07h.863c.08 0 .075.004.075-.074V8.393' .
        'c0-.082.006-.076-.08-.076h-3.5c-.064 0-.075-.006-.075.073v1.445c0 .083-.006.077.08.077h.854' .
        'c.075 0 .07-.004.07.07v4.624c0 .095.008.084-.085.084-.37 0-1.11-.002-1.304 0-.048.001-.06.03-.06.03' .
        'l-.697 1.519s-.014.025-.008.036' .
        'c.006.01.013.008.058.008 1.748.003 3.495.002 5.243.002.03-.001.034-.006.035-.033v-1.539zm4.177-3.43' .
        'c0 .013-.007.023-.02.024-.346.006-.692.004-1.037.004-.014-.002-.022-.01-.022-.024-.005-.434-.007-.869-.01-1.303 0-.072-.006-.071.07-.07' .
        'l.733-.003c.041 0 .081.002.12.015.093.025.16.107.165.204.006.431.002 1.153.001 1.153zm2.67.244' .
        'a1.953 1.953 0 0 0-.883-.222h-.18c-.04-.001-.04-.003-.042-.04V10.21c0-.132-.007-.263-.025-.394' .
        'a1.823 1.823 0 0 0-.153-.53 1.533 1.533 0 0 0-.677-.71 2.167 2.167 0 0 0-1-.258' .
        'c-.153-.003-.567 0-.72 0-.07 0-.068.004-.068-.065V7.76c0-.031-.01-.041-.046-.039H17.93' .
        's-.016 0-.023.007c-.006.006-.008.012-.008.023v.546c-.008.036-.057.015-.082.022h-.95' .
        'c-.022.002-.028.008-.03.032v1.481c0 .09-.004.082.082.082h.913c.082 0 .072.128.072.128V11.19' .
        's.003.117-.06.117h-1.482c-.068 0-.06.082-.06.082v1.445s-.01.068.064.068h1.457' .
        'c.082 0 .076-.006.076.079v3.225c0 .088-.007.081.082.081h1.43c.09 0 .082.007.082-.08v-3.27' .
        'c0-.029.006-.035.033-.035l2.323-.003c.098 0 .191.02.28.061a.46.46 0 0 1 .274.407' .
        'c.008.395.003.79.003 1.185 0 .259-.107.367-.33.367h-1.218' .
        'c-.023.002-.029.008-.028.033.184.437.374.871.57 1.303a.045.045 0 0 0 .04.026' .
        'c.17.005.34.002.51.003.15-.002.517.004.666-.01a2.03 2.03 0 0 0 .408-.075c.59-.18.975-.698.976-1.313' .
        'v-1.981c0-.128-.01-.254-.034-.38 0 .078-.029-.641-.724-.998z',
        '0 0 24 24',
    ],
    'dianping_url' => [
        'Dianping (大众点评)',
        'M6.922 0c-2.407 0-3.28.25-4.16.72A4.905 4.905 0 0 0 .72 2.763C.25 3.642 0 4.515 0 6.922v10.156' .
        'c0 2.407.25 3.28.72 4.16.471.88 1.162 1.57 2.042 2.041.88.47 1.753.721 4.16.721h10.156' .
        'c2.407 0 3.28-.25 4.16-.72a4.907 4.907 0 0 0 2.041-2.042c.471-.88.721-1.753.721-4.16V6.922' .
        'c0-2.407-.25-3.28-.72-4.16A4.905 4.905 0 0 0 21.237.72C20.358.25 19.485 0 17.078 0H6.922zm3.139 2.703' .
        'a3.07 3.07 0 0 1 3.07 3.07 3.07 3.07 0 0 1-3.07 3.07 3.07 3.07 0 0 1-3.07-3.07 3.07 3.07 0 0 1 3.07-3.07' .
        'zm6.994 1.617' .
        'c.145.66.195 1.35.13 2.057-.17 1.88-1.105 3.52-2.48 4.656 3.177.427 5.685 2.65 6.514 5.5.08.22.148.486.217.723' .
        'l-2.688.685' .
        'c-.06-.222-.127-.452-.19-.67-.751-2.622-3.55-4.172-6.273-3.466-2.718.704-4.338 3.391-3.642 6.021' .
        'l.199.729-2.688.687c-.07-.247-.132-.512-.195-.754a7.38 7.38 0 0 1-.215-2.554' .
        'c.199-2.2 1.345-4.1 3.014-5.36-2.634-.47-4.716-2.312-5.506-4.678l2.62-.677' .
        'c.566 1.534 2.012 2.689 3.8 2.84 2.446.207 4.604-1.544 4.818-3.91.036-.394.013-.78-.056-1.151' .
        'l2.62-.678z',
        '0 0 24 24',
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
                    <?php foreach ($footerSocials as $setting => $social) : ?>
                        <?php
                        [$label, $iconPath] = $social;
                        $viewBox = $social[2] ?? '0 0 16 16';
                        $iconClass = $setting === 'xiaohongshu_url' ? 'kc-footer-icon kc-footer-icon--wordmark' : 'kc-footer-icon';
                        $socialUrl = kampachi_setting($setting);
                        ?>
                        <?php if ($socialUrl !== '') : ?>
                            <a href="<?php echo esc_url($socialUrl); ?>" target="_blank" rel="noopener noreferrer"
                                aria-label="<?php echo esc_attr($label); ?>">
                                <svg class="<?php echo esc_attr($iconClass); ?>" width="16" height="16"
                                    viewBox="<?php echo esc_attr($viewBox); ?>"
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
