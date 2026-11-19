<?php

declare(strict_types=1);

namespace Kampachi\CMS;

if (! defined('ABSPATH')) {
    exit;
}

final class OffersLanding extends CollectionLanding
{
    protected const KEY = 'offers';
    protected const TITLE = 'Offers Landing';
    protected const POST_TYPE = 'kc_offer';
    protected const PAGE_SLUG = 'promotion';
    protected const TAXONOMY = 'kc_offer_category';
    protected const HAS_ARCHIVE = true;
}
