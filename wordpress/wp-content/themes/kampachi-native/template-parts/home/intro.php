<?php
/** @var array $kampachiHome */

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

$title = Homepage_Content::content($kampachiHome, 'intro', 'title', kampachi_setting('hero_title'));
$description = Homepage_Content::content($kampachiHome, 'intro', 'description', kampachi_setting('hero_description'));

if (trim($title) === '' && trim(wp_strip_all_tags($description)) === '') {
    return;
}
?>
<section class="flavours-section" id="about" aria-labelledby="flavours-title">
    <div class="content-container flavours-grid">
        <div class="flavours-copy">
            <?php if ($title !== '') : ?>
                <?php echo Homepage_Content::text_element(
                    $title,
                    $kampachiHome['format']['intro_title'] ?? [],
                    'h1',
                    ['id' => 'flavours-title'],
                    true
                ); ?>
            <?php endif; ?>
            <?php echo Homepage_Content::text_element(
                $description,
                $kampachiHome['format']['intro_description'] ?? [],
                'div',
                ['class' => 'home-cms-description']
            ); ?>
        </div>
    </div>
</section>
