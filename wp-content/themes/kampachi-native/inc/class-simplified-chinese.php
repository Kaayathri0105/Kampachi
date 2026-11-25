<?php

declare(strict_types=1);

namespace Kampachi\Native;

use Kampachi_Homepage;
use Kampachi_Landings;
use WP_HTML_Tag_Processor;
use WP_Post;

final class Simplified_Chinese
{
    /** @var array<int, WP_Post|null> */
    private static array $published = [];

    private const LABELS = [
        'home' => '首页',
        'kampachi home' => 'Kampachi 首页',
        'offers & events' => '优惠与活动',
        'offers and events' => '优惠与活动',
        'promotions' => '优惠活动',
        'sake' => '清酒',
        'sake collection' => '清酒精选',
        'artisanal sake' => '匠心清酒',
        'japanese sake collection' => '日本清酒精选',
        'catering & private dining' => '餐饮外烩与私人宴会',
        'catering & events' => '餐饮外烩与活动',
        'catering & private events' => '餐饮外烩与私人宴会',
        'catering and private dining events' => '餐饮外烩与私人宴会',
        'locations' => '分店',
        'location' => '分店',
        'outlets' => '分店',
        'restaurants' => '餐厅分店',
        'contact us' => '联系我们',
        'contact' => '联系方式',
        'address' => '地址',
        'explore' => '探索更多',
        'office' => '办公地址',
        'reservation' => '预订',
        'reservations' => '预订',
        'book now' => '立即预订',
        'reserve now' => '立即预订',
        'enquire now' => '立即咨询',
        'click to enquire' => '点击咨询',
        'about us' => '关于我们',
        'press & media' => '新闻与媒体',
        'awards & accolades' => '奖项与荣誉',
        'careers' => '人才招聘',
        'privacy & policy' => '隐私政策',
        'privacy policy' => '隐私政策',
        'all rights reserved.' => '保留所有权利。',
        'copyright' => '版权所有',
        'discover' => '了解详情',
        'discover %s' => '了解%s',
        'more' => '查看更多',
        'details' => '详情',
        'offer details' => '优惠详情',
        'signature dishes' => '招牌菜',
        'chef’s recommendations' => '主厨推荐',
        'dish' => '菜品',
        'menus' => '菜单',
        'menu' => '菜单',
        'view menu' => '查看菜单',
        'hours of operation' => '营业时间',
        'visit us' => '到店用餐',
        'open in google maps' => '在 Google 地图中打开',
        'date & time' => '日期与时间',
        'price' => '价格',
        'start date' => '开始日期',
        'end date' => '结束日期',
        'salary' => '薪资',
        'all sake' => '所有清酒',
        'all events' => '所有活动',
        'sake breweries and brands' => '清酒酒造与品牌',
        'all offers' => '所有优惠',
        'buffet' => '自助餐',
        'dining' => '用餐',
        'seasonal' => '时令精选',
        'gifting' => '礼品',
        'offer categories' => '优惠类别',
        'sort by' => '排序方式',
        'default order' => '默认排序',
        'newest to oldest' => '从新到旧',
        'oldest to newest' => '从旧到新',
        'a to z' => '字母升序',
        'z to a' => '字母降序',
        'no offers are currently available.' => '目前暂无优惠。',
        'previous' => '上一页',
        'next' => '下一页',
        'previous highlights' => '上一项精选',
        'next highlights' => '下一项精选',
        'offers navigation' => '优惠导航',
        'your name' => '您的姓名',
        'email address' => '电子邮箱',
        'website' => '网站',
        'contact us directly by email for enquiries.' => '如有疑问，请直接通过电子邮件联系我们。',
        'private dining' => '私人宴会',
        'press and media' => '新闻与媒体',
        'kampachi japanese restaurant' => 'Kampachi 日本餐厅',
        'view all offers' => '查看所有优惠',
        'year' => '年份',
        'all years' => '所有年份',
        'search by title or publication' => '按标题或出版物搜索',
        'search by title or awarding body' => '按标题或颁奖机构搜索',
        'no press articles found.' => '未找到相关新闻。',
        'no awards found.' => '未找到相关奖项。',
        'press archive' => '新闻档案',
        'awards archive' => '奖项档案',
        'choose language (%s)' => '选择语言（%s）',
        'main navigation' => '主导航',
        'sake bottles' => '清酒产品',
        '%s%% off' => '减免 %s%%',
        'unavailable' => '暂未提供',
        'eq kuala lumpur' => '吉隆坡 EQ',
        'equatorial penang' => '槟城赤道酒店',
        'plaza33 petaling jaya' => '八打灵再也 Plaza33',
        'pavilion kuala lumpur' => '吉隆坡柏威年广场',
        'back to sake breweries' => '返回清酒酒造',
        'back to catering & events' => '返回餐饮外烩与活动',
        'back to offers & events' => '返回优惠与活动',
        'back to careers' => '返回人才招聘',
        'back to outlets' => '返回分店',
        'back to 47in27' => '返回 47in27',
        'back to press & media' => '返回新闻与媒体',
        'read the original article' => '阅读原文',
        'back to awards & accolades' => '返回奖项与荣誉',
        'view award source' => '查看奖项来源',
        'back to home' => '返回首页',
        'send an enquiry' => '发送咨询',
        'send enquiry' => '发送咨询',
        'send' => '发送',
        'name' => '姓名',
        'first name' => '名',
        'last name' => '姓',
        'email' => '电子邮箱',
        'phone' => '电话',
        'telephone (optional)' => '电话（选填）',
        'subject' => '主题',
        'message' => '留言',
        'apply online' => '在线申请',
        'apply by email' => '通过电子邮件申请',
        'apply via whatsapp' => '通过 WhatsApp 申请',
        'thank you. your enquiry has been sent.' => '感谢您的咨询。您的留言已发送。',
        'too many submissions. please try again later.' => '提交次数过多，请稍后再试。',
        'please complete all required fields and try again.' => '请填写所有必填项后重试。',
        'we could not send your enquiry. please contact us directly by email.' => '咨询未能发送，请直接通过电子邮件联系我们。',
        'online enquiries are temporarily unavailable. please contact us directly.' => '在线咨询暂不可用，请直接联系我们。',
    ];

