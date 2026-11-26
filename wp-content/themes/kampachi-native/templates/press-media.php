<?php
/**
 * Press and awards landing page features and archive.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

use Kampachi\Native\Site_Language;

use Kampachi\CMS\Awards;
use Kampachi\CMS\AwardsLanding;
use Kampachi\CMS\Press;
use Kampachi\CMS\PressLanding;

if (! defined('ABSPATH')) {
    exit;
}

$pageId = (int) $args['pageId'];
$isAwards = $args['isAwards'];
$landingEditor = $isAwards ? AwardsLanding::class : PressLanding::class;
$postType = $isAwards ? Awards::class : Press::class;
$landingUrl = get_permalink($pageId) ?: home_url('/' . $postType::PAGE_SLUG . '/');
$prefix = $isAwards ? 'award' : 'press';
$searchKey = $prefix . '_search';
$yearKey = $prefix . '_year';
$pageKey = $prefix . '_page';
$issuerKey = $isAwards ? 'award_organisation' : 'press_publication';
$dateKey = $isAwards ? 'award_year' : 'press_original_date';
$moduleClass = $isAwards ? 'kc-module-awards-accolades kc-awards-page' : 'kc-module-press-media';
$search = $_GET[$searchKey] ?? '';
$search = is_string($search) ? sanitize_text_field(wp_unslash($search)) : '';
$normalizedSearch = str_replace('’', "'", $search);
$requestedYear = $_GET[$yearKey] ?? '';
$selectedYear = is_string($requestedYear) && preg_match('/\A[1-9]\d{3}\z/', $requestedYear)
    ? (int) $requestedYear
    : 0;
$requestedPage = $_GET[$pageKey] ?? 1;
$pageNumber = is_scalar($requestedPage) ? max(1, (int) $requestedPage) : 1;
$perPage = 12;
$highlights = [];
$archive = [];
$availableYears = [];
$yearPattern = $isAwards
    ? '/(?<!\d)([1-9]\d{3})(?!\d)(?:\s*[-–—]\s*([1-9]\d{3})(?!\d))?/u'
    : '/(?<!\d)([1-9]\d{3})(?!\d)/';

foreach (Site_Language::posts($landingEditor::posts($pageId)) as $article) {
    $sourceId = $isAwards ? absint(get_post_meta($article->ID, '_kc_translation_of', true)) : 0;
    if ($isAwards && AwardsLanding::isHighlight($sourceId ?: $article->ID)) {
        $highlights[] = $article;
        continue;
    }

    $title = html_entity_decode(wp_strip_all_tags($article->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $publication = kampachi_native_meta($article->ID, $issuerKey);
    $date = kampachi_native_meta($article->ID, $dateKey);
    preg_match_all($yearPattern, $date, $matches, PREG_SET_ORDER);
    $years = [];
    foreach ($matches as $match) {
        $start = (int) $match[1];
        $end = (int) ($match[2] ?? $start);
        $years += array_fill_keys(range($start, $end), true);
    }

    if ($years === []) {
        $fallbackYear = (int) get_the_date('Y', $article);
        $years = $fallbackYear > 0 ? [$fallbackYear => true] : [];
    }

    $availableYears += $years;

    if ($date === '') {
        $date = get_the_date($isAwards ? 'Y' : '', $article);
    }

    $searchable = str_replace('’', "'", $title . ' ' . $publication);
    if ($normalizedSearch !== '' && mb_stripos($searchable, $normalizedSearch, 0, 'UTF-8') === false) {
        continue;
    }

    $archive[] = [
        'post' => $article,
        'publication' => $publication,
        'date' => $date,
        'years' => $years,
        'sortYear' => max([0, ...array_keys($years)]),
    ];
}

krsort($availableYears, SORT_NUMERIC);
$selectedYear = isset($availableYears[$selectedYear]) ? $selectedYear : 0;
if ($selectedYear !== 0) {
    $archive = array_filter($archive, static fn (array $entry): bool => isset($entry['years'][$selectedYear]));
}

usort($archive, static function (array $left, array $right): int {
    $a = $left['post'];
    $b = $right['post'];

    return ($right['sortYear'] <=> $left['sortYear'])
        ?: strcmp($b->post_date, $a->post_date)
        ?: ($b->ID <=> $a->ID);
});

$resultCount = count($archive);
$pageCount = (int) ceil($resultCount / $perPage);
$pageNumber = min($pageNumber, max($pageCount, 1));
$archive = array_slice($archive, ($pageNumber - 1) * $perPage, $perPage);
$pagination = $pageCount > 1 ? paginate_links([
    'base' => str_replace('999999999', '%#%', add_query_arg($pageKey, 999999999, $landingUrl)),
    'format' => '',
    'current' => $pageNumber,
    'total' => $pageCount,
    'add_args' => array_filter([$searchKey => $search, $yearKey => $selectedYear]),
    'add_fragment' => '#press-archive',
    'prev_text' => __('Previous', 'kampachi-native'),
    'next_text' => __('Next', 'kampachi-native'),
]) : null;
$resultMessage = $isAwards
    /* translators: %s is the number of matching awards. */
    ? _n('%s award found.', '%s awards found.', $resultCount, 'kampachi-native')
    /* translators: %s is the number of matching press articles. */
    : _n('%s press article found.', '%s press articles found.', $resultCount, 'kampachi-native');
