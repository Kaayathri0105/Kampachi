<?php

declare(strict_types=1);

use Kampachi\CMS\Outlets;
use Kampachi\Native\Site_Language;

if (! defined('ABSPATH')) {
    exit;
}

$numbers = [
    'EQ Kuala Lumpur' => '60122789110',
    'Plaza33 Petaling Jaya' => '60126758860',
    'Pavilion Kuala Lumpur' => '60123740886',
];
$outletIds = Outlets::locationIds($args['outlet_ids'] ?? []);
if ($outletIds !== []) {
    $locations = array_map([Outlets::class, 'locationName'], $outletIds);
    $numbers = array_intersect_key($numbers, array_fill_keys($locations, true));
}

foreach ($args['existing_urls'] ?? [] as $existingUrl) {
    $parts = wp_parse_url($existingUrl);
    $host = strtolower($parts['host'] ?? '');
    if (in_array($host, ['wa.me', 'www.wa.me'], true)) {
        $phone = trim($parts['path'] ?? '', '/+');
    } elseif (in_array($host, ['api.whatsapp.com', 'web.whatsapp.com'], true)) {
        parse_str($parts['query'] ?? '', $query);
        $phone = is_string($query['phone'] ?? null) ? ltrim(trim($query['phone']), '+') : '';
    } else {
        continue;
    }
    $numbers = array_diff($numbers, [$phone]);
}

if ($numbers === []) {
    return;
}

$buttonClass = $args['button_class'] ?? 'kc-catering-detail-cta';
?>
<div class="kc-whatsapp-ctas">
    <?php foreach ($numbers as $location => $phone) : ?>
        <a class="<?php echo esc_attr($buttonClass); ?>" href="<?php echo esc_url('https://wa.me/' . $phone); ?>">
            <?php echo esc_html(sprintf(Site_Language::text('WhatsApp %s'), Site_Language::text($location))); ?>
        </a>
    <?php endforeach; ?>
</div>
