<?php

declare(strict_types=1);

namespace Kampachi\CMS;

if (! defined('ABSPATH')) {
    exit;
}

final class OutletsLanding extends CollectionLanding
{
    protected const KEY = 'outlets';
    protected const TITLE = 'Outlets Landing';
    protected const POST_TYPE = 'kc_outlet';
    protected const PAGE_SLUG = 'outlets';

    private const CREATE_ACTION = 'kampachi_create_outlets_landing';

    public static function boot(): void
    {
        parent::boot();
        add_action('admin_post_' . self::CREATE_ACTION, [self::class, 'create']);
    }

    protected static function renderMissingPage(): void
    {
        if (! self::canCreate()) {
            echo '<p>Ask a user who can publish pages to set up the Outlets landing page.</p>';

            return;
        }

        echo '<section class="kc-panel"><h2>Set up the landing page</h2>';
        echo '<p>Create and publish the Outlets landing page with the current published outlets.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CREATE_ACTION) . '">';
        wp_nonce_field(self::CREATE_ACTION);
        submit_button('Create Outlets Landing Page');
        echo '</form></section>';
    }

    private static function canCreate(): bool
    {
        return current_user_can('edit_pages') && current_user_can('publish_pages');
    }

    public static function create(): void
    {
        if (! self::canCreate()) {
            wp_die(
                esc_html__('You do not have permission to publish this landing page.', 'kampachi-cms'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(self::CREATE_ACTION);
        if (self::page() === null) {
            $result = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_name' => self::PAGE_SLUG,
                'post_title' => 'Outlets',
            ], true);
            if (is_wp_error($result)) {
                wp_die(esc_html($result->get_error_message()));
            }
        }

        wp_safe_redirect(self::adminUrl());
        exit;
    }
}
