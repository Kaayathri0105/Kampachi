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
    protected const POST_TYPE = Press::POST_TYPE;
    protected const PAGE_SLUG = Press::PAGE_SLUG;
    protected const PAGE_TITLE = 'Press & Media';
}
