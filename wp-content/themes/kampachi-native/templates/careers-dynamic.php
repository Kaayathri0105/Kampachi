<?php
/**
 * Careers landing page driven by published vacancies.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Site_Language;

use Kampachi\CMS\CareersLanding;

if (! defined('ABSPATH')) {
    exit;
}

$pageId = get_queried_object_id();
$title = kampachi_native_meta($pageId, 'intro_title') ?: get_the_title($pageId);
$subtitle = kampachi_native_meta($pageId, 'intro_subtitle');
$intro = kampachi_native_meta($pageId, 'intro_description');
$jobs = class_exists(CareersLanding::class)
    ? CareersLanding::posts($pageId)
    : get_posts([
        'post_type' => 'kc_job',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
        'suppress_filters' => false,
    ]);
$jobs = Site_Language::posts($jobs);
$applicationLabel = Site_Language::text('Apply');
$status = isset($_GET['kc_application']) && is_string($_GET['kc_application'])
    ? sanitize_key(wp_unslash($_GET['kc_application'])) : '';
$selectedJob = isset($_GET['kc_vacancy']) && is_string($_GET['kc_vacancy']) ? absint($_GET['kc_vacancy']) : 0;
$resumeLimit = class_exists(CareersLanding::class) ? CareersLanding::resumeLimit() : 0;
$applicationMessage = match ($status) {
    'sent' => Site_Language::text('Thank you. Your application has been sent.'),
    'invalid' => Site_Language::text('Check your name and email, then try again.'),
    'resume' => Site_Language::text('Select a PDF résumé within the size limit.'),
    'limited' => Site_Language::text('Too many submissions. Please try again later.'),
    'failed' => Site_Language::text('Your application could not be sent. Please try again.'),
    'unavailable' => Site_Language::text('Online applications are temporarily unavailable.'),
    default => '',
};
?>
<main id="main-content" class="kc-editorial-page kc-careers-page">
    <?php kampachi_native_render_page_hero($pageId); ?>
    <?php kampachi_native_render_intro($title, $subtitle, $intro); ?>
    <?php $careersPage = get_post($pageId); ?>
    <?php if ($careersPage instanceof WP_Post && trim($careersPage->post_content) !== '') : ?>
        <div class="kc-editorial-container kc-about-story">
            <?php echo apply_filters('the_content', $careersPage->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>
    <section class="kc-editorial-container kc-careers-list" aria-label="<?php echo esc_attr(Site_Language::text('Open vacancies')); ?>">
        <?php foreach ($jobs as $job) : ?>
            <?php
            $jobId = $job->ID;
            $jobTitle = get_the_title($job);
            $jobContent = get_the_content(null, false, $job);
            $jobExcerpt = $jobContent === '' ? get_the_excerpt($job) : '';
            $showStatus = $jobId === $selectedJob && $applicationMessage !== '';
            $canApply = $resumeLimit > 0 && ! post_password_required($job);
            $salaryMin = kampachi_native_meta($jobId, 'salary_min');
            $salaryMax = kampachi_native_meta($jobId, 'salary_max');
            $salary = '';
            if (is_numeric($salaryMin) && is_numeric($salaryMax) && (float) $salaryMax >= (float) $salaryMin) {
                $salary = 'RM ' . number_format((float) $salaryMin, 0) . ' – RM ' . number_format((float) $salaryMax, 0);
            } elseif (is_numeric($salaryMin)) {
                $salary = 'From RM ' . number_format((float) $salaryMin, 0);
            } elseif (is_numeric($salaryMax)) {
                $salary = 'Up to RM ' . number_format((float) $salaryMax, 0);
            }
            $applyUrl = kampachi_native_meta($jobId, 'apply_url');
            $applyEmail = kampachi_native_meta($jobId, 'apply_email') ?: kampachi_native_meta($jobId, 'email');
            $whatsapp = kampachi_native_meta($jobId, 'apply_whatsapp') ?: kampachi_native_meta($jobId, 'whatsapp');
            $whatsappUrl = '';
            if (preg_match('~^https?://(wa\.me|api\.whatsapp\.com)/~i', $whatsapp)) {
                $whatsappUrl = $whatsapp;
            } elseif (preg_match('/^\+?[\d\s()\-]{8,24}$/', $whatsapp)) {
                $whatsappUrl = 'https://wa.me/' . preg_replace('/\D/', '', $whatsapp);
            }
            ?>
            <details class="kc-vacancy" id="vacancy-<?php echo esc_attr((string) $jobId); ?>" <?php echo $showStatus ? 'open' : ''; ?>>
                <summary>
                    <span><?php echo esc_html($jobTitle); ?></span>
                    <span class="kc-vacancy-button"><?php echo esc_html($applicationLabel); ?></span>
                </summary>
                <div class="kc-vacancy-details">
                    <?php if ($salary !== '') : ?><p class="kc-vacancy-salary"><strong><?php echo esc_html(kampachi_native_label('salary')); ?>:</strong> <?php echo esc_html($salary); ?></p><?php endif; ?>
                    <?php if ($jobContent !== '') : ?>
                        <div class="kc-vacancy-description">
                            <?php echo apply_filters('the_content', $jobContent); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    <?php elseif ($jobExcerpt !== '') : ?>
                        <p class="kc-vacancy-description"><?php echo esc_html($jobExcerpt); ?></p>
                    <?php endif; ?>
                    <?php if ($canApply) : ?>
                        <details class="kc-vacancy-application" <?php echo $showStatus ? 'open' : ''; ?>>
                            <summary class="kc-vacancy-button"><?php echo esc_html($applicationLabel); ?></summary>
                            <?php if ($showStatus) : ?>
                                <p class="kc-contact-notice" role="<?php echo $status === 'sent' ? 'status' : 'alert'; ?>">
                                    <?php echo esc_html($applicationMessage); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (! $showStatus || $status !== 'sent') : ?>
                                <form class="kc-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(CareersLanding::APPLICATION_ACTION); ?>">
                                    <input type="hidden" name="vacancy" value="<?php echo esc_attr((string) $jobId); ?>">
                                    <input type="hidden" name="kc_application_nonce" value="<?php echo esc_attr(wp_create_nonce(CareersLanding::APPLICATION_ACTION . '_' . $jobId)); ?>">
                                    <?php wp_referer_field(); ?>
                                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr((string) $resumeLimit); ?>">
                                    <div class="kc-contact-honeypot" aria-hidden="true" inert>
                                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                                    </div>
                                    <label>
                                        <?php echo esc_html(Site_Language::text('Name')); ?>
                                        <input type="text" name="name" autocomplete="name" maxlength="120" required>
                                    </label>
                                    <label>
                                        <?php echo esc_html(Site_Language::text('Email')); ?>
                                        <input type="email" name="email" autocomplete="email" maxlength="254" required>
                                    </label>
                                    <label>
                                        <?php echo esc_html(Site_Language::text('Upload résumé')); ?>
                                        <input type="file" name="resume" accept=".pdf,application/pdf" required>
                                        <small><?php echo esc_html(sprintf(Site_Language::text('PDF only, up to %s.'), size_format($resumeLimit))); ?></small>
                                    </label>
                                    <button type="submit"><?php echo esc_html(Site_Language::text('Submit application')); ?></button>
                                </form>
                            <?php endif; ?>
                        </details>
                    <?php endif; ?>
                    <?php if ($applyUrl !== '' || is_email($applyEmail) || $whatsappUrl !== '') : ?>
                        <div class="kc-vacancy-actions">
                            <?php if ($applyUrl !== '') : ?><a href="<?php echo esc_url($applyUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(kampachi_native_label('apply_online')); ?></a><?php endif; ?>
                            <?php if (is_email($applyEmail)) : ?><a href="<?php echo esc_url('mailto:' . $applyEmail . '?subject=' . rawurlencode('Application: ' . $jobTitle)); ?>"><?php echo esc_html(kampachi_native_label('apply_email')); ?></a><?php endif; ?>
                            <?php if ($whatsappUrl !== '') : ?><a href="<?php echo esc_url($whatsappUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(kampachi_native_label('apply_whatsapp')); ?></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </section>
</main>
