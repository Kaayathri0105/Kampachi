<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

$title = Homepage_Content::content($kampachiHome, 'intro', 'title', kampachi_setting('hero_title'));
$description = Homepage_Content::description(
    $kampachiHome,
    'intro',
    kampachi_setting('hero_description')
);

if (trim($title) === '' && trim(wp_strip_all_tags($description)) === '') {
    return;
}
?>
<section class="flavours-section" id="about" aria-labelledby="flavours-title">
    <div class="content-container flavours-grid">
        <div class="flavours-copy">
            <?php if ($title !== '') : ?><h1 id="flavours-title"><?php echo esc_html(wp_strip_all_tags($title)); ?></h1><?php endif; ?>
            <div class="home-cms-description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </div>
    </div>
</section>
