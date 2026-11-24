<?php

declare(strict_types=1);

namespace Kampachi\CMS;

if (! defined('ABSPATH')) {
    exit;
}

final class CareersLanding extends CollectionLanding
{
    protected const KEY = 'careers';
    protected const TITLE = 'Careers Landing';
    protected const ITEMS_LABEL = 'vacancies';
    protected const ITEMS_TITLE = 'Vacancy Order';
    protected const POST_TYPE = 'kc_job';
    protected const PAGE_SLUG = 'careers';
    protected const PAGE_TITLE = 'Careers';
    protected const HAS_BODY_EDITOR = true;

    protected static function adminUrl(): string
    {
        return add_query_arg(
            ['post_type' => self::POST_TYPE, 'page' => 'kampachi-careers-landing'],
            admin_url('edit.php')
        );
    }
}
