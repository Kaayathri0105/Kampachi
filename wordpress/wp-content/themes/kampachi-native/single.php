<?php
/**
 * Shared detail view for promotions, sake, prefectures, outlets and careers.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

get_header();

if (is_singular('kc_outlet') && class_exists(\Kampachi\CMS\Outlets::class)) {
    get_template_part('templates/outlet-detail');
    get_footer();
    return;
}

if (is_singular('kc_sake') && class_exists(\Kampachi\CMS\Sake::class)) {
    get_template_part('templates/sake-detail');
    get_footer();
    return;
}

while (have_posts()) {
    the_post();
    $postId = get_the_ID();
    $postType = get_post_type($postId);
    $image = kampachi_native_post_image($postId, 'full');
    $subtitle = kampachi_native_meta($postId, 'subtitle');
    $description = '';
    $backLinks = [
        'kc_offer' => ['promotion', 'back_offers'],
        'kc_sake' => ['sake', 'back_sake'],
        'kc_prefecture' => ['47in27', 'back_prefectures'],
        'kc_outlet' => ['outlets', 'back_outlets'],
        'kc_job' => ['careers', 'back_careers'],
    ];
    $back = $backLinks[$postType] ?? ['home', 'back_home'];
    ?>
    <main class="kc-detail-page">
        <?php if ($image !== '') : ?>
            <?php kampachi_native_render_hero([['url' => $image]], '', get_the_title() . ' hero'); ?>
        <?php endif; ?>
        <?php kampachi_native_render_intro(get_the_title(), $subtitle, $description); ?>
        <article class="kc-editor-area">
            <a class="kc-back" href="<?php echo esc_url(home_url('/' . $back[0] . '/')); ?>">← <?php echo esc_html(kampachi_native_label($back[1])); ?></a>
            <?php the_content(); ?>
            <?php
            foreach (['price' => 'price', 'date_start' => 'start_date', 'date_end' => 'end_date', 'address' => 'address', 'hours' => 'opening_hours'] as $field => $label) {
                $value = kampachi_native_meta($postId, $field);

                if ($value !== '') {
                    echo '<p><strong>' . esc_html(kampachi_native_label($label)) . ':</strong> ' . esc_html($value) . '</p>';
                }
            }
            ?>
            <div class="kc-page-actions">
                <?php foreach (['booking_url' => 'enquire', 'menu_url' => 'menus'] as $field => $label) : ?>
                    <?php $url = kampachi_native_meta($postId, $field); ?>
                    <?php if ($url !== '') : ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(kampachi_native_label($label)); ?> ↗</a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </article>
    </main>
    <?php
}

get_footer();
