<?php

declare(strict_types=1);

namespace Kampachi\CMS;

if (! defined('ABSPATH')) {
    exit;
}

final class AwardsLanding extends CollectionLanding
{
    protected const KEY = 'awards';
    protected const TITLE = 'Awards & Accolades Landing';
    protected const ITEMS_LABEL = 'awards';
    protected const ITEMS_TITLE = 'Highlights & Award Order';
    protected const ITEMS_HELP = 'Tick Highlight and keep Show enabled to place an award in the carousel. '
        . 'Drag awards to set the carousel order.';
    protected const HIGHLIGHT_META = '_kampachi_award_highlight';
    protected const POST_TYPE = Awards::POST_TYPE;
    protected const PAGE_SLUG = Awards::PAGE_SLUG;
    protected const PAGE_TITLE = Awards::PAGE_TITLE;
}
