<?php
/**
 * Press & Media highlights and archive.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\CMS\Press;
use Kampachi\CMS\PressLanding;

if (! defined('ABSPATH')) {
    exit;
}

$pageId = (int) $args['pageId'];
$landingUrl = get_permalink($pageId) ?: home_url('/press-media/');
$search = $_GET['press_search'] ?? '';
$search = is_string($search) ? sanitize_text_field(wp_unslash($search)) : '';
$normalizedSearch = str_replace('’', "'", $search);
$sort = ($_GET['press_order'] ?? '') === 'oldest' ? 'oldest' : 'newest';
$requestedPage = $_GET['press_page'] ?? 1;
$pageNumber = is_scalar($requestedPage) ? max(1, (int) $requestedPage) : 1;
$perPage = 12;
$highlights = [];
$archive = [];

foreach (PressLanding::posts($pageId) as $article) {
    if (Press::isHighlight($article->ID)) {
        $highlights[] = $article;
        continue;
    }

    $title = html_entity_decode(wp_strip_all_tags($article->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $publication = kampachi_native_meta($article->ID, 'press_publication');
    $date = kampachi_native_meta($article->ID, 'press_original_date') ?: get_the_date('', $article);
    $searchable = str_replace('’', "'", $title . ' ' . $publication);
    if ($normalizedSearch !== '' && mb_stripos($searchable, $normalizedSearch, 0, 'UTF-8') === false) {
        continue;
    }

    $archive[] = ['post' => $article, 'publication' => $publication, 'date' => $date];
}

usort($archive, static function (array $left, array $right) use ($sort): int {
    $a = $left['post'];
    $b = $right['post'];
    $order = strcmp($a->post_date, $b->post_date) ?: ($a->ID <=> $b->ID);

    return $sort === 'oldest' ? $order : -$order;
});

$pageCount = (int) ceil(count($archive) / $perPage);
$pageNumber = min($pageNumber, max($pageCount, 1));
$archive = array_slice($archive, ($pageNumber - 1) * $perPage, $perPage);
$pagination = $pageCount > 1 ? paginate_links([
    'base' => str_replace('999999999', '%#%', add_query_arg('press_page', 999999999, $landingUrl)),
    'format' => '',
    'current' => $pageNumber,
    'total' => $pageCount,
    'add_args' => array_filter(['press_search' => $search, 'press_order' => $sort]),
    'add_fragment' => '#press-archive',
    'prev_text' => __('Previous', 'kampachi-native'),
    'next_text' => __('Next', 'kampachi-native'),
]) : null;
$landingParams = [];
wp_parse_str((string) wp_parse_url($landingUrl, PHP_URL_QUERY), $landingParams);
?>
<main class="kc-landing-page kc-module-press-media kc-press-page">
    <?php kampachi_native_render_intro($args['title'], '', $args['description']); ?>

    <?php if ($highlights !== []) : ?>
        <section class="kc-press-section" aria-labelledby="press-highlights-title">
            <h2 id="press-highlights-title"><?php esc_html_e('Highlights', 'kampachi-native'); ?></h2>
            <div class="kc-press-carousel" data-press-carousel>
                <div class="kc-press-track" id="press-highlights-track" tabindex="0"
                    aria-label="<?php esc_attr_e('Highlighted press articles', 'kampachi-native'); ?>">
                    <?php foreach ($highlights as $article) : ?>
                        <?php
                        $image = kampachi_native_post_image($article->ID);
                        $url = Press::originalUrl($article->ID);
                        $title = get_the_title($article);
                        $excerpt = wp_strip_all_tags(get_the_excerpt($article));
                        ?>
                        <article class="kc-press-card">
                            <?php if ($image !== '') : ?>
                                <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <div class="kc-press-card-body">
                                <h3><?php echo esc_html($title); ?></h3>
                                <?php if ($excerpt !== '') : ?>
                                    <p><?php echo esc_html($excerpt); ?></p>
                                <?php endif; ?>
                                <?php if ($url !== '') : ?>
                                    <a class="kc-discover" href="<?php echo esc_url($url); ?>" target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="<?php echo esc_attr(sprintf(__('Discover %s', 'kampachi-native'), $title)); ?>">
                                        <?php echo esc_html(kampachi_native_label('discover')); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="kc-press-controls" data-press-controls hidden>
                    <button type="button" data-press-previous aria-controls="press-highlights-track"
                        aria-label="<?php esc_attr_e('Previous highlights', 'kampachi-native'); ?>">←</button>
                    <button type="button" data-press-next aria-controls="press-highlights-track"
                        aria-label="<?php esc_attr_e('Next highlights', 'kampachi-native'); ?>">→</button>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="kc-press-section" id="press-archive" aria-labelledby="press-archive-title">
        <h2 id="press-archive-title"><?php esc_html_e('Press Archive', 'kampachi-native'); ?></h2>
        <form class="kc-press-filters" method="get" action="<?php echo esc_url($landingUrl . '#press-archive'); ?>">
            <?php foreach ($landingParams as $name => $value) : ?>
                <?php if (is_scalar($value)) : ?>
                    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="kc-press-search">
                <label for="press-search"><?php esc_html_e('Search by title or publication', 'kampachi-native'); ?></label>
                <input id="press-search" name="press_search" type="search" value="<?php echo esc_attr($search); ?>">
            </div>
            <div>
                <label for="press-order"><?php esc_html_e('Sort by date', 'kampachi-native'); ?></label>
                <select id="press-order" name="press_order">
                    <option value="newest"<?php selected($sort, 'newest'); ?>>
                        <?php esc_html_e('Newest to oldest', 'kampachi-native'); ?>
                    </option>
                    <option value="oldest"<?php selected($sort, 'oldest'); ?>>
                        <?php esc_html_e('Oldest to newest', 'kampachi-native'); ?>
                    </option>
                </select>
            </div>
            <button class="view-all-button" type="submit"><?php esc_html_e('Apply', 'kampachi-native'); ?></button>
        </form>

        <?php if ($archive === []) : ?>
            <p><?php esc_html_e('No press articles found.', 'kampachi-native'); ?></p>
        <?php else : ?>
            <ul class="kc-press-list">
                <?php foreach ($archive as $entry) : ?>
                    <?php
                    $title = get_the_title($entry['post']);
                    $url = Press::originalUrl($entry['post']->ID);
                    ?>
                    <li>
                        <div>
                            <p class="kc-press-meta">
                                <?php echo esc_html(implode(' · ', array_filter([$entry['publication'], $entry['date']]))); ?>
                            </p>
                            <h3><?php echo esc_html($title); ?></h3>
                        </div>
                        <?php if ($url !== '') : ?>
                            <a class="kc-discover" href="<?php echo esc_url($url); ?>" target="_blank"
                                rel="noopener noreferrer"
                                aria-label="<?php echo esc_attr(sprintf(__('Discover %s', 'kampachi-native'), $title)); ?>">
                                <?php echo esc_html(kampachi_native_label('discover')); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (is_string($pagination)) : ?>
            <nav class="kc-press-pagination" aria-label="<?php esc_attr_e('Press archive pages', 'kampachi-native'); ?>">
                <?php echo wp_kses_post($pagination); ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
