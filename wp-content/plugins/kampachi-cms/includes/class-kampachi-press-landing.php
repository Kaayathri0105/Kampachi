<?php

declare(strict_types=1);

namespace Kampachi\CMS;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class PressLanding extends CollectionLanding
{
    protected const KEY = 'press';
    protected const TITLE = 'Press & Media Landing';
    protected const ITEMS_LABEL = 'press articles';
    protected const ITEMS_TITLE = 'Highlights & Article Order';
    protected const ITEMS_HELP = 'Tick Highlight and keep Show enabled to place an article in the carousel. '
        . 'Drag articles to set the carousel order.';
    protected const POST_TYPE = Press::POST_TYPE;
    protected const PAGE_SLUG = Press::PAGE_SLUG;
    protected const PAGE_TITLE = 'Press & Media';

    protected static function extraItemFields(): array
    {
        return ['highlight_changes' => []];
    }

    protected static function renderItemControls(WP_Post $post): void
    {
        echo '<label><input type="checkbox" data-kc-collection-highlight'
            . checked(Press::isHighlight($post->ID), true, false)
            . disabled(! current_user_can('edit_post', $post->ID), true, false)
            . ' aria-label="' . esc_attr('Highlight ' . get_the_title($post)) . '"> Highlight</label>';
    }

    protected static function saveItemControls(array $raw, array $eligible): void
    {
        $changes = $raw['highlight_changes'] ?? null;
        if (! is_string($changes) || $changes === '') {
            return;
        }

        $allowed = array_fill_keys($eligible, true);
        foreach (explode(',', $changes) as $change) {
            if (! preg_match('/^([1-9]\d*):([01])$/D', $change, $match)) {
                continue;
            }

            $postId = (int) $match[1];
            if (isset($allowed[$postId]) && current_user_can('edit_post', $postId)) {
                Press::setHighlight($postId, $match[2] === '1');
            }
        }
    }
}
