<?php
/**
 * Careers landing page driven by published vacancies.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$pageId = get_queried_object_id();
$title = kampachi_native_meta($pageId, 'intro_title') ?: get_the_title($pageId);
$subtitle = kampachi_native_meta($pageId, 'intro_subtitle');
$intro = kampachi_native_meta($pageId, 'intro_description');
$jobs = new WP_Query([
    'post_type' => 'kc_job',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
    'no_found_rows' => true,
]);
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
    <section class="kc-editorial-container kc-careers-list" aria-label="Open vacancies">
        <?php if ($jobs->have_posts()) : ?>
            <?php while ($jobs->have_posts()) : $jobs->the_post(); ?>
                <?php
                $jobId = get_the_ID();
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
                <details class="kc-vacancy">
                    <summary><span><?php echo esc_html(get_the_title()); ?></span><span class="kc-vacancy-toggle" aria-hidden="true">+</span></summary>
                    <div class="kc-vacancy-details">
                        <?php if ($salary !== '') : ?><p class="kc-vacancy-salary"><strong>Salary:</strong> <?php echo esc_html($salary); ?></p><?php endif; ?>
                        <div class="kc-vacancy-description">
                            <?php echo esc_html(get_the_excerpt()); ?>
                        </div>
                        <div class="kc-vacancy-actions">
                            <a class="kc-discover" href="<?php echo esc_url(get_permalink($jobId)); ?>"><?php echo esc_html(kampachi_native_label('discover')); ?></a>
                            <?php if ($applyUrl !== '') : ?><a href="<?php echo esc_url($applyUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(kampachi_native_label('apply_online')); ?></a><?php endif; ?>
                            <?php if (is_email($applyEmail)) : ?><a href="<?php echo esc_url('mailto:' . $applyEmail . '?subject=' . rawurlencode('Application: ' . get_the_title())); ?>"><?php echo esc_html(kampachi_native_label('apply_email')); ?></a><?php endif; ?>
                            <?php if ($whatsappUrl !== '') : ?><a href="<?php echo esc_url($whatsappUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(kampachi_native_label('apply_whatsapp')); ?></a><?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        <?php endif; ?>
    </section>
</main>
