<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use DateTimeImmutable;
use WP_Post;

/**
 * Structured Offers & Events content, administration and presentation data.
 */
final class Offers
{
    public const POST_TYPE = 'kc_offer';

    private const ASSET_VERSION = '3.4.2';

    private const HERO = '_kampachi_offer_hero_ids';
    private const INTRO = '_kampachi_offer_introduction';
    private const DETAILS = '_kampachi_offer_details';

    public static function boot(): void
    {
        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'registerBoxes']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save'], 20, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdmin']);
        add_action('admin_notices', [self::class, 'notice']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueFrontend'], 1000);
    }

    public static function registerBoxes(WP_Post $post): void
    {
        remove_meta_box('postexcerpt', self::POST_TYPE, 'normal');
        remove_meta_box('postimagediv', self::POST_TYPE, 'side');
        add_meta_box('kampachi_offer_content', 'Offers & Events — Content', [self::class, 'render'], self::POST_TYPE, 'normal', 'high');
    }

    public static function enqueueAdmin(string $hook): void
    {
        $screen = get_current_screen();

        if (
            ! in_array($hook, ['post.php', 'post-new.php'], true)
            || $screen === null || $screen->post_type !== self::POST_TYPE
        ) {
            return;
        }

        wp_enqueue_media();
        Content::enqueueDescriptionEditor();
        wp_enqueue_style('kampachi-offers-admin', plugins_url('../assets/offers-admin.css', __FILE__), [], (string) filemtime(dirname(__DIR__) . '/assets/offers-admin.css'));
        wp_enqueue_script(
            'kampachi-offers-admin',
            plugins_url('../assets/offers-admin.js', __FILE__),
            ['jquery', 'jquery-ui-sortable', Content::DESCRIPTION_EDITOR],
            (string) filemtime(dirname(__DIR__) . '/assets/offers-admin.js'),
            true
        );
    }

    public static function enqueueFrontend(): void
    {
        if (! is_singular(self::POST_TYPE)) {
            return;
        }

        $stylesheet = 'offer-detail-' . self::ASSET_VERSION . '.css';
        $path = dirname(__DIR__) . '/assets/' . $stylesheet;

        wp_enqueue_style(
            'kampachi-offer-detail',
            plugins_url('../assets/' . $stylesheet, __FILE__),
            [],
            (string) md5_file($path)
        );
        wp_enqueue_script('kampachi-offer-detail', plugins_url('../assets/offer-detail.js', __FILE__), [], (string) filemtime(dirname(__DIR__) . '/assets/offer-detail.js'), true);
    }

    public static function render(WP_Post $post): void
    {
        wp_nonce_field('kampachi_offer_save', 'kampachi_offer_nonce');
        echo '<div class="kc-offers-editor" data-offer-editor>';
        self::renderHero($post->ID);

        echo '<section class="kc-offers-panel"><h3>Short Description</h3>';
        echo '<label class="screen-reader-text" for="kc-offer-excerpt">Short Description (Excerpt)</label>';
        echo '<textarea class="widefat" id="kc-offer-excerpt" name="excerpt" rows="4" '
            . 'aria-describedby="kc-offer-excerpt-help">' . esc_textarea($post->post_excerpt) . '</textarea>';
        echo '<p class="description" id="kc-offer-excerpt-help">Appears on offer cards and the homepage.</p></section>';

        echo '<section class="kc-offers-panel"><h3>Introduction</h3>';
        self::renderDescription('kc-offer-introduction', 'kampachi_offer[introduction]', self::introduction($post->ID), 7);
        echo '</section>';

        self::renderSchedule($post->ID);
        echo '<section class="kc-offers-panel" data-offer-details-panel><h3>Offer Details</h3>';
        self::renderDetails($post->ID);
        self::renderCta($post->ID);
        echo '</section>';

        self::renderThumbnail($post->ID);
        echo '<input type="hidden" name="kampachi_offer[complete]" value="1"></div>';
    }

    private static function input(string $key, string $label, string $value, string $type = 'text'): void
    {
        echo '<p><label for="kc-offer-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input class="widefat" id="kc-offer-' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="kampachi_offer[' . esc_attr($key) . ']" value="' . esc_attr($value) . '"></p>';
    }

    private static function renderSchedule(int $postId): void
    {
        echo '<section class="kc-offers-panel"><h3>Offer Expiry</h3><div class="kc-offers-columns">';
        self::input('date_start', 'Start date', self::field($postId, 'date_start'), 'date');
        self::input('time_start', 'Start time (optional)', self::field($postId, 'time_start'), 'time');
        self::input('date_end', 'Expiry date', self::field($postId, 'date_end'), 'date');
        self::input('time_end', 'Expiry time (optional)', self::field($postId, 'time_end'), 'time');
        echo '</div></section>';
    }

    private static function renderHero(int $postId): void
    {
        $ids = self::heroIds($postId);
        echo '<section class="kc-offers-panel"><h3>Hero</h3>';
        echo '<p>Add images and videos for the full-screen banner on the individual offer page. '
            . 'Drag or use the arrow buttons to change their order.</p>';
        echo '<input type="hidden" name="kampachi_offer_hero_ids" value="' . esc_attr(implode(',', $ids)) . '">';
        echo '<div class="kc-offers-media-list" data-offer-hero-list>';

        foreach ($ids as $id) {
            $image = wp_attachment_is_image($id);
            $thumbnail = $image ? wp_get_attachment_image_url($id, 'thumbnail') : false;
            echo '<div class="kc-offers-media" data-offer-hero data-id="' . esc_attr((string) $id) . '"><span class="dashicons dashicons-move" aria-hidden="true"></span>';
            if (is_string($thumbnail)) {
                echo '<img src="' . esc_url($thumbnail) . '" alt="">';
            } else {
                echo '<span class="dashicons dashicons-format-video" aria-hidden="true"></span>';
            }
            echo '<strong>' . esc_html(get_the_title($id)) . '</strong><small>' . ($image ? 'Image' : 'Video') . '</small>';
            self::rowActions();
            echo '</div>';
        }

        echo '</div><p><button type="button" class="button" data-offer-add-hero>Add Images / Videos</button></p>';
        HeroMotion::editor($postId, 'kampachi_offer_hero_ids');
        echo '</section>';
    }

    private static function renderThumbnail(int $postId): void
    {
        $imageId = get_post_thumbnail_id($postId);
        $imageUrl = self::coverImage($postId, 'medium');

        echo '<section class="kc-offers-panel"><h3>Card Thumbnail Image</h3>';
        echo '<p>This image appears on the offer card. The card’s Discover button opens '
            . 'the individual page with its own full-screen banner images or videos.</p>';
        echo '<input type="hidden" name="kampachi_offer[thumbnail_id]" '
            . 'data-offer-thumbnail-id value="' . esc_attr((string) $imageId) . '">';
        echo '<input type="hidden" name="kampachi_offer[thumbnail_changed]" '
            . 'data-offer-thumbnail-changed value="0">';
        echo '<img class="kc-offers-thumbnail-preview" data-offer-thumbnail-preview '
            . 'src="' . esc_url($imageUrl) . '" alt="Card thumbnail preview"'
            . ($imageUrl === '' ? ' hidden' : '') . '>';
        echo '<p><button type="button" class="button" data-offer-pick-thumbnail>Choose Card Image</button> ';
        echo '<button type="button" class="button" data-offer-clear-thumbnail>Remove Image</button></p>';
        echo '</section>';
    }

    private static function rowActions(): void
    {
        echo '<div class="kc-offers-row-actions"><button type="button" class="button" '
            . 'data-offer-up aria-label="Move up">↑</button> ';
        echo '<button type="button" class="button" data-offer-down aria-label="Move down">↓</button> ';
        echo '<button type="button" class="button-link-delete" data-offer-remove>Remove</button></div>';
    }

    private static function renderCta(int $postId): void
    {
        echo '<section class="kc-offers-subpanel"><h4>CTA Button</h4>';
        echo '<p>Enter both the label and the full link to show a button on this offer page.</p>';
        echo '<div class="kc-offers-columns">';
        self::input('offer_cta_label', 'Button label', self::field($postId, 'offer_cta_label'));
        self::input('offer_cta_url', 'Button URL', self::field($postId, 'offer_cta_url'), 'url');
        echo '</div></section>';
    }

    private static function renderDetails(int $postId): void
    {
        echo '<section class="kc-offers-subpanel"><h4>Additional Details</h4><p>Add a title '
            . 'and description for each section. Drag or use the arrow buttons to arrange '
            . 'them.</p><div data-offer-details>';
        foreach (self::details($postId) as $index => $detail) {
            self::detailRow($detail, (string) $index);
        }
        echo '</div><p><button type="button" class="button" data-offer-add-detail>Add '
            . 'Detail Section</button></p><template data-offer-detail-template>';
        self::detailRow(['title' => '', 'description' => ''], '__INDEX__');
        echo '</template></section>';
    }

    /** @param array{title: string, description: string} $detail */
    private static function detailRow(array $detail, string $index): void
    {
        $prefix = 'kampachi_offer[details][' . $index . ']';
        echo '<div class="kc-offers-repeat" data-offer-detail><span class="dashicons '
            . 'dashicons-move" aria-hidden="true"></span><div class="kc-offers-repeat-body">';
        echo '<label><strong>Section title</strong><input class="widefat" name="' . esc_attr($prefix . '[title]') . '" value="' . esc_attr($detail['title']) . '"></label>';
        self::renderDescription('kc-offer-detail-body-' . $index, $prefix . '[description]', $detail['description']);
        echo '</div>';
        self::rowActions();
        echo '</div>';
    }

    private static function renderDescription(string $id, string $name, string $html, int $rows = 6): void
    {
        echo '<label for="' . esc_attr($id) . '"><strong>Description</strong></label>';
        echo '<textarea id="' . esc_attr($id) . '" class="widefat kc-offers-rich" rows="' . esc_attr((string) $rows)
            . '" name="' . esc_attr($name) . '">' . esc_textarea($html) . '</textarea>';
    }

    public static function save(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== self::POST_TYPE || ! self::canSave($postId)) {
            return;
        }

        $raw = wp_unslash($_POST['kampachi_offer']);
        if (($raw['complete'] ?? '') !== '1') {
            self::setNotice($postId, 'The offer fields could not be saved completely. Please reload the editor and '
                . 'try again. The previous offer fields were kept.');
            return;
        }

        self::saveSchedule($postId, $raw);
        self::saveThumbnail($postId, $raw);
        update_post_meta($postId, self::INTRO, wp_slash(wp_kses_post(self::string($raw['introduction'] ?? ''))));
        update_post_meta($postId, self::DETAILS, wp_slash(self::cleanDetails($raw['details'] ?? [])));
        self::saveCta($postId, $raw);
        $hero = self::string($_POST['kampachi_offer_hero_ids'] ?? '');
        update_post_meta($postId, self::HERO, self::cleanMediaIds(explode(',', wp_unslash($hero))));
    }

    /** @param array<string, mixed> $raw */
    private static function saveThumbnail(int $postId, array $raw): void
    {
        if (($raw['thumbnail_changed'] ?? '') !== '1') {
            return;
        }

        $value = $raw['thumbnail_id'] ?? null;
        if (! is_scalar($value) || preg_match('/^[0-9]+$/D', (string) $value) !== 1) {
            return;
        }

        $imageId = absint($value);
        if ($imageId === 0) {
            delete_post_thumbnail($postId);
            delete_post_meta($postId, '_kampachi_source_image_url');
        } elseif (wp_attachment_is_image($imageId)) {
            set_post_thumbnail($postId, $imageId);
        }
    }

    private static function canSave(int $postId): bool
    {
        if (
            wp_is_post_revision($postId) || wp_is_post_autosave($postId)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || ! current_user_can('edit_post', $postId)
            || ! isset($_POST['kampachi_offer']) || ! is_array($_POST['kampachi_offer'])
        ) {
            return false;
        }

        $nonce = $_POST['kampachi_offer_nonce'] ?? null;
        return is_string($nonce) && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'kampachi_offer_save') !== false;
    }

    /** @param array<string, mixed> $raw */
    private static function saveSchedule(int $postId, array $raw): void
    {
        $schedule = [];
        $message = 'The date and time were not saved. Check the date/time range. Other offer fields were saved.';

        foreach (['date_start', 'date_end', 'time_start', 'time_end'] as $key) {
            $value = array_key_exists($key, $raw) ? $raw[$key] : self::field($postId, $key);
            if (! is_string($value)) {
                self::setNotice($postId, $message);
                return;
            }
            $schedule[$key] = trim($value);
        }

        if (
            ! self::validDate($schedule['date_start']) || ! self::validDate($schedule['date_end'])
            || ! self::validTime($schedule['time_start']) || ! self::validTime($schedule['time_end'])
            || ! self::scheduleOrderValid($schedule)
        ) {
            self::setNotice($postId, $message);
            return;
        }

        foreach ($schedule as $key => $value) {
            update_post_meta($postId, '_kampachi_' . $key, $value);
        }
    }

    /** @param array<string, string> $schedule */
    private static function scheduleOrderValid(array $schedule): bool
    {
        if ($schedule['date_start'] === '' || $schedule['date_end'] === '') {
            return true;
        }
        if ($schedule['date_end'] < $schedule['date_start']) {
            return false;
        }
        if ($schedule['date_start'] !== $schedule['date_end']) {
            return true;
        }

        return $schedule['time_start'] === '' || $schedule['time_end'] === ''
            || $schedule['time_end'] >= $schedule['time_start'];
    }

    private static function validTime(string $value): bool
    {
        return $value === '' || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) === 1;
    }

    private static function validDate(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) !== 1 || substr($value, 0, 4) === '0000') {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function setNotice(int $postId, string $message): void
    {
        set_transient('kampachi_offer_notice_' . get_current_user_id() . '_' . $postId, $message, 300);
    }

    public static function notice(): void
    {
        $postId = isset($_GET['post']) && is_scalar($_GET['post']) ? absint($_GET['post']) : 0;
        if ($postId === 0) {
            return;
        }
        $key = 'kampachi_offer_notice_' . get_current_user_id() . '_' . $postId;
        $message = get_transient($key);
        if (! is_string($message) || $message === '') {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    public static function field(int $postId, string $key): string
    {
        $value = get_post_meta($postId, '_kampachi_' . $key, true);
        return is_scalar($value) ? (string) $value : '';
    }

    public static function introduction(int $postId): string
    {
        if (metadata_exists('post', $postId, self::INTRO)) {
            return self::field($postId, 'offer_introduction');
        }
        return '';
    }

    /** @return array<int, int> */
    private static function cleanMediaIds(mixed $raw): array
    {
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            $id = is_scalar($value) ? absint($value) : 0;
            $mime = $id > 0 ? get_post_mime_type($id) : false;
            if ($id > 0 && is_string($mime) && (wp_attachment_is_image($id) || str_starts_with($mime, 'video/'))) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /** @return array<int, int> */
    public static function heroIds(int $postId): array
    {
        if (metadata_exists('post', $postId, self::HERO)) {
            return self::cleanMediaIds(get_post_meta($postId, self::HERO, true));
        }
        $ids = get_post_meta($postId, '_kampachi_hero_image_ids', true);
        $ids = is_array($ids) ? $ids : [];
        $ids[] = get_post_meta($postId, '_kampachi_hero_video_id', true);
        return self::cleanMediaIds($ids);
    }

    /** @return array<int, array{type: string, id: int, url: string, mime: string, alt: string, direction: string}> */
    public static function hero(int $postId): array
    {
        $media = [];
        foreach (self::heroIds($postId) as $id) {
            $image = wp_attachment_is_image($id);
            $url = $image ? wp_get_attachment_image_url($id, 'full') : wp_get_attachment_url($id);
            if (! is_string($url) || $url === '') {
                continue;
            }
            $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
            $media[] = [
                'type' => $image ? 'image' : 'video',
                'id' => $id,
                'url' => $url,
                'mime' => (string) get_post_mime_type($id),
                'alt' => is_string($alt) ? $alt : '',
                'direction' => HeroMotion::direction($postId, $id),
            ];
        }
        return $media;
    }

    public static function coverImage(int $postId, string $size = 'large'): string
    {
        $featured = get_the_post_thumbnail_url($postId, $size);
        if (is_string($featured) && $featured !== '') {
            return $featured;
        }
        $legacy = self::field($postId, 'source_image_url');

        return in_array(wp_parse_url($legacy, PHP_URL_HOST), ['kampachi.com.my', 'www.kampachi.com.my'], true)
            ? $legacy
            : '';
    }

    /** @param array<string, mixed> $raw */
    private static function saveCta(int $postId, array $raw): void
    {
        if (isset($raw['offer_cta_label']) && is_string($raw['offer_cta_label'])) {
            $label = sanitize_text_field($raw['offer_cta_label']);
            update_post_meta($postId, '_kampachi_offer_cta_label', wp_slash($label));
        }

        if (isset($raw['offer_cta_url']) && is_string($raw['offer_cta_url'])) {
            $url = self::cleanCtaUrl($raw['offer_cta_url']);
            update_post_meta($postId, '_kampachi_offer_cta_url', $url);
        }
    }

    private static function cleanCtaUrl(string $value): string
    {
        $value = trim($value);
        $parts = wp_parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return esc_url_raw($value, ['http', 'https']);
    }

    /** @return array{label: string, url: string}|null */
    public static function cta(int $postId): ?array
    {
        $label = trim(self::field($postId, 'offer_cta_label'));
        $url = self::cleanCtaUrl(self::field($postId, 'offer_cta_url'));

        return $label !== '' && $url !== '' ? ['label' => $label, 'url' => $url] : null;
    }

    /** @return array<int, array{title: string, description: string}> */
    private static function cleanDetails(mixed $raw): array
    {
        $details = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = sanitize_text_field(self::string($row['title'] ?? ''));
            $description = wp_kses_post(self::string($row['description'] ?? ''));
            if ($title !== '' || trim(wp_strip_all_tags($description)) !== '') {
                $details[] = ['title' => $title, 'description' => $description];
            }
        }
        return $details;
    }

    /** @return array<int, array{title: string, description: string}> */
    public static function details(int $postId): array
    {
        if (metadata_exists('post', $postId, self::DETAILS)) {
            return self::cleanDetails(get_post_meta($postId, self::DETAILS, true));
        }
        $post = get_post($postId);
        $rows = [];
        if ($post instanceof WP_Post && trim($post->post_content) !== '') {
            $rows[] = ['title' => 'Offer Information', 'description' => $post->post_content];
        }
        foreach (['price' => 'Price', 'hours' => 'Availability', 'terms' => 'Terms & Conditions'] as $key => $title) {
            $description = self::field($postId, $key);
            if ($description !== '') {
                $rows[] = ['title' => $title, 'description' => $description];
            }
        }
        return self::cleanDetails($rows);
    }

    public static function dateRange(int $postId): string
    {
        $dates = [];
        foreach (['date_start', 'date_end'] as $key) {
            $value = self::field($postId, $key);
            $date = $value !== '' && self::validDate($value)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone())
                : false;
            $dates[$key] = $date !== false ? wp_date('j F Y', $date->getTimestamp(), wp_timezone()) : '';
        }
        if ($dates['date_start'] !== '' && $dates['date_end'] !== '') {
            return $dates['date_start'] === $dates['date_end'] ? $dates['date_start'] : $dates['date_start'] . ' – ' . $dates['date_end'];
        }
        if ($dates['date_end'] !== '') {
            return 'Valid until ' . $dates['date_end'];
        }
        return $dates['date_start'] !== '' ? 'From ' . $dates['date_start'] : '';
    }

    public static function timeRange(int $postId): string
    {
        $start = self::timeLabel(self::field($postId, 'time_start'));
        $end = self::timeLabel(self::field($postId, 'time_end'));

        if ($start !== '' && $end !== '') {
            return $start === $end ? $start : $start . ' – ' . $end;
        }
        if ($end !== '') {
            return 'Until ' . $end;
        }

        return $start !== '' ? 'From ' . $start : '';
    }

    private static function timeLabel(string $value): string
    {
        if ($value === '' || ! self::validTime($value)) {
            return '';
        }

        $time = DateTimeImmutable::createFromFormat('!H:i', $value);

        return $time !== false ? $time->format('g:i A') : '';
    }
}