    public static function active(): bool
    {
        return ! is_admin() && did_action('wp') > 0
            && get_post_meta(get_queried_object_id(), '_kc_language', true) === 'zh';
    }

    public static function text(string $value): string
    {
        if ($value === '' || ! self::active()) {
            return $value;
        }

        $key = strtolower(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return self::LABELS[$key] ?? $value;
    }

    public static function gettext(string $translation, string $original, string $domain): string
    {
        if (! in_array($domain, ['kampachi-native', 'kampachi-cms'], true)) {
            return $translation;
        }

        $localized = self::text($original);

        return $localized === $original ? $translation : $localized;
    }

    /** @param list<WP_Post> $posts @return list<WP_Post> */
    public static function posts(array $posts): array
    {
        if (! self::active() || $posts === []) {
            return $posts;
        }

        $sources = [];
        $localized = [];
        foreach ($posts as $post) {
            if (get_post_meta($post->ID, '_kc_language', true) === 'zh') {
                if ($post->post_status === 'publish') {
                    $localized[$post->ID] = $post;
                }
                continue;
            }
            $sourceId = absint(get_post_meta($post->ID, '_kc_translation_of', true)) ?: $post->ID;
            $sources[$post->ID] = $sourceId;
        }

        $missing = array_diff(array_unique($sources), array_keys(self::$published));
        if ($missing !== []) {
            foreach ($missing as $id) {
                self::$published[$id] = null;
            }
            $translations = get_posts([
                'post_type' => array_values(array_unique(array_column($posts, 'post_type'))),
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'ID',
                'order' => 'DESC',
                'kc_include_translations' => true,
                'meta_query' => [
                    ['key' => '_kc_translation_of', 'value' => array_values($missing), 'compare' => 'IN'],
                    ['key' => '_kc_language', 'value' => 'zh'],
                ],
            ]);
            foreach ($translations as $translation) {
                $sourceId = absint(get_post_meta($translation->ID, '_kc_translation_of', true));
                self::$published[$sourceId] ??= $translation;
            }
        }

        $ordered = [];
        foreach ($posts as $post) {
            $translation = $localized[$post->ID] ?? self::$published[$sources[$post->ID] ?? 0] ?? null;
            if ($translation !== null) {
                $ordered[$translation->ID] = $translation;
            }
        }

        return array_values($ordered);
    }

    public static function url(string $url): string
    {
        if ($url === '' || ! self::active() || str_starts_with($url, '#')) {
            return $url;
        }

        $home = wp_parse_url(home_url('/'));
        $parts = wp_parse_url($url);
        if (! is_array($parts) || (isset($parts['scheme']) && ! in_array($parts['scheme'], ['https', 'http'], true))
            || (isset($parts['host']) && strtolower($parts['host']) !== strtolower($home['host'] ?? ''))) {
            return $url;
        }

        $path = trim($parts['path'] ?? '/', '/');
        $basePath = trim($home['path'] ?? '', '/');
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = ltrim(substr($path, strlen($basePath)), '/');
        }
        $postId = url_to_postid(home_url('/' . $path . (isset($parts['query']) ? '?' . $parts['query'] : '')));
        if ($postId > 0) {
            $post = get_post($postId);
        } elseif ($path === '') {
            $post = class_exists(Kampachi_Homepage::class) ? Kampachi_Homepage::master_page() : null;
        } else {
            $post = get_page_by_path($path, OBJECT, ['page', 'kc_landing']);
        }
        if (! $post instanceof WP_Post) {
            return $url;
        }

        $target = self::posts([$post])[0] ?? null;
        $permalink = $target !== null ? get_permalink($target) : '';
        if (! is_string($permalink) || $permalink === '') {
            return '';
        }
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $permalink = add_query_arg(array_diff_key($query, array_flip(['p', 'page_id', 'post_type'])), $permalink);
        }

        return $permalink . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    public static function links(array $links): array
    {
        if (! self::active()) {
            return $links;
        }

        foreach ($links as $key => $link) {
            $url = self::url($link['url']);
            if ($url === '') {
                unset($links[$key]);
                continue;
            }
            $links[$key]['url'] = $url;
            $links[$key]['title'] = self::text($link['title']);
        }

        return $links;
    }

    public static function menu_items(array $items): array
    {
        if (! self::active()) {
            return $items;
        }

        foreach ($items as $item) {
            $item->title = self::text((string) $item->title);
            $item->url = self::url((string) $item->url);
        }

        return $items;
    }

    public static function rich_links(string $html): string
    {
        if (! self::active() || $html === '' || ! class_exists(WP_HTML_Tag_Processor::class)) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);
        while ($processor->next_tag('a')) {
            $url = $processor->get_attribute('href');
            if (! is_string($url)) {
                continue;
            }
            $localized = self::url($url);
            if ($localized === '') {
                $processor->remove_attribute('href');
                $processor->set_attribute('aria-disabled', 'true');
            } elseif ($localized !== $url) {
                $processor->set_attribute('href', $localized);
            }
        }

        return $processor->get_updated_html();
    }
}
