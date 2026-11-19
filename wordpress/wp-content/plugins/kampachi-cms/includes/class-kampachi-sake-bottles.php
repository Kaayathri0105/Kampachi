<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Error;
use WP_Post;

/**
 * Repeatable bottles belonging to one Sake brewery or brand.
 */
final class SakeBottles
{
    public const META_KEY = '_kampachi_sake_bottles';

    private const TEXT_FIELDS = ['name', 'drink_type', 'size', 'description'];

    public static function boot(): void
    {
        add_action('add_meta_boxes', [self::class, 'registerBox']);
        add_action('save_post_kc_sake', [self::class, 'savePost'], 25, 2);
        add_action('admin_notices', [self::class, 'renderNotice']);
    }

    public static function registerBox(): void
    {
        add_meta_box(
            'kampachi_sake_bottles',
            'Bottles — Drink Types, Prices & Locations',
            [self::class, 'renderBox'],
            Sake::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function renderBox(WP_Post $post): void
    {
        $bottles = self::all($post->ID, includeHidden: true);
        $outlets = Outlets::publishedLocations();

        wp_nonce_field('kampachi_save_sake_bottles_' . $post->ID, 'kampachi_sake_bottles_nonce');
        echo '<div class="kc-sake-bottles-editor" data-kc-bottles-editor>';
        echo '<p>One post = one brewery / brand. Add all its bottles below. '
            . 'Each bottle has its own drink type, description, price and locations.</p>';
        echo '<p class="description">Tick Visible to show a bottle on the website. '
            . 'Drag bottles to reorder, or use Move up / Move down. '
            . 'Changes are saved when you Update or Publish this brewery.</p>';
        echo '<div class="kc-sake-bottles-list" data-kc-bottles-list>';

        foreach ($bottles as $index => $bottle) {
            self::renderRow((string) $index, $bottle, $outlets);
        }

        echo '</div>';
        echo '<p class="kc-sake-bottles-empty" data-kc-bottles-empty' . ($bottles !== [] ? ' hidden' : '')
            . '>No bottles added yet. Use Add Bottle to create the first one.</p>';
        echo '<template id="kc-sake-bottle-template">';
        self::renderRow('__INDEX__', self::emptyRow('__BOTTLE_ID__'), $outlets);
        echo '</template>';
        echo '<p><button type="button" class="button button-secondary" data-kc-add-bottle>+ Add Bottle</button></p>';
        echo '<span class="screen-reader-text" aria-live="polite" data-kc-bottles-status></span>';
        // Last successful form control: an incomplete PHP input must never erase bottles.
        echo '<input type="hidden" name="kampachi_sake_bottles_complete" value="1">';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $bottle
     * @param array<int, WP_Post> $outlets
     */
    private static function renderRow(string $index, array $bottle, array $outlets): void
    {
        $prefix = 'kampachi_sake_bottles[' . $index . ']';
        $htmlId = 'kc-sake-bottle-' . $index . '-';
        $imageUrl = $bottle['image_id'] > 0
            ? wp_get_attachment_image_url($bottle['image_id'], 'thumbnail')
            : false;

        echo '<div class="kc-sake-bottle" data-kc-bottle role="group" aria-labelledby="'
            . esc_attr($htmlId . 'heading') . '">';
        echo '<div class="kc-sake-bottle-heading">';
        echo '<span class="dashicons dashicons-move kc-sake-bottle-handle" aria-hidden="true"></span>';
        $number = is_numeric($index) ? (string) ((int) $index + 1) : '1';
        echo '<h3 id="' . esc_attr($htmlId . 'heading') . '">Bottle <span data-kc-bottle-number>'
            . esc_html($number) . '</span></h3>';
        echo '<button type="button" class="button" data-kc-bottle-up>Move up</button> ';
        echo '<button type="button" class="button" data-kc-bottle-down>Move down</button> ';
        echo '<button type="button" class="button-link-delete" data-kc-remove-bottle>Remove bottle</button></div>';
        echo '<input type="hidden" name="' . esc_attr($prefix . '[id]') . '" value="' . esc_attr($bottle['id']) . '">';
        $visibilityName = esc_attr($prefix . '[visible]');
        echo '<input type="hidden" name="' . $visibilityName . '" value="0">';
        echo '<p><label><input type="checkbox" name="' . $visibilityName . '" value="1"'
            . checked($bottle['visible'], true, false) . '> Visible</label></p>';
        self::renderFields($bottle, $prefix, $htmlId);

        echo '</div><p class="description">Leave the price blank to hide the price on the website.</p>';
        echo '<div><label for="' . esc_attr($htmlId . 'description') . '">Bottle description / tasting notes</label>';
        echo '<textarea class="widefat" rows="4" data-kc-sake-rich id="' . esc_attr($htmlId . 'description')
            . '" name="' . esc_attr($prefix . '[description]') . '">'
            . esc_textarea($bottle['description']) . '</textarea></div>';
        echo '<div class="kc-sake-bottle-image-editor">';
        echo '<input type="hidden" name="' . esc_attr($prefix . '[image_id]')
            . '" value="' . esc_attr((string) $bottle['image_id']) . '" data-kc-bottle-image-id>';
        $imageAttributes = is_string($imageUrl) ? ' src="' . esc_url($imageUrl) . '"' : ' hidden';
        echo '<img alt="" data-kc-bottle-image-preview' . $imageAttributes . '>';
        echo '<button type="button" class="button" data-kc-bottle-image-pick>Choose Bottle Image</button> ';
        echo '<button type="button" class="button" data-kc-bottle-image-clear>Remove Image</button></div>';
        echo '<fieldset class="kc-sake-bottle-outlets"><legend>Locations for this bottle</legend>';

        if ($outlets === []) {
            echo '<p class="description">Publish an Outlet post to make it selectable here.</p>';
        }

        foreach ($outlets as $outlet) {
            if (! $outlet instanceof WP_Post) {
                continue;
            }

            $selected = in_array($outlet->ID, $bottle['outlet_ids'], true);
            echo '<label><input type="checkbox" name="' . esc_attr($prefix . '[outlet_ids][]')
                . '" value="' . esc_attr((string) $outlet->ID) . '" ' . checked($selected, true, false)
                . '> ' . esc_html(Outlets::locationName($outlet)) . '</label>';
        }

        echo '<p class="description">Select every outlet carrying this bottle. '
            . 'Leave all unchecked to hide the availability block.</p>';
        echo '</fieldset></div>';
    }

    /** @param array<string, mixed> $bottle */
    private static function renderFields(array $bottle, string $prefix, string $htmlId): void
    {
        $fields = [
            'name' => ['Bottle name', 'e.g. Brew X — X1'],
            'drink_type' => ['Type of drink', 'e.g. Junmai Ginjo, sparkling sake, shochu'],
            'size' => ['Bottle size (optional)', 'e.g. 720 ml'],
            'price' => ['Bottle price (MYR)', 'e.g. 280.00'],
            'discount' => ['Discount % (optional)', '0–100'],
        ];
        echo '<div class="kc-sake-bottle-fields">';

        foreach ($fields as $key => [$label, $placeholder]) {
            $type = $key === 'discount' ? 'number' : 'text';
            $attributes = $key === 'name' ? ' required' : '';
            $attributes .= $key === 'price' ? ' inputmode="decimal"' : '';
            $attributes .= $key === 'discount' ? ' min="0" max="100" step="0.01"' : '';
            echo '<p><label for="' . esc_attr($htmlId . $key) . '">' . esc_html($label) . '</label>';
            echo '<input class="widefat" type="' . esc_attr($type) . '" id="' . esc_attr($htmlId . $key)
                . '" name="' . esc_attr($prefix . '[' . $key . ']') . '" value="' . esc_attr($bottle[$key])
                . '" placeholder="' . esc_attr($placeholder) . '"' . $attributes . '></p>';
        }
    }

    public static function savePost(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== Sake::POST_TYPE || ! self::canSave($postId)) {
            return;
        }

        if (($_POST['kampachi_sake_bottles_complete'] ?? null) !== '1') {
            self::saveError(
                $postId,
                'The bottle form was incomplete. Reload the editor and try again; existing bottles were kept.'
            );
            return;
        }

        $raw = $_POST['kampachi_sake_bottles'] ?? [];
        $bottles = is_array($raw)
            ? self::sanitizeRows(wp_unslash($raw))
            : new WP_Error('kc_sake_bottles', 'The bottle list is invalid.');

        if (is_wp_error($bottles)) {
            self::saveError($postId, $bottles->get_error_message());
            return;
        }

        $result = update_post_meta($postId, self::META_KEY, wp_slash($bottles));

        if ($result === false && get_post_meta($postId, self::META_KEY, true) !== $bottles) {
            self::saveError($postId, 'The bottle list could not be saved. Please try again.');
            return;
        }

        delete_transient(self::noticeKey($postId));
    }

    private static function canSave(int $postId): bool
    {
        if (
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($postId)
            || wp_is_post_autosave($postId)
            || ! current_user_can('edit_post', $postId)
        ) {
            return false;
        }

        $nonce = $_POST['kampachi_sake_bottles_nonce'] ?? null;

        if (! is_string($nonce)) {
            return false;
        }

        $action = 'kampachi_save_sake_bottles_' . $postId;

        return wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), $action) !== false;
    }

