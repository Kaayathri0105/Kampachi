<?php

declare(strict_types=1);

namespace Kampachi\CMS;

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
    protected const HIGHLIGHT_META = Press::HIGHLIGHT_META;
}