$landingParams = [];
wp_parse_str((string) wp_parse_url($landingUrl, PHP_URL_QUERY), $landingParams);
?>
<main class="kc-landing-page kc-press-page <?php echo esc_attr($moduleClass); ?>">
    <?php kampachi_native_render_intro($args['title'], '', $args['description']); ?>

    <?php $videoUrl = $isAwards ? '' : PressLanding::videoUrl($pageId); ?>
    <?php if ($videoUrl !== '') : ?>
        <?php
        $featureTitle = kampachi_native_meta($pageId, 'press_video_title');
        $featureDescription = kampachi_native_meta($pageId, 'press_video_description');
        $ctaLabel = kampachi_native_meta($pageId, 'press_video_cta_label');
        $ctaUrl = esc_url(Site_Language::navigation_url(kampachi_native_meta($pageId, 'press_video_cta_url')));
        ?>
        <section class="kc-press-section kc-press-feature">
            <video class="kc-press-feature-video" src="<?php echo esc_url($videoUrl); ?>"
                aria-label="<?php echo esc_attr($featureTitle !== '' ? $featureTitle : $args['title']); ?>"
                controls playsinline preload="metadata"></video>
            <?php if ($featureTitle !== '' || $featureDescription !== '' || ($ctaLabel !== '' && $ctaUrl !== '')) : ?>
                <div class="kc-press-feature-copy">
                    <?php if ($featureTitle !== '') : ?>
                        <h2><?php echo esc_html($featureTitle); ?></h2>
                    <?php endif; ?>
                    <?php if ($featureDescription !== '') : ?>
                        <div class="kc-press-feature-description">
                            <?php echo wp_kses_post(wpautop(esc_html($featureDescription))); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($ctaLabel !== '' && $ctaUrl !== '') : ?>
                        <a class="view-all-button" href="<?php echo $ctaUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
                            <?php echo esc_html($ctaLabel); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($highlights !== []) : ?>
        <section class="kc-press-section" aria-label="<?php esc_attr_e('Highlighted awards', 'kampachi-native'); ?>">
            <div class="kc-press-carousel" data-press-carousel>
                <div class="kc-press-controls" data-press-controls hidden>
                    <button type="button" data-press-previous aria-controls="press-highlights-track"
                        aria-label="<?php esc_attr_e('Previous highlights', 'kampachi-native'); ?>">
                        <span aria-hidden="true">&#8592;</span>
                    </button>
                    <button type="button" data-press-next aria-controls="press-highlights-track"
                        aria-label="<?php esc_attr_e('Next highlights', 'kampachi-native'); ?>">
                        <span aria-hidden="true">&#8594;</span>
                    </button>
                </div>
                <div class="kc-press-track" id="press-highlights-track" tabindex="0" role="region"
                    aria-roledescription="carousel"
                    aria-label="<?php esc_attr_e('Highlighted awards', 'kampachi-native'); ?>">
                    <?php foreach ($highlights as $article) : ?>
                        <?php
                        $image = kampachi_native_post_image($article->ID);
                        $title = get_the_title($article);
                        $excerpt = wp_strip_all_tags(get_the_excerpt($article));
                        ?>
                        <article class="kc-press-card">
                            <?php if ($image !== '') : ?>
                                <div class="kc-press-card-media">
                                    <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy" decoding="async">
                                </div>
                            <?php endif; ?>
                            <div class="kc-press-card-body">
                                <h3><?php echo esc_html($title); ?></h3>
                                <?php if ($excerpt !== '') : ?>
                                    <p><?php echo esc_html($excerpt); ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="kc-press-section" id="press-archive"
        aria-label="<?php echo esc_attr($isAwards ? __('Awards archive', 'kampachi-native') : __('Press Archive', 'kampachi-native')); ?>">
        <form class="kc-press-filters" method="get" action="<?php echo esc_url($landingUrl . '#press-archive'); ?>">
            <?php foreach ($landingParams as $name => $value) : ?>
                <?php if (is_scalar($value)) : ?>
                    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="kc-press-search">
                <label for="press-search"><?php echo esc_html($isAwards ? __('Search by title or awarding body', 'kampachi-native') : __('Search by title or publication', 'kampachi-native')); ?></label>
                <input id="press-search" name="<?php echo esc_attr($searchKey); ?>" type="search" enterkeyhint="search"
                    value="<?php echo esc_attr($search); ?>">
            </div>
            <div>
                <label for="press-year"><?php esc_html_e('Year', 'kampachi-native'); ?></label>
                <select id="press-year" name="<?php echo esc_attr($yearKey); ?>">
                    <option value=""><?php esc_html_e('All years', 'kampachi-native'); ?></option>
                    <?php foreach (array_keys($availableYears) as $year) : ?>
                        <option value="<?php echo esc_attr((string) $year); ?>"<?php selected($selectedYear, $year); ?>>
                            <?php echo esc_html((string) $year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="kc-press-status" role="status" data-press-status></div>
        <div id="press-archive-results" data-results-message="<?php
            echo esc_attr(sprintf($resultMessage, number_format_i18n($resultCount)));
        ?>">
            <?php if ($archive === []) : ?>
                <p><?php echo esc_html($isAwards ? __('No awards found.', 'kampachi-native') : __('No press articles found.', 'kampachi-native')); ?></p>
            <?php else : ?>
                <ul class="kc-press-list">
                    <?php foreach ($archive as $entry) : ?>
                        <?php
                        $title = get_the_title($entry['post']);
                        $url = $isAwards ? '' : Press::originalUrl($entry['post']->ID);
                        $logo = $isAwards ? kampachi_native_post_image($entry['post']->ID, 'medium') : '';
                        $excerpt = $isAwards ? wp_strip_all_tags(get_the_excerpt($entry['post'])) : '';
                        $meta = implode(' · ', array_filter([$entry['publication'], $entry['date']]));
                        ?>
                        <li<?php echo $url !== '' ? ' class="kc-linked-card"' : ''; ?>>
                            <div class="kc-press-entry">
                                <?php if ($logo !== '') : ?>
                                    <img class="kc-award-logo" src="<?php echo esc_url($logo); ?>" alt=""
                                        loading="lazy" decoding="async">
                                <?php endif; ?>
                                <div>
                                    <p class="kc-press-meta"><?php echo esc_html($meta); ?></p>
                                    <h3><?php echo esc_html($title); ?></h3>
                                    <?php if ($excerpt !== '') : ?>
                                        <p class="kc-award-description"><?php echo esc_html($excerpt); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($url !== '') : ?>
                                <a class="kc-discover kc-linked-card-link" href="<?php echo esc_url($url); ?>"
                                    target="_blank" rel="noopener noreferrer"
                                    aria-label="<?php echo esc_attr(sprintf(__('Discover %s', 'kampachi-native'), $title)); ?>">
                                    <?php echo esc_html(kampachi_native_label('discover')); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (is_string($pagination)) : ?>
                <nav class="kc-press-pagination"
                    aria-label="<?php echo esc_attr($isAwards ? __('Awards archive pages', 'kampachi-native') : __('Press archive pages', 'kampachi-native')); ?>">
                    <?php echo wp_kses_post($pagination); ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</main>
