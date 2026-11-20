<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;
use WP_Screen;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Apply the Homepage editor's visual system to Kampachi CMS admin screens.
 *
 * Presentation-only: this class does not change forms, metadata, capabilities,
 * frontend templates, or WordPress's native content editor.
 */
final class AdminStyle
{
    private const VERSION = '2.8.2';

    private const POST_TYPES = [
        'kc_event',
        'kc_offer',
        'kc_sake',
        'kc_outlet',
        'kc_job',
        'kc_prefecture',
        Press::POST_TYPE,
        Awards::POST_TYPE,
    ];

    private const LANDING_SLUGS = [
        'home',
        'promotion',
        'sake',
        '47in27',
        'catering-events',
        'careers',
        'about-us',
        'privacy-policy',
        'contact-us',
        Press::PAGE_SLUG,
        Awards::PAGE_SLUG,
    ];

    public static function boot(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 99);
    }

    public static function enqueue(string $hook): void
    {
        $screen = get_current_screen();

        if (! $screen instanceof WP_Screen) {
            return;
        }

        // The Homepage already enqueues the original design-system stylesheet.
        if ($hook === 'toplevel_page_kampachi-homepage-module') {
            return;
        }

        $pluginScreen = str_contains($screen->id, 'kampachi-');
        $postScreen = in_array($hook, ['post.php', 'post-new.php'], true)
            && self::is_kampachi_editor((string) $screen->post_type);

        if (! $pluginScreen && ! $postScreen) {
            return;
        }

        wp_enqueue_style(
            'kampachi-admin-shared',
            plugins_url('../assets/admin.css', __FILE__),
            [],
            self::VERSION
        );

        wp_enqueue_style(
            'kampachi-admin-consistency',
            plugins_url('../assets/admin-consistency.css', __FILE__),
            ['kampachi-admin-shared'],
            self::VERSION
        );
    }

    private static function is_kampachi_editor(string $postType): bool
    {
        if (in_array($postType, self::POST_TYPES, true)) {
            return true;
        }

        if ($postType !== 'page' || ! isset($_GET['post']) || ! is_scalar($_GET['post'])) {
            return false;
        }

        $id = absint($_GET['post']);

        if ($id === 0) {
            return false;
        }

        $post = get_post($id);

        return $post instanceof WP_Post
            && $post->post_type === 'page'
            && in_array($post->post_name, self::LANDING_SLUGS, true);
    }
}
