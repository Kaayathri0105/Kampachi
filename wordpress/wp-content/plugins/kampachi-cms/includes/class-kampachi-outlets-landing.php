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
    protected const PAGE_TITLE = 'Outlets';

    /** @return list<\WP_Post> */
    public static function posts(int $pageId, bool $includeHidden = false): array
    {
        return Outlets::filterLocations(parent::posts($pageId, $includeHidden));
    }
}
