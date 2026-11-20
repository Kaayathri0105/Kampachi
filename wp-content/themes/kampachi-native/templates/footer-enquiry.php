<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$status = isset($_GET['footer_enquiry']) && is_string($_GET['footer_enquiry'])
    ? sanitize_key(wp_unslash($_GET['footer_enquiry']))
    : '';
$notice = kampachi_native_enquiry_message($status);
$canSend = class_exists('Kampachi_Shell_Settings') && Kampachi_Shell_Settings::enquiry_recipients() !== [];
?>
<div class="footer-cta">
    <?php if ($canSend || $notice !== '') : ?>
        <details class="kc-footer-enquiry" <?php if ($notice !== '') : ?>open<?php endif; ?>>
            <summary class="kc-footer-enquiry-toggle">
                <span><?php echo esc_html($args['label']); ?></span>
                <span class="kc-footer-enquiry-chevron" aria-hidden="true"></span>
            </summary>
            <div class="kc-footer-enquiry-panel" id="footer-enquiry">
                <?php if ($notice !== '') : ?>
                    <p class="kc-footer-enquiry-notice" role="status"><?php echo esc_html($notice); ?></p>
                <?php endif; ?>
                <?php if ($canSend && $status !== 'sent') : ?>
                    <form class="kc-footer-enquiry-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"
                        aria-label="<?php esc_attr_e('Enquiry form', 'kampachi-native'); ?>">
                        <input type="hidden" name="action" value="kc_send_enquiry">
                        <input type="hidden" name="kc_enquiry_source" value="footer">
                        <?php wp_nonce_field('kc_send_enquiry', 'kc_enquiry_nonce'); ?>
                        <div class="kc-footer-enquiry-trap" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <label>
                            <span class="screen-reader-text"><?php esc_html_e('First name', 'kampachi-native'); ?></span>
                            <input type="text" name="first_name" maxlength="60" autocomplete="given-name"
                                placeholder="<?php esc_attr_e('First name', 'kampachi-native'); ?>" required>
                        </label>
                        <label>
                            <span class="screen-reader-text"><?php esc_html_e('Last name', 'kampachi-native'); ?></span>
                            <input type="text" name="last_name" maxlength="60" autocomplete="family-name"
                                placeholder="<?php esc_attr_e('Last name', 'kampachi-native'); ?>" required>
                        </label>
                        <label>
                            <span class="screen-reader-text"><?php esc_html_e('Email', 'kampachi-native'); ?></span>
                            <input type="email" name="email" maxlength="254" autocomplete="email"
                                placeholder="<?php esc_attr_e('Email', 'kampachi-native'); ?>" required>
                        </label>
                        <label>
                            <span class="screen-reader-text">
                                <?php esc_html_e('Telephone (optional)', 'kampachi-native'); ?>
                            </span>
                            <input type="tel" name="telephone" maxlength="40" autocomplete="tel"
                                placeholder="<?php esc_attr_e('Telephone (optional)', 'kampachi-native'); ?>">
                        </label>
                        <label>
                            <span class="screen-reader-text"><?php esc_html_e('Subject', 'kampachi-native'); ?></span>
                            <input type="text" name="subject" maxlength="160"
                                placeholder="<?php esc_attr_e('Subject', 'kampachi-native'); ?>" required>
                        </label>
                        <label>
                            <span class="screen-reader-text"><?php esc_html_e('Message', 'kampachi-native'); ?></span>
                            <textarea name="message" rows="5" maxlength="5000"
                                placeholder="<?php esc_attr_e('Message', 'kampachi-native'); ?>" required></textarea>
                        </label>
                        <button type="submit"><?php esc_html_e('Send', 'kampachi-native'); ?></button>
                    </form>
                <?php elseif (! $canSend) : ?>
                    <a href="<?php echo esc_url($args['url']); ?>"><?php esc_html_e('Contact us', 'kampachi-native'); ?></a>
                <?php endif; ?>
            </div>
        </details>
    <?php else : ?>
        <a href="<?php echo esc_url($args['url']); ?>">
            <span><?php echo esc_html($args['label']); ?></span>
            <span aria-hidden="true">↗</span>
        </a>
    <?php endif; ?>
</div>
