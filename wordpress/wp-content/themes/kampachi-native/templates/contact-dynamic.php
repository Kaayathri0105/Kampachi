<?php
/** @package KampachiNative */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$pageId = get_queried_object_id();
$title = kampachi_native_meta($pageId, 'intro_title') ?: get_the_title($pageId);
$subtitle = kampachi_native_meta($pageId, 'intro_subtitle');
$intro = kampachi_native_meta($pageId, 'intro_description');
$status = isset($_GET['enquiry']) && is_string($_GET['enquiry']) ? sanitize_key(wp_unslash($_GET['enquiry'])) : '';
$messages = [
    'sent' => 'Thank you. Your enquiry has been sent.',
    'invalid' => 'Please complete all required fields and try again.',
    'failed' => 'We could not send your enquiry. Please contact us directly by email.',
    'limited' => 'Too many submissions. Please try again later.',
    'unavailable' => 'Online enquiries are temporarily unavailable. Please contact us directly.',
];
?>
<main id="main-content" class="kc-editorial-page kc-contact-page">
    <?php kampachi_native_render_page_hero($pageId); ?>
    <?php kampachi_native_render_intro($title, $subtitle, $intro); ?>
    <div class="kc-editorial-container kc-contact-layout">
        <section class="kc-contact-details">
            <h2><?php echo esc_html(kampachi_native_label('contact_title')); ?></h2>
            <?php $contactPage = get_post($pageId); ?>
            <?php if ($contactPage instanceof WP_Post && trim($contactPage->post_content) !== '') : ?>
                <div class="kc-contact-content"><?php echo apply_filters('the_content', $contactPage->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
            <?php $contactEmail = kampachi_setting('contact_email'); ?>
            <?php if (is_email($contactEmail)) : ?><p><a href="<?php echo esc_url('mailto:' . $contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a></p><?php endif; ?>
            <?php $contactPhone = kampachi_setting('contact_phone'); ?>
            <?php if ($contactPhone !== '') : ?><p><?php echo esc_html($contactPhone); ?></p><?php endif; ?>
        </section>
        <section class="kc-contact-form-wrap">
            <h2><?php echo esc_html(kampachi_native_label('enquiry_title')); ?></h2>
            <?php if (isset($messages[$status])) : ?>
                <p class="kc-contact-notice" role="status"><?php echo esc_html($messages[$status]); ?></p>
            <?php endif; ?>
            <?php if (class_exists('Kampachi_Shell_Settings') && Kampachi_Shell_Settings::enquiry_recipients() !== []) : ?>
                <form class="kc-contact-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="kc_send_enquiry">
                    <?php wp_nonce_field('kc_send_enquiry', 'kc_enquiry_nonce'); ?>
                    <div class="kc-contact-honeypot" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <label>Your Name *<input type="text" name="name" maxlength="120" required autocomplete="name"></label>
                    <label>Email Address *<input type="email" name="email" maxlength="254" required autocomplete="email"></label>
                    <label>Subject *<input type="text" name="subject" maxlength="160" required></label>
                    <label>Message *<textarea name="message" rows="6" maxlength="5000" required></textarea></label>
                    <button type="submit"><?php echo esc_html(kampachi_native_label('enquiry_button')); ?> ↗</button>
                </form>
            <?php else : ?>
                <p>Contact us directly by email for enquiries.</p>
            <?php endif; ?>
        </section>
    </div>
</main>