    /** @return array<int, array<string, mixed>>|WP_Error */
    private static function sanitizeRows(array $rows): array|WP_Error
    {
        $clean = [];
        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            $bottle = self::sanitizeRow($row, $index + 1, true);

            if (is_wp_error($bottle)) {
                return $bottle;
            }

            if ($bottle['id'] === '' || isset($seen[$bottle['id']])) {
                $bottle['id'] = wp_generate_uuid4();
            }

            $seen[$bottle['id']] = true;
            $clean[] = $bottle;
        }

        return $clean;
    }

    /** @return array<string, mixed>|WP_Error */
    private static function sanitizeRow(mixed $row, int $number, bool $saving = false): array|WP_Error
    {
        if (! is_array($row)) {
            return new WP_Error('kc_sake_bottle', sprintf('Bottle %d is invalid.', $number));
        }

        $clean = self::textValues($row, $number);

        if (is_wp_error($clean)) {
            return $clean;
        }

        $pricing = self::priceValues($row, $number);

        if (is_wp_error($pricing)) {
            return $pricing;
        }

        $imageId = self::imageId($row['image_id'] ?? 0, $number, $saving);

        if (is_wp_error($imageId)) {
            return $imageId;
        }

        if (isset($row['outlet_ids']) && ! is_array($row['outlet_ids'])) {
            return new WP_Error('kc_sake_bottle_outlets', sprintf('Choose valid outlets for bottle %d.', $number));
        }

        $rawId = $row['id'] ?? '';
        $clean['id'] = is_string($rawId) && preg_match('/^[a-z0-9-]{1,64}$/D', $rawId) ? $rawId : '';
        $clean['visible'] = ! array_key_exists('visible', $row) || in_array($row['visible'], [true, 1, '1'], true);
        $clean['price'] = $pricing['price'];
        $clean['discount'] = $pricing['discount'];
        $clean['image_id'] = $imageId;
        $clean['outlet_ids'] = self::outletIds($row['outlet_ids'] ?? []);

        return $clean;
    }

    /** @return array<string, mixed>|WP_Error */
    private static function textValues(array $row, int $number): array|WP_Error
    {
        $clean = self::emptyRow();

        foreach (self::TEXT_FIELDS as $key) {
            $value = $row[$key] ?? '';

            if (! is_string($value)) {
                return new WP_Error('kc_sake_bottle', sprintf('Bottle %d has an invalid text field.', $number));
            }

            $clean[$key] = $key === 'description' ? wp_kses_post($value) : sanitize_text_field($value);
        }

        if (trim($clean['name']) === '') {
            return new WP_Error('kc_sake_bottle_name', sprintf('Enter a name for bottle %d.', $number));
        }

        return $clean;
    }

    /** @return array{price: string, discount: string}|WP_Error */
    private static function priceValues(array $row, int $number): array|WP_Error
    {
        $price = self::amount($row['price'] ?? '');
        $discount = self::discount($row['discount'] ?? '');

        if ($price === null || $discount === null || ($price === '' && (float) $discount > 0)) {
            $message = sprintf(
                'Bottle %d needs a valid non-negative price and a discount between 0 and 100. '
                    . 'A discount also needs a price.',
                $number
            );

            return new WP_Error('kc_sake_bottle_price', $message);
        }

        return ['price' => $price, 'discount' => $discount];
    }

    private static function imageId(mixed $value, int $number, bool $saving): int|WP_Error
    {
        if (in_array($value, [0, '0', ''], true)) {
            return 0;
        }

        $id = self::positiveId($value);
        $valid = $id > 0 && get_post_type($id) === 'attachment' && wp_attachment_is_image($id);

        if (! $valid && $saving) {
            return new WP_Error('kc_sake_bottle_image', sprintf('Choose a valid image for bottle %d.', $number));
        }

        // A deleted image must not hide its bottle on the website.
        return $valid ? $id : 0;
    }

    /** @return array<string, mixed> */
    private static function emptyRow(string $id = ''): array
    {
        return [
            'id' => $id,
            'visible' => false,
            'name' => '',
            'drink_type' => '',
            'size' => '',
            'description' => '',
            'price' => '',
            'discount' => '',
            'image_id' => 0,
            'outlet_ids' => [],
        ];
    }

    private static function amount(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^(?:RM|MYR)\s*/i', '', $value) ?? '';

        if (! preg_match('/^(?:\d{1,9}|\d{1,3}(?:,\d{3}){1,2})(?:\.\d{1,2})?$/D', $value)) {
            return null;
        }

        return number_format((float) str_replace(',', '', $value), 2, '.', '');
    }

    private static function discount(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $value) || (float) $value > 100) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function positiveId(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) {
            return 0;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? 0 : $id;
    }

    /** @return array<int, int> */
    public static function outletIds(mixed $values): array
    {
        return Outlets::locationIds($values);
    }

    /**
     * Include hidden rows when loading bottles for editing or translation sync.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(int $postId, bool $includeHidden = false): array
    {
        if (! metadata_exists('post', $postId, self::META_KEY)) {
            return self::legacyBottles($postId);
        }

        $stored = get_post_meta($postId, self::META_KEY, true);
        $bottles = [];

        foreach (is_array($stored) ? array_values($stored) : [] as $index => $row) {
            $bottle = self::sanitizeRow($row, $index + 1);

            if (! is_wp_error($bottle) && ($includeHidden || $bottle['visible'])) {
                $bottles[] = $bottle;
            }
        }

        return $bottles;
    }

    /** @return array<int, array<string, mixed>> */
    private static function legacyBottles(int $postId): array
    {
        $price = self::amount(get_post_meta($postId, '_kampachi_price', true));
        $outlets = self::outletIds(get_post_meta($postId, '_kampachi_sake_outlet_ids', true));

        if (($price === null || $price === '') && $outlets === []) {
            return [];
        }

        $images = Sake::valid_image_ids(get_post_meta($postId, '_kampachi_sake_image_ids', true));
        $bottle = self::emptyRow('legacy-' . $postId);
        $bottle['visible'] = true;
        $bottle['name'] = get_the_title($postId);
        $bottle['description'] = Sake::field($postId, 'description');
        $bottle['price'] = $price ?? '';
        $bottle['discount'] = $bottle['price'] !== ''
            ? (self::discount(get_post_meta($postId, '_kampachi_sake_discount', true)) ?? '')
            : '';
        $bottle['image_id'] = $images[0] ?? (int) get_post_thumbnail_id($postId);
        $bottle['outlet_ids'] = $outlets;

        return [$bottle];
    }

    /** @return array{original: float, discounted: float, percent: float}|null */
    public static function pricing(array $bottle): ?array
    {
        $price = self::amount($bottle['price'] ?? '');

        if ($price === null || $price === '') {
            return null;
        }

        $percent = (float) (self::discount($bottle['discount'] ?? '') ?? '0');
        $original = (float) $price;

        return [
            'original' => $original,
            'discounted' => round($original * (1 - $percent / 100), 2),
            'percent' => $percent,
        ];
    }

    /**
     * Stable IDs keep translated text attached to its bottle after reordering.
     * Price, image, outlet selection, visibility and collection membership follow English.
     */
    public static function syncTranslation(int $sourceId, int $targetId): void
    {
        if (! metadata_exists('post', $sourceId, self::META_KEY)) {
            return;
        }

        $translated = [];

        foreach (self::all($targetId, includeHidden: true) as $bottle) {
            if ($bottle['id'] !== '') {
                $translated[$bottle['id']] = $bottle;
            }
        }

        $merged = [];
        $textKeys = array_flip(self::TEXT_FIELDS);

        foreach (self::all($sourceId, includeHidden: true) as $bottle) {
            $existing = $translated[$bottle['id']] ?? [];
            $merged[] = array_merge($bottle, array_intersect_key($existing, $textKeys));
        }

        update_post_meta($targetId, self::META_KEY, wp_slash($merged));
    }

    private static function noticeKey(int $postId): string
    {
        return 'kc_sake_bottles_error_' . get_current_user_id() . '_' . $postId;
    }

    private static function saveError(int $postId, string $message): void
    {
        set_transient(self::noticeKey($postId), $message, 2 * MINUTE_IN_SECONDS);
    }

    public static function renderNotice(): void
    {
        $screen = get_current_screen();
        $rawId = $_GET['post'] ?? null;
        $postId = self::positiveId($rawId);

        if (! $screen || $screen->post_type !== Sake::POST_TYPE || $postId === 0) {
            return;
        }

        $message = get_transient(self::noticeKey($postId));

        if (is_string($message) && $message !== '') {
            delete_transient(self::noticeKey($postId));
            echo '<div class="notice notice-error"><p><strong>Bottles were not updated.</strong> '
                . esc_html($message) . '</p></div>';
        }
    }
}
