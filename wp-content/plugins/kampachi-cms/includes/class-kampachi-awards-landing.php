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
    protected const POST_TYPE = Awards::POST_TYPE;
    protected const PAGE_SLUG = Awards::PAGE_SLUG;
    protected const PAGE_TITLE = Awards::PAGE_TITLE;
}
