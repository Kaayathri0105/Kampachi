<?php
/**
 * Shared Kampachi WordPress footer.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

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
$publicAddress = kampachi_setting('footer_address');
$enquiryUrl = kampachi_setting('footer_cta_url', home_url('/contact-us/'));
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
            <a class="footer-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Kampachi home">
                <?php if ($footerLogo !== '') : ?>
                    <img src="<?php echo esc_url($footerLogo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" loading="lazy">
                <?php else : ?>
                    <span><?php echo esc_html(get_bloginfo('name')); ?></span>
                <?php endif; ?>
            </a>
            <nav class="footer-navigation" aria-label="Footer navigation">
                <h2 class="footer-heading"><?php echo esc_html(kampachi_setting('footer_explore_title')); ?></h2>
                <?php if (class_exists(\Kampachi\CMS\FooterNavigation::class) && \Kampachi\CMS\FooterNavigation::isConfigured()) : ?>
                    <?php \Kampachi\CMS\FooterNavigation::renderLinks(); ?>
                <?php elseif (has_nav_menu('footer')) : ?>
                    <?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'menu_class' => 'footer-links', 'fallback_cb' => false]); ?>
                <?php endif; ?>
            </nav>
            <section class="footer-address">
                <h2 class="footer-heading"><?php echo esc_html(kampachi_setting('footer_office_title')); ?></h2>
                <address><?php echo nl2br(esc_html($publicAddress)); ?></address>
            </section>
            <section class="footer-contact">
                <h2 class="footer-heading"><?php echo esc_html(kampachi_setting('footer_contact_title')); ?></h2>
                <?php if (is_email($publicEmail)) : ?>
                    <a href="<?php echo esc_url('mailto:' . $publicEmail); ?>"><?php echo esc_html($publicEmail); ?></a>
                <?php endif; ?>
                <?php if ($publicPhone !== '') : ?>
                    <a href="<?php echo esc_url('tel:' . preg_replace('/[^\d+]/', '', $publicPhone)); ?>"><?php echo esc_html($publicPhone); ?></a>
                <?php endif; ?>
                <div class="kc-footer-socials">
                    <?php foreach (['facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'youtube_url' => 'YouTube', 'tiktok_url' => 'TikTok'] as $setting => $label) : ?>
                        <?php $socialUrl = kampachi_setting($setting); ?>
                        <?php if ($socialUrl !== '') : ?><a href="<?php echo esc_url($socialUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?></a><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php if ($enquiryUrl !== '' && kampachi_setting('footer_cta_text') !== '') : ?>
            <div class="footer-cta"><a href="<?php echo esc_url($enquiryUrl); ?>"><?php echo esc_html(kampachi_setting('footer_cta_text')); ?> <span aria-hidden="true">↗</span></a></div>
            <?php endif; ?>
        </div>
        <div class="footer-bottom">
            <p class="copyright"><?php echo esc_html(kampachi_setting('footer_company', get_bloginfo('name'))); ?> © <?php echo esc_html(wp_date('Y')); ?></p>
            <?php $privacyPage = get_page_by_path('privacy-policy', OBJECT, 'page'); ?>
            <?php if ($privacyPage instanceof WP_Post && $privacyPage->post_status === 'publish') : ?>
                <a class="footer-privacy" href="<?php echo esc_url(get_permalink($privacyPage)); ?>"><?php echo esc_html(get_the_title($privacyPage)); ?></a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
