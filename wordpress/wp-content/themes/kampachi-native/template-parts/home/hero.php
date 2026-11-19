<?php

declare(strict_types=1);

use Kampachi\Native\Homepage_Content;

/** @var array $kampachiHome */

$videoId = absint($kampachiHome['hero_video_id'] ?? 0);
$videoUrl = $videoId > 0 ? wp_get_attachment_url($videoId) : false;
$mime = $videoId > 0 ? (string) get_post_mime_type($videoId) : '';
$video = is_string($videoUrl) && $videoUrl !== '' && str_starts_with($mime, 'video/')
    ? ['url' => $videoUrl, 'mime' => $mime]
    : null;

$images = [];
foreach ((array) ($kampachiHome['hero_slides'] ?? []) as $slide) {
    if (! is_array($slide)) {
        continue;
    }

    $url = wp_get_attachment_image_url(absint($slide['image_id'] ?? 0), 'full');
    if (is_string($url) && $url !== '') {
        $images[] = [
            'url' => $url,
            'direction' => sanitize_html_class((string) ($slide['direction'] ?? 'zoom-in')),
            'title' => (string) ($slide['title'] ?? ''),
            'format' => $slide['format']['title'] ?? [],
        ];
    }
}

$videoSlides = $video === null ? [] : [$video];
$slides = is_callable([Kampachi_Homepage::class, 'displayed_slides'])
    ? Kampachi_Homepage::displayed_slides($kampachiHome, $videoSlides, $images)
    : ($images !== [] ? $images : $videoSlides);
kampachi_native_render_hero($slides, '', 'Kampachi Japanese dining', 'home');
