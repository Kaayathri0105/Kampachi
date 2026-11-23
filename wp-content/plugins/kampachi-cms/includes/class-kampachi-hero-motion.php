<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-image Ken Burns directions for CMS-managed hero galleries.
 * Kept per post rather than per attachment so the same image can move
 * differently on different pages.
 */
final class HeroMotion
{
    private const META_KEY = '_kampachi_hero_kenburn_directions';

    public const DIRECTIONS = [
        'zoom-in' => 'Zoom in',
        'zoom-out' => 'Zoom out',
        'left' => 'Pan left',
        'right' => 'Pan right',
        'up' => 'Pan up',
        'down' => 'Pan down',
        'none' => 'No animation',
    ];

    public static function boot(): void
    {
        add_action('save_post', [self::class, 'saveForPost'], 90);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdmin']);
    }

    public static function normalize(mixed $direction): string
    {
        return is_string($direction) && isset(self::DIRECTIONS[$direction])
            ? $direction
            : 'zoom-in';
    }

    public static function direction(int $postId, int $imageId): string
    {
        if ($postId <= 0 || $imageId <= 0) {
            return 'zoom-in';
        }

        $directions = get_post_meta($postId, self::META_KEY, true);

        return is_array($directions)
            ? self::normalize($directions[$imageId] ?? null)
            : 'zoom-in';
    }

    /**
     * @param string $sourceName POST name of the comma-delimited media ID field.
     */
    public static function editor(int $postId, string $sourceName): void
    {
        $saved = get_post_meta($postId, self::META_KEY, true);
        $saved = is_array($saved) ? $saved : [];
        $map = [];

        foreach ($saved as $imageId => $direction) {
            if (absint($imageId) > 0) {
                $map[(string) absint($imageId)] = self::normalize($direction);
            }
        }

        wp_nonce_field('kampachi_hero_motion_save', 'kampachi_hero_motion_nonce');
        echo '<div class="kc-hero-motion-editor" data-kc-motion-editor';
        echo ' data-source-name="' . esc_attr($sourceName) . '"';
        echo ' data-saved="' . esc_attr((string) wp_json_encode($map)) . '">';
        echo '<p><strong>Ken Burns direction — each hero image</strong></p>';
        echo '<p class="description">Select the movement for each image. Video slides are unchanged. "No animation" keeps the image still.</p>';
        echo '<div data-kc-motion-rows></div>';
        echo '</div>';
    }

    /**
     * Read and validate the posted direction map after WordPress nonce/capability checks.
     * Only image attachments selected in the current hero media field are retained.
     *
     * @param array<int, int> $mediaIds
     */
    public static function save(int $postId, array $mediaIds): void
    {
        if (! self::authorized($postId)) {
            return;
        }

        if (! isset($_POST['kampachi_hero_directions']) || ! is_array($_POST['kampachi_hero_directions'])) {
            return;
        }

        $raw = wp_unslash($_POST['kampachi_hero_directions']);
        $clean = [];

        foreach (array_unique(array_map('absint', $mediaIds)) as $imageId) {
            if ($imageId <= 0 || ! wp_attachment_is_image($imageId)) {
                continue;
            }

            $clean[$imageId] = self::normalize($raw[$imageId] ?? null);
        }

        update_post_meta($postId, self::META_KEY, $clean);
    }

    public static function saveForPost(int $postId): void
    {
        if (! self::authorized($postId)) {
            return;
        }

        $post = get_post($postId);
        if (! $post instanceof WP_Post) {
            return;
        }

        $name = match ($post->post_type) {
            'kc_offer' => 'kampachi_offer_hero_ids',
            'kc_outlet' => 'kampachi_outlet_hero_ids',
            'kc_event' => 'kampachi_catering_event_images',
            'kc_sake' => 'kampachi_sake_images',
            'page', \Kampachi_Landings::POST_TYPE => match ($post->post_name) {
                'promotion', 'about-us', 'careers', 'contact-us', 'privacy-policy', 'outlets' => 'kampachi_landing',
                'catering-events' => 'kampachi_catering_hero_media',
                'sake' => 'kampachi_sake_hero_media',
                default => '',
            },
            default => '',
        };

        if ($name === '') {
            return;
        }

        if ($name === 'kampachi_landing') {
            $landing = isset($_POST[$name]) && is_array($_POST[$name])
                ? wp_unslash($_POST[$name])
                : [];
            $value = $landing['hero_image_ids'] ?? '';
        } else {
            $value = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        }

        if (! is_string($value)) {
            return;
        }

        self::save($postId, array_map('absint', explode(',', sanitize_text_field($value))));
    }

    public static function saveDashboard(int $postId, string $fieldName): void
    {
        $value = isset($_POST[$fieldName]) ? wp_unslash($_POST[$fieldName]) : '';

        if (is_string($value)) {
            self::save($postId, array_map('absint', explode(',', sanitize_text_field($value))));
        }
    }

    public static function enqueueAdmin(): void
    {
        $screen = get_current_screen();
        if (! $screen instanceof \WP_Screen) {
            return;
        }

        // The script exits immediately on admin pages with no hero direction editor.
        if ($screen->base === 'post' || str_contains($screen->id, 'kampachi-')) {
            $file = plugin_dir_path(dirname(__FILE__)) . 'assets/hero-motion-admin.js';
            wp_enqueue_script(
                'kampachi-hero-motion-admin',
                plugins_url('../assets/hero-motion-admin.js', __FILE__),
                [],
                is_file($file) ? (string) filemtime($file) : '1.0',
                true
            );
        }
    }

    private static function authorized(int $postId): bool
    {
        if ($postId <= 0 || ! current_user_can('edit_post', $postId)
            || wp_is_post_revision($postId)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return false;
        }

        $nonce = $_POST['kampachi_hero_motion_nonce'] ?? null;

        return is_string($nonce)
            && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'kampachi_hero_motion_save') !== false;
    }
}
