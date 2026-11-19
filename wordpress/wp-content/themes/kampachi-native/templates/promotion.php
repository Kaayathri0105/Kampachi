<?php
/**
 * CMS-driven Offers & Events listing in the original Kampachi layout.
 *
 * @package KampachiNative
 */

declare(strict_types=1);

namespace Kampachi\Native;

use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

$promotionPage = get_queried_object();

if (! $promotionPage instanceof WP_Post || $promotionPage->post_type !== 'page') {
    $promotionPage = get_page_by_path('promotion', OBJECT, 'page');

    if (! $promotionPage instanceof WP_Post || $promotionPage->post_status !== 'publish') {
        $promotionPage = null;
    }
}

$promotionPageId = $promotionPage instanceof WP_Post ? $promotionPage->ID : 0;
$promotionTitle = $promotionPageId > 0 ? kampachi_native_meta($promotionPageId, 'intro_title') : '';
$promotionSubtitle = $promotionPageId > 0 ? kampachi_native_meta($promotionPageId, 'intro_subtitle') : '';
$promotionDescription = $promotionPageId > 0 ? kampachi_native_meta($promotionPageId, 'intro_description') : '';

if (trim($promotionTitle) === '') {
    $promotionTitle = $promotionPageId > 0 ? get_the_title($promotionPageId) : post_type_archive_title('', false);
}

$promotionPosts = get_posts([
    'post_type' => 'kc_offer',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC'],
    'ignore_sticky_posts' => true,
    'no_found_rows' => true,
    'suppress_filters' => false,
]);
$promotionCards = [];
$promotionCategories = [];

foreach ($promotionPosts as $promotion) {
    if (! $promotion instanceof WP_Post) {
        continue;
    }

    $categoryKeys = [];
    $terms = get_the_terms($promotion->ID, 'kc_offer_category');

    foreach (is_array($terms) ? $terms : [] as $term) {
        if (! $term instanceof WP_Term) {
            continue;
        }

        $categoryKey = 'term-' . $term->term_id;
        $categoryKeys[] = $categoryKey;
        $promotionCategories[$categoryKey] = $term->name;
    }

    $promotionCards[] = [
        'title' => get_the_title($promotion),
        'description' => get_the_excerpt($promotion),
        'image' => kampachi_native_post_image($promotion->ID),
        'url' => get_permalink($promotion),
        'id' => $promotion->ID,
        'categories' => implode(' ', $categoryKeys),
    ];
}

asort($promotionCategories, SORT_NATURAL | SORT_FLAG_CASE);
?>

    
    <style>
        :root {
            color-scheme: dark;
            --ink: #100304;
            --paper: #f4f0eb;
            --header-height: 88px;
            --section-transition-height: clamp(120px, 20vh, 220px);
            --paper-fade-height: clamp(230px, 34vh, 360px);
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            scroll-behavior: smooth;
        }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            background: var(--ink);
            color: var(--paper);
            font-family: "Noto Serif JP", "Yu Mincho", "Hiragino Mincho ProN", "Noto Serif CJK JP", Georgia, serif;
            -webkit-font-smoothing: antialiased;
        }

        body.menu-open {
            overflow: hidden;
        }

        button,
        a {
            color: inherit;
            font: inherit;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #e9c9a4;
            outline-offset: 5px;
        }

        .site-header {
            position: fixed;
            z-index: 30;
            inset: 0 0 auto;
            height: var(--header-height);
            background: var(--ink);
        }

        .site-header::before {
            position: absolute;
            z-index: 1;
            inset: 0 0 auto;
            height: 8px;
            background: #342a36;
            content: "";
            pointer-events: none;
        }

        .header-inner {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            width: min(1100px, calc(100% - 48px));
            height: 100%;
            margin-inline: auto;
        }

        .brand {
            position: absolute;
            left: 50%;
            display: block;
            color: var(--paper);
            line-height: 0;
            text-decoration: none;
            transform: translateX(-50%);
        }

        .brand-logo {
            display: block;
            width: 188px;
            max-height: 58px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .reservation-link {
            display: inline-flex;
            min-width: 198px;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border: 1px solid rgba(244, 240, 235, 0.78);
            color: var(--paper);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.27em;
            line-height: 1;
            text-decoration: none;
            transition: background-color 180ms ease, color 180ms ease;
        }

        .reservation-link:hover {
            background: var(--paper);
            color: var(--ink);
        }

        .language-wrap {
            position: relative;
        }

        .language-button,
        .menu-button {
            display: grid;
            width: 30px;
            height: 36px;
            padding: 0;
            place-items: center;
            border: 0;
            background: transparent;
            cursor: pointer;
        }

        .language-button {
            width: 26px;
            height: 26px;
            overflow: hidden;
            border-radius: 50%;
            background: #bd1d2c;
            font-size: 16px;
            line-height: 1;
        }

        .language-popover {
            position: absolute;
            top: calc(100% + 15px);
            right: -8px;
            display: grid;
            min-width: 150px;
            gap: 2px;
            padding: 8px;
            border: 1px solid rgba(244, 240, 235, 0.18);
            background: #100b0c;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-5px);
            transition: opacity 160ms ease, transform 160ms ease;
        }

        .language-wrap.is-open .language-popover {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .language-option {
            padding: 9px 10px;
            border: 0;
            background: transparent;
            color: var(--paper);
            cursor: pointer;
            font-size: 12px;
            text-align: left;
        }

        .language-option:hover,
        .language-option[aria-current="true"] {
            background: rgba(255, 255, 255, 0.09);
        }

        .menu-button {
            position: relative;
            width: 28px;
        }

        .menu-lines,
        .menu-lines::before,
        .menu-lines::after {
            position: absolute;
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 1px;
            background: var(--paper);
            content: "";
            transition: transform 220ms var(--ease), top 220ms var(--ease), opacity 160ms ease;
        }

        .menu-lines::before {
            top: -6px;
        }

        .menu-lines::after {
            top: 6px;
        }

        .menu-button[aria-expanded="true"] .menu-lines {
            background: transparent;
        }

        .menu-button[aria-expanded="true"] .menu-lines::before {
            top: 0;
            transform: rotate(45deg);
        }

        .menu-button[aria-expanded="true"] .menu-lines::after {
            top: 0;
            transform: rotate(-45deg);
        }

        .menu-button[aria-expanded="true"] .menu-lines {
            background: var(--paper);
        }

        .menu-button[aria-expanded="true"] .menu-lines::before {
            top: -6px;
            transform: none;
        }

        .menu-button[aria-expanded="true"] .menu-lines::after {
            top: 6px;
            transform: none;
        }

        main {
            padding-top: var(--header-height);
        }

        .hero {
            position: relative;
            min-height: 480px;
            height: 75svh;
            overflow: hidden;
            background: #d7d4cd;
        }

        .hero-video {
            display: block;
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 50%;
            background: #201a18;
        }

        .hero::after {
            position: absolute;
            z-index: 1;
            right: 0;
            bottom: 0;
            left: 0;
            height: var(--section-transition-height);
            background: linear-gradient(
                to bottom,
                rgba(253, 252, 247, 0) 0%,
                rgba(253, 252, 247, 0.24) 42%,
                rgba(253, 252, 247, 0.82) 84%,
                #fdfcf7 100%
            );
            content: "";
            pointer-events: none;
        }

        .hero-message {
            position: absolute;
            z-index: 2;
            right: 12px;
            bottom: clamp(24px, 6vh, 62px);
            left: 12px;
            margin: 0;
            color: #2b2420;
            font-family: "Cormorant Garamond", "Noto Serif JP", serif;
            font-size: clamp(18px, 3vw, 44px);
            font-style: italic;
            font-weight: 300;
            letter-spacing: 0.04em;
            line-height: 1.3;
            text-align: center;
            text-shadow: 0 1px 8px rgba(255, 255, 255, 0.68);
            white-space: nowrap;
            pointer-events: none;
        }

        .hero-message__word {
            display: inline-block;
            opacity: 0;
            animation: hero-word-cycle 18s ease-in-out calc(var(--word-index) * 0.9s) forwards;
        }

        .hero-message__word:nth-child(1) { --word-index: 0; }
        .hero-message__word:nth-child(2) { --word-index: 1; }
        .hero-message__word:nth-child(3) { --word-index: 2; }
        .hero-message__word:nth-child(4) { --word-index: 3; }
        .hero-message__word:nth-child(5) { --word-index: 4; }

        @keyframes hero-word-cycle {
            0% { opacity: 0; }
            5% { opacity: 1; }
            80% { opacity: 1; }
            85% { opacity: 0; }
            100% { opacity: 0; }
        }

        .menu-overlay {
            position: fixed;
            z-index: 20;
            inset: var(--header-height) 0 0;
            display: grid;
            overflow-y: auto;
            place-items: start center;
            padding: 64px 20px 38px;
            background: var(--ink);
            opacity: 0;
            pointer-events: none;
            visibility: hidden;
            transition: opacity 260ms ease, visibility 260ms ease;
        }

        .menu-overlay.is-open {
            opacity: 1;
            pointer-events: auto;
            visibility: visible;
        }

        .primary-nav {
            width: 100%;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: clamp(30px, 5vh, 44px);
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-item {
            text-align: center;
        }

        .nav-item-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .nav-link {
            color: rgba(244, 240, 235, 0.93);
            font-size: clamp(20px, 1.8vw, 29px);
            font-weight: 300;
            letter-spacing: -0.035em;
            line-height: 1.12;
            text-decoration: none;
            transition: color 180ms ease, letter-spacing 180ms ease;
        }

        .nav-link:hover {
            color: #fff;
            letter-spacing: 0.02em;
        }

        .nav-dropdown-toggle {
            display: grid;
            width: 30px;
            height: 30px;
            padding: 0;
            place-items: center;
            border: 1px solid rgba(244, 240, 235, 0.35);
            border-radius: 50%;
            background: transparent;
            color: var(--paper);
            cursor: pointer;
        }

        .nav-dropdown-chevron {
            width: 7px;
            height: 7px;
            margin-top: -4px;
            border-right: 1px solid currentColor;
            border-bottom: 1px solid currentColor;
            transform: rotate(45deg);
            transition: transform 180ms ease, margin 180ms ease;
        }

        .nav-dropdown-toggle[aria-expanded="true"] .nav-dropdown-chevron {
            margin-top: 4px;
            transform: rotate(225deg);
        }

        .nav-sublist {
            display: grid;
            gap: 11px;
            margin: 15px 0 0;
            padding: 14px 0 0;
            border-top: 1px solid rgba(244, 240, 235, 0.2);
            list-style: none;
        }

        .nav-sublist[hidden] {
            display: none;
        }

        .nav-sublink {
            color: rgba(244, 240, 235, 0.72);
            font-size: clamp(12px, 1.15vw, 16px);
            font-weight: 300;
            letter-spacing: 0.13em;
            line-height: 1.25;
            text-decoration: none;
            transition: color 180ms ease, letter-spacing 180ms ease;
        }

        .nav-sublink:hover {
            color: #fff;
            letter-spacing: 0.17em;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 620px) {
            :root {
                --header-height: 76px;
            }

            .header-inner {
                width: calc(100% - 36px);
                justify-content: flex-end;
            }

            .brand {
                position: static;
                width: 145px;
                margin-right: auto;
                transform: none;
            }

            .brand-logo {
                width: 100%;
                max-height: 44px;
            }

            .header-actions {
                gap: 10px;
            }

            .reservation-link {
                min-width: 106px;
                min-height: 34px;
                padding-inline: 8px;
                font-size: 10px;
                letter-spacing: 0.18em;
            }

            .language-button {
                width: 24px;
                height: 24px;
                font-size: 14px;
            }

            .menu-button {
                width: 24px;
            }

            .menu-overlay {
                padding-block: 20px 28px;
            }

            .nav-list {
                gap: clamp(17px, 3.6vh, 28px);
            }

            .nav-link {
                font-size: clamp(21px, 5.5vw, 26px);
            }
        }

        @media (max-height: 650px) {
            .menu-overlay {
                place-items: start center;
            }

            .nav-list {
                gap: 18px;
            }

            .nav-link {
                font-size: 21px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }

            .hero-message__word {
                animation: none;
                opacity: 1;
            }
        }

        .paper-surface {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            background-color: #fbfaf7;
            color: #211f1d;
        }

        .paper-surface::before,
        .paper-surface::after {
            position: absolute;
            z-index: 0;
            display: block;
            content: "";
            pointer-events: none;
            background-repeat: no-repeat;
            background-size: contain;
            opacity: 0.31;
        }

        .paper-surface::before {
            top: -54px;
            left: -82px;
            width: min(39vw, 565px);
            height: 390px;
            background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2MDAgNDAwIj4KPHBhdGggZD0iTS0yNiAxN0MxNi04IDU0IDQgODQgMjRjMjQgMTYgMjggMzkgNTggNDggMzEgOSA1OS0zIDgwIDE0IDIyIDE4IDE0IDQwIDQyIDUxIDI5IDExIDY1IDEgODAgMjMgMTYgMjMtMyA0Mi0zMSA1MC0yNSA3LTUwLTQtNzMgOC0yOCAxNS0zNSA0My02OSA1Mi0zMyA5LTUxLTE2LTc5LTE3LTMzLTItNjUgMjgtOTEgMTItMjMtMTQtMi0zNy0yNC01NC0xOC0xMy00NCA0LTU5LTEzLTE4LTIxLTEtNDggMS03MkMtNzkgOTYtNjcgNDItMjYgMTdaIiBmaWxsPSIjYzljOWM2IiBvcGFjaXR5PSIuNTUiLz4KPHBhdGggZD0iTS0xOCAxOTljNDItMjYgNjEtMyA4MiAxOCAyMiAyMSA0NCAyMyA3MCAxNyAyOC03IDQyIDExIDM0IDMxLTEwIDI0LTM5IDI3LTU1IDQ1LTE4IDIwLTMwIDQ3LTU1IDQ2LTI5LTEtMjQtMzQtNDYtNTEtMjQtMTgtNjEtMTAtNzctMzQtMTktMzAgMTItNTUgNDctNzJaIiBmaWxsPSIjZDdkN2Q0IiBvcGFjaXR5PSIuNDYiLz4KPHBhdGggZD0iTTEyOS0yNGMyNiAyOSAxMyA1NiA0MCA3MiAxNiAxMCA0MiA3IDUzIDI1IDEzIDIyLTYgMzktMjkgNDQtMjkgNi01MS0xMy03Ny0yMC0yOS04LTYzLTUtNzUtMjktMTMtMjUgOS01NCAzNS03MyAxOC0xMyAzOS0yOSA1My0xOVoiIGZpbGw9IiNiZGJkYmIiIG9wYWNpdHk9Ii4zMSIvPgo8L3N2Zz4=");
            background-position: left top;
        }

        .paper-surface::after {
            right: -72px;
            bottom: -96px;
            width: min(34vw, 480px);
            height: 380px;
            background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2MDAgNDAwIj4KPHBhdGggZD0iTTQ2NiAxNWM0Ni0yMiA5MiA3IDExMiA0NCAxNiAyOS01IDUzLTE2IDc1LTEzIDI2LTMgNTMtMTggNzYtMTggMjgtNDUgMTktNjMgNDMtMTggMjUtMjIgNjEtNTAgNzItMjYgMTEtNDctOS02My0yNS0yNC0yMy00MC0yMi02OS0xOS0zNCAzLTY0LTctNzMtMzItOC0yMyAxMi00MSAzNS01NCAyNi0xNSA0My0yNSA0Ny01NCA0LTMwLTUtNTkgMTgtNzggMjAtMTcgNDUtNiA3MS0xNCAyNi04IDQxLTI1IDY5LTM0WiIgZmlsbD0iI2M4YzhjNSIgb3BhY2l0eT0iLjU0Ii8+CjxwYXRoIGQ9Ik0yNTEgMzA2YzMxLTE4IDYxLTggODMgMTAgMjAgMTcgMzkgMTcgNjIgMTMgMjctNCA0NSAxMSAzOSAzMi03IDI2LTM2IDMxLTU2IDQ3LTIxIDE2LTM5IDM0LTY1IDI3LTIzLTctMjMtMzEtNDUtNDMtMjUtMTQtNTYtOC03My0yNy0yMC0yMyAyMC00NiA1NS01OVoiIGZpbGw9IiNkOWQ5ZDYiIG9wYWNpdHk9Ii41MyIvPgo8cGF0aCBkPSJNNTU3IDIyMWMyNi05IDU4IDEzIDYyIDQzIDQgMzEtMjIgNTMtNDMgNzItMjAgMTctNDIgNDQtNjggMzQtMjMtOS05LTM5LTIyLTYxLTEzLTIxLTQyLTM2LTM1LTYwIDctMjUgNDMtMjAgNjQtMjIgMTUtMSAyNiAwIDQyLTZaIiBmaWxsPSIjYmZiZmJkIiBvcGFjaXR5PSIuMzUiLz4KPC9zdmc+");
            background-position: right bottom;
        }

        .paper-world {
            background-image:
                linear-gradient(
                    to bottom,
                    #fdfcf7 0%,
                    rgba(253, 252, 247, 0.94) 20%,
                    rgba(253, 252, 247, 0.78) 43%,
                    rgba(253, 252, 247, 0.4) 72%,
                    rgba(253, 252, 247, 0) 100%
                ),
                url("data:image/jpeg;base64,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");
            background-position: center top;
            background-color: #fdfcf7;
            background-repeat: no-repeat;
            background-size: 100% var(--paper-fade-height), cover;
        }

        .site-footer {
            overflow: visible;
            background-color: #fdfcf7;
            background-image: none;
        }

        .site-footer::before {
            position: absolute;
            z-index: 0;
            top: -130px;
            right: 0;
            bottom: 0;
            left: 0;
            display: block;
            width: auto;
            height: auto;
            content: "";
            pointer-events: none;
            background-image: url("data:image/jpeg;base64,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");
            background-position: center bottom;
            background-repeat: no-repeat;
            background-size: cover;
            opacity: 1;
            -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 130px);
            mask-image: linear-gradient(to bottom, transparent 0, #000 130px);
            -webkit-mask-repeat: no-repeat;
            mask-repeat: no-repeat;
        }

        @media (max-width: 560px) {
            .site-footer::before {
                top: -90px;
                background-position: right bottom;
                -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 90px);
                mask-image: linear-gradient(to bottom, transparent 0, #000 90px);
            }
        }

        .paper-world::before,
        .paper-world::after,
        .site-footer::after {
            display: none;
        }

        .flavours-section,
        .experiences-section,
        .footer-inner {
            position: relative;
            z-index: 1;
        }

        .content-container,
        .footer-inner {
            width: min(1365px, calc(100% - 64px));
            margin-inline: auto;
        }

        .flavours-section {
            padding: 88px 0 105px;
        }

        .flavours-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
            align-items: center;
            gap: 56px;
        }

        .flavours-copy {
            max-width: 625px;
        }

        .flavours-copy h1 {
            margin: 0 0 25px;
            font-size: clamp(31px, 2.3vw, 42px);
            font-weight: 400;
            letter-spacing: 0.035em;
            line-height: 1.16;
        }

        .flavours-copy p {
            max-width: 635px;
            margin: 0;
            font-size: 16px;
            letter-spacing: 0.025em;
            line-height: 1.62;
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 27px;
        }

        .content-button {
            display: inline-flex;
            min-height: 54px;
            align-items: center;
            justify-content: center;
            padding: 0 24px;
            border: 1px solid #171514;
            border-radius: 5px;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-decoration: none;
            transition: background-color 180ms ease, color 180ms ease;
        }

        .content-button--filled {
            background: #080605;
            color: #fbfaf7;
        }

        .content-button--filled:hover {
            background: #383330;
        }

        .content-button--outline {
            background: transparent;
            color: #171514;
        }

        .content-button--outline:hover {
            background: #171514;
            color: #fbfaf7;
        }

        .feature-image {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
        }

        .experiences-section {
            padding: 14px 0 96px;
        }

        .experiences-heading {
            margin: 0;
            font-size: clamp(29px, 2.1vw, 38px);
            font-weight: 400;
            letter-spacing: 0.035em;
            line-height: 1.2;
            text-align: center;
        }

        .experiences-subtitle {
            margin: 18px 0 39px;
            font-size: 16px;
            letter-spacing: 0.035em;
            text-align: center;
        }

        .experience-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .experience-card img {
            display: block;
            width: 100%;
            aspect-ratio: 1.78 / 1;
            object-fit: cover;
        }

        .experience-card h3 {
            margin: 13px 0 13px;
            font-size: clamp(22px, 1.65vw, 29px);
            font-weight: 400;
            letter-spacing: 0.035em;
            line-height: 1.18;
        }

        .experience-card p {
            max-width: 440px;
            margin: 0;
            font-size: 15px;
            letter-spacing: 0.025em;
            line-height: 1.6;
        }

        .view-all-wrap {
            display: flex;
            justify-content: center;
            margin-top: 39px;
        }

        .view-all-button {
            min-width: 117px;
            padding-inline: 18px;
        }

        .site-footer {
            padding: 18px 0 27px;
        }

        .footer-top {
            display: grid;
            min-height: 76px;
            grid-template-columns: 100px minmax(0, 1fr) 160px;
            align-items: center;
            gap: 24px;
        }

        .footer-brand {
            display: inline-block;
            width: 66px;
            line-height: 0;
        }

        .footer-brand img {
            display: block;
            width: 100%;
            max-height: 54px;
            object-fit: contain;
        }

        .footer-links,
        .social-links {
            display: flex;
            align-items: center;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .footer-links {
            justify-content: center;
            flex-wrap: wrap;
            gap: 14px 27px;
        }

        .footer-links a {
            color: inherit;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .social-links {
            justify-content: flex-end;
            gap: 17px;
            color: #454340;
        }

        .social-links svg {
            display: block;
            width: 17px;
            height: 17px;
            fill: currentColor;
        }

        .footer-rule {
            height: 1px;
            margin: 15px 0 30px;
            border: 0;
            background: rgba(33, 31, 29, 0.18);
        }

        .copyright {
            margin: 0;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-align: center;
        }

        @media (max-width: 900px) {
            .content-container,
            .footer-inner {
                width: min(100% - 48px, 700px);
            }

            .flavours-section {
                padding: 70px 0 76px;
            }

            .flavours-grid {
                grid-template-columns: 1fr;
                gap: 34px;
            }

            .flavours-copy {
                max-width: 700px;
            }

            .feature-image {
                max-height: 540px;
            }

            .experiences-section {
                padding: 15px 0 76px;
            }

            .experience-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 34px 22px;
            }

            .footer-top {
                grid-template-columns: 78px minmax(0, 1fr);
                gap: 20px;
            }

            .social-links {
                grid-column: 1 / -1;
                justify-content: center;
            }
        }

        @media (max-width: 560px) {
            .content-container,
            .footer-inner {
                width: calc(100% - 40px);
            }

            .paper-surface:not(.site-footer)::before {
                width: 77vw;
                height: 280px;
                opacity: 0.22;
            }

            .paper-surface:not(.site-footer)::after {
                width: 70vw;
                height: 290px;
                opacity: 0.22;
            }

            .flavours-section {
                padding: 58px 0 63px;
            }

            .flavours-grid {
                gap: 28px;
            }

            .flavours-copy h1 {
                margin-bottom: 19px;
                font-size: clamp(29px, 8vw, 36px);
            }

            .flavours-copy p,
            .experience-card p {
                font-size: 14px;
            }

            .button-row {
                gap: 12px;
                margin-top: 22px;
            }

            .content-button {
                min-height: 48px;
                padding-inline: 17px;
                font-size: 11px;
            }

            .experiences-section {
                padding: 12px 0 59px;
            }

            .experiences-heading {
                font-size: 29px;
            }

            .experiences-subtitle {
                margin: 13px 0 27px;
                font-size: 14px;
            }

            .experience-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .experience-card h3 {
                margin: 10px 0 8px;
                font-size: 24px;
            }

            .view-all-wrap {
                margin-top: 28px;
            }

            .site-footer {
                padding: 20px 0 24px;
            }

            .footer-top {
                grid-template-columns: 1fr;
                justify-items: center;
                gap: 20px;
            }

            .footer-brand {
                width: 74px;
            }

            .footer-links {
                gap: 14px 20px;
            }

            .footer-links a {
                font-size: 11px;
            }

            .social-links {
                gap: 20px;
            }

            .footer-rule {
                margin: 22px 0 23px;
            }

            .copyright {
                font-size: 10px;
            }
        }

    

        @media (max-width: 680px) {
            .site-footer {
                padding: 28px 0 calc(24px + env(safe-area-inset-bottom, 0px));
            }

            .footer-inner {
                width: calc(100% - 40px);
                max-width: 700px;
            }

            .footer-top {
                display: flex;
                min-height: 0;
                flex-direction: column;
                align-items: center;
                gap: 22px;
            }

            .footer-brand {
                width: 76px;
            }

            .footer-top nav {
                width: 100%;
                min-width: 0;
            }

            .footer-links {
                width: 100%;
                justify-content: center;
                gap: 12px clamp(12px, 3.5vw, 22px);
            }

            .footer-links li {
                flex: 0 0 auto;
            }

            .footer-links a {
                display: block;
                font-size: clamp(11px, 2.6vw, 12px);
                line-height: 1.5;
                letter-spacing: 0.07em;
                white-space: nowrap;
            }

            .social-links {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 18px 24px;
            }

            .social-links svg {
                width: 19px;
                height: 19px;
            }

            .footer-rule {
                margin: 24px 0 18px;
            }

            .copyright {
                max-width: 100%;
                padding-inline: 8px;
                font-size: 11px;
                letter-spacing: 0.06em;
                line-height: 1.6;
                overflow-wrap: anywhere;
            }
        }

    

        .promotions-main {
            color: #211f1d;
        }

        .promotions-collection {
            padding: calc(var(--header-height) + clamp(38px, 6vh, 66px)) 0 82px;
        }

        .promotions-intro {
            max-width: 850px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }

        .promotions-title {
            margin: 0 0 25px;
            color: inherit;
            font-size: clamp(31px, 2.3vw, 42px);
            font-weight: 400;
            letter-spacing: 0.035em;
            line-height: 1.16;
        }

        .promotions-intro-copy {
            max-width: 635px;
            margin: 0 auto;
            color: inherit;
            font-size: 16px;
            letter-spacing: 0.025em;
            line-height: 1.62;
        }

        .promotion-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin: 36px 0 23px;
            border-bottom: 1px solid rgba(44, 39, 35, 0.16);
        }

        .promotion-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 7px 26px;
        }

        .promotion-filter {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 2px;
            border: 0;
            background: transparent;
            color: #615b56;
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            transition: color 180ms ease;
        }

        .promotion-filter::after {
            position: absolute;
            right: 0;
            bottom: -1px;
            left: 0;
            height: 2px;
            background: #872d32;
            content: "";
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 180ms ease;
        }

        .promotion-filter:hover,
        .promotion-filter.is-active {
            color: #872d32;
        }

        .promotion-filter.is-active::after {
            transform: scaleX(1);
        }

        .promotion-filter-count {
            color: #9a9189;
            font-size: 10px;
            letter-spacing: 0;
        }

        .promotion-result-count {
            flex: 0 0 auto;
            margin: 0 0 12px;
            color: #766e67;
            font-size: 12px;
            letter-spacing: 0.04em;
        }

        .promotion-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            gap: 26px;
        }

        .promotion-card {
            display: flex;
            min-width: 0;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(47, 40, 34, 0.14);
            background: rgba(255, 254, 251, 0.78);
            box-shadow: 0 16px 42px rgba(38, 29, 24, 0.07);
            transition: transform 220ms ease, box-shadow 220ms ease;
        }

        .promotion-card:hover {
            box-shadow: 0 22px 48px rgba(38, 29, 24, 0.12);
            transform: translateY(-5px);
        }

        .promotion-card[hidden] {
            display: none !important;
        }

        .promotion-pagination[hidden],
        .promotion-empty-state[hidden] {
            display: none;
        }

        .promotion-card-image {
            position: relative;
            overflow: hidden;
            background: #e8e2d9;
            aspect-ratio: 1.78 / 1;
        }

        .promotion-card-placeholder {
            display: grid;
            height: 100%;
            place-items: center;
            color: #766e67;
        }

        .promotion-card-image::after {
            position: absolute;
            inset: 42% 0 0;
            background: linear-gradient(to bottom, transparent, rgba(20, 17, 15, 0.38));
            content: "";
            pointer-events: none;
        }

        .promotion-card-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 450ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .promotion-card:hover .promotion-card-image img {
            transform: scale(1.035);
        }

        .promotion-category {
            position: absolute;
            z-index: 1;
            top: 15px;
            left: 15px;
            padding: 7px 10px;
            background: rgba(37, 29, 24, 0.82);
            color: #fffaf4;
            font-size: 9px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .promotion-card-number {
            position: absolute;
            z-index: 1;
            right: 16px;
            bottom: 13px;
            color: #fffaf4;
            font-family: "Cormorant Garamond", serif;
            font-size: 19px;
            letter-spacing: 0.12em;
        }

        .promotion-card-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            padding: 23px 23px 21px;
        }

        .promotion-card-kicker {
            margin: 0 0 8px;
            color: #872d32;
            font-size: 10px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .promotion-card h2 {
            margin: 0;
            color: #211f1d;
            font-family: "Cormorant Garamond", "Noto Serif JP", serif;
            font-size: clamp(26px, 2.2vw, 34px);
            font-weight: 500;
            letter-spacing: 0.015em;
            line-height: 1.08;
        }

        .promotion-card-description {
            margin: 13px 0 17px;
            color: #5a5550;
            font-size: 13px;
            letter-spacing: 0.015em;
            line-height: 1.75;
        }

        .promotion-details {
            display: grid;
            gap: 7px;
            margin: 0 0 20px;
            padding: 13px 14px;
            border-left: 2px solid #a34b4c;
            background: rgba(236, 230, 220, 0.55);
        }

        .promotion-details div {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 4px 10px;
        }

        .promotion-details dt,
        .promotion-details dd {
            margin: 0;
            font-size: 11px;
            line-height: 1.5;
        }

        .promotion-details dt {
            color: #766e67;
        }

        .promotion-details dd {
            color: #302925;
            font-weight: 500;
            text-align: right;
        }

        .promotion-contact-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            align-self: flex-start;
            margin-top: auto;
            transition: gap 180ms ease, border-color 180ms ease;
        }

        .promotion-contact-link:hover {
            gap: 14px;
        }

        .promotion-contact-link span {
            font-size: 16px;
            line-height: 1;
        }

        .promotion-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 34px;
        }

        .promotion-pagination[hidden] {
            display: none;
        }

        .promotion-page-list {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .promotion-page-button {
            min-width: 42px;
            min-height: 44px;
            padding-inline: 14px;
        }

        .promotion-page-button[aria-current="page"] {
            background: #080605;
            color: #fbfaf7;
        }

        .promotion-page-button:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .promotion-page-button:disabled:hover {
            background: transparent;
            color: #171514;
        }

        @media (max-width: 1000px) {
            .promotion-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .promotions-collection {
                padding-top: calc(var(--header-height) + 38px);
                padding-bottom: 56px;
            }

            .promotions-title {
                margin-bottom: 19px;
                font-size: clamp(29px, 8vw, 36px);
            }

            .promotions-intro-copy {
                font-size: 14px;
            }

            .promotion-toolbar {
                align-items: flex-end;
                flex-direction: column;
                gap: 5px;
                margin: 27px 0 18px;
                border: 0;
            }

            .promotion-filters {
                width: 100%;
                justify-content: center;
                gap: 4px 16px;
                border-bottom: 1px solid rgba(44, 39, 35, 0.16);
            }

            .promotion-filter {
                min-height: 42px;
                font-size: 10px;
            }

            .promotion-result-count {
                margin: 6px 0 0;
                font-size: 11px;
            }

            .promotion-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 22px;
            }

            .promotion-pagination {
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 27px;
            }

            .promotion-page-list {
                gap: 5px;
            }

            .promotion-page-button {
                min-width: 40px;
                padding-inline: 12px;
            }

            .promotion-card-body {
                padding: 20px 19px 19px;
            }

            .promotion-card h2 {
                font-size: 30px;
            }

        }

    
        .reservation-wrap {
            position: relative;
        }

        .reservation-trigger {
            appearance: none;
            background: transparent;
            cursor: pointer;
            white-space: nowrap;
        }

        .reservation-menu[hidden] {
            display: none;
        }

        .reservation-menu {
            position: absolute;
            z-index: 45;
            top: calc(100% + 14px);
            right: 0;
            display: grid;
            min-width: 210px;
            gap: 2px;
            padding: 8px;
            border: 1px solid rgba(244, 240, 235, 0.22);
            background: #100b0c;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.24);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-5px);
            transition: opacity 160ms ease, transform 160ms ease;
            visibility: hidden;
        }

        .reservation-wrap.is-open .reservation-menu {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
            visibility: visible;
        }

        .reservation-option {
            display: block;
            padding: 11px 12px;
            color: var(--paper);
            font-size: 12px;
            letter-spacing: 0.08em;
            line-height: 1.3;
            text-decoration: none;
            transition: background-color 160ms ease, color 160ms ease;
        }

        .reservation-option:hover {
            background: rgba(255, 255, 255, 0.09);
            color: #e9c9a4;
        }

        @media (max-width: 600px) {
            .reservation-menu {
                min-width: 172px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .reservation-menu,
            .reservation-option {
                transition: none;
            }
        }

    
        .promotion-heading-link {
            color: inherit;
            text-decoration: none;
        }

        .promotion-heading-link:hover {
            text-decoration: underline;
            text-underline-offset: 0.16em;
        }

    
        .promotion-card h2 { font-size: 22px !important; }
        .promotion-contact-link {
            display: inline-flex !important;
            align-items: center;
            gap: 9px;
            align-self: flex-start;
            margin-top: auto;
            padding: 0 0 7px !important;
            border: 0 !important;
            border-bottom: 1px solid #81776f !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            color: #211f1d !important;
            font-family: "Cormorant Garamond", "Noto Serif JP", serif !important;
            font-size: 15px !important;
            letter-spacing: 0.13em;
            line-height: 1.2 !important;
            text-decoration: none !important;
            text-transform: uppercase;
            transition: gap 180ms ease, color 180ms ease, border-color 180ms ease;
        }
        .promotion-contact-link:hover { gap: 14px; border-bottom-color: #6f2228 !important; color: #6f2228 !important; }
        .promotion-contact-link span { font-size: 19px !important; line-height: 1 !important; }

    </style>
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri() . '/assets/typography.css'); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri() . '/assets/header.css'); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri() . '/assets/site-theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri() . '/assets/components/hero-banners.css'); ?>">


    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/components/header.js'); ?>" data-site-root="./"></script>

    <main class="promotions-main" id="home">
<?php kampachi_render_editor_content(); ?>

        <div class="paper-surface paper-world">
            <?php
            $promotionPost = $promotionPage;
            $promotionSourceId = $promotionPost instanceof WP_Post
                ? absint(get_post_meta($promotionPost->ID, '_kc_translation_of', true))
                : 0;
            if ($promotionPost instanceof WP_Post && $promotionSourceId > 0
                && ! metadata_exists('post', $promotionPost->ID, '_kampachi_hero_image_ids')
                && ! metadata_exists('post', $promotionPost->ID, '_kampachi_hero_video_id')) {
                $sourcePost = get_post($promotionSourceId);
                if ($sourcePost instanceof WP_Post && $sourcePost->post_type === 'page') {
                    $promotionPost = $sourcePost;
                }
            }
            $promotionIds = $promotionPost instanceof WP_Post
                ? get_post_meta($promotionPost->ID, '_kampachi_hero_image_ids', true)
                : [];
            $promotionIds = is_array($promotionIds) ? array_values($promotionIds) : [];
            $promotionSlides = [];

            foreach ($promotionIds as $imageId) {
                $imageId = absint($imageId);
                $imageUrl = wp_get_attachment_image_url($imageId, 'full');

                if (! is_string($imageUrl) || $imageUrl === '') {
                    continue;
                }

                $promotionSlides[] = [
                    'url' => $imageUrl,
                    'direction' => class_exists(\Kampachi\CMS\HeroMotion::class)
                        ? \Kampachi\CMS\HeroMotion::direction($promotionPost->ID, $imageId)
                        : 'zoom-in',
                ];
            }
            $promotionVideoId = $promotionPost instanceof WP_Post
                ? absint(get_post_meta($promotionPost->ID, '_kampachi_hero_video_id', true))
                : 0;
            $promotionVideoMime = $promotionVideoId > 0 ? get_post_mime_type($promotionVideoId) : '';
            $promotionVideoUrl = is_string($promotionVideoMime) && str_starts_with($promotionVideoMime, 'video/')
                ? wp_get_attachment_url($promotionVideoId)
                : false;
            ?>
            <?php if ($promotionSlides !== []) : ?>
                <?php kampachi_native_render_hero($promotionSlides, '', 'Promotions hero'); ?>
            <?php elseif (is_string($promotionVideoUrl) && $promotionVideoUrl !== '') : ?>
                <?php kampachi_native_render_hero([], $promotionVideoUrl, 'Promotions hero'); ?>
            <?php elseif ($promotionPost instanceof WP_Post
                && ! metadata_exists('post', $promotionPost->ID, '_kampachi_hero_image_ids')
                && ! metadata_exists('post', $promotionPost->ID, '_kampachi_hero_video_id')) : ?>
                <section class="visual-hero visual-hero--promotion" aria-hidden="true">
                    <img class="visual-hero__image visual-hero__image--first" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/heroes/20230307_KRSB_BY_TML-6799 copy.jpg'); ?>" alt="" fetchpriority="high" decoding="async">
                    <img class="visual-hero__image visual-hero__image--second" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/heroes/20260622_KRSB_GRANDMENU_PHOTO_1752.jpg'); ?>" alt="" loading="eager" decoding="async">
                </section>
            <?php endif; ?>
            <section class="promotions-collection" aria-labelledby="promotions-title">
                <div class="content-container">
                    <header class="promotions-intro">
                        <?php if (trim($promotionSubtitle) !== '') : ?>
                            <p class="kc-kicker"><?php echo esc_html($promotionSubtitle); ?></p>
                        <?php endif; ?>
                        <h1 class="promotions-title" id="promotions-title"><?php echo esc_html($promotionTitle); ?></h1>
                        <?php if (trim($promotionDescription) !== '') : ?>
                            <div class="promotions-intro-copy"><?php echo wp_kses_post(wpautop($promotionDescription)); ?></div>
                        <?php endif; ?>
                    </header>

                    <div class="promotion-toolbar">
                        <div class="promotion-filters" role="group" aria-label="Filter promotions">
                            <button class="promotion-filter is-active" type="button" data-promotion-filter="all" aria-pressed="true">All offers</button>
                            <?php foreach ($promotionCategories as $categoryKey => $categoryName) : ?>
                                <button class="promotion-filter" type="button" data-promotion-filter="<?php echo esc_attr($categoryKey); ?>" aria-pressed="false"><?php echo esc_html($categoryName); ?></button>
                            <?php endforeach; ?>
                        </div>

                    </div>

                    <div class="promotion-grid">
                        <?php foreach ($promotionCards as $promotionCard) : ?>
                            <article class="promotion-card" data-promotion-category="<?php echo esc_attr($promotionCard['categories']); ?>" id="promotion-<?php echo esc_attr((string) $promotionCard['id']); ?>">
                                <div class="promotion-card-image">
                                    <?php if ($promotionCard['image'] !== '') : ?>
                                        <img src="<?php echo esc_url($promotionCard['image']); ?>" alt="<?php echo esc_attr($promotionCard['title']); ?>" loading="lazy" decoding="async">
                                    <?php else : ?>
                                        <span class="promotion-card-placeholder" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="promotion-card-body">
                                    <h2><?php echo esc_html($promotionCard['title']); ?></h2>
                                    <?php if ($promotionCard['description'] !== '') : ?>
                                        <p class="promotion-card-description"><?php echo esc_html($promotionCard['description']); ?></p>
                                    <?php endif; ?>
                                    <a class="content-button content-button--outline promotion-contact-link" href="<?php echo esc_url($promotionCard['url']); ?>" aria-label="<?php echo esc_attr('Discover ' . $promotionCard['title']); ?>"><?php echo esc_html(kampachi_native_label('discover')); ?> <span aria-hidden="true">↗</span></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="promotion-empty-state" role="status"<?php echo $promotionCards !== [] ? ' hidden' : ''; ?>>No offers are currently available.</p>

                    <nav class="promotion-pagination" aria-label="Promotion pages">
                        <button class="content-button content-button--outline promotion-page-button" type="button" data-page-previous aria-label="Previous page">Previous</button>
                        <div class="promotion-page-list" aria-label="Select a page"></div>
                        <button class="content-button content-button--outline promotion-page-button" type="button" data-page-next aria-label="Next page">Next <span aria-hidden="true">→</span></button>
                    </nav>
                </div>
            </section>
        </div>
    </main>

    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/components/footer.js'); ?>" data-site-root="./"></script>

    <script>
    (() => {
        'use strict';

        const heroVideo = document.querySelector('.hero-video');
        if (heroVideo) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                heroVideo.pause();
                heroVideo.removeAttribute('autoplay');
            } else {
                heroVideo.play().catch(() => {});
            }
        }

        const promotionFilters = document.querySelectorAll('[data-promotion-filter]');
        const promotionFilterCounts = document.querySelectorAll('[data-promotion-filter-count]');
        const promotionCards = Array.from(document.querySelectorAll('[data-promotion-category]'));
        const promotionGrid = document.querySelector('.promotion-grid');
        const promotionCount = document.querySelector('#promotion-count');
        const promotionEmptyState = document.querySelector('.promotion-empty-state');
        const promotionPagination = document.querySelector('.promotion-pagination');
        const promotionPageList = document.querySelector('.promotion-page-list');
        const previousPageButton = document.querySelector('[data-page-previous]');
        const nextPageButton = document.querySelector('[data-page-next]');
        const promotionPageSize = 6;
        let selectedPromotionCategory = 'all';
        let currentPromotionPage = 1;

        function getFilteredPromotions() {
            return promotionCards.filter((card) => (
                selectedPromotionCategory === 'all'
                || card.dataset.promotionCategory.split(/\s+/).includes(selectedPromotionCategory)
            ));
        }

        function scrollToPromotionGrid() {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            promotionGrid.scrollIntoView({
                behavior: reduceMotion ? 'auto' : 'smooth',
                block: 'start',
            });
        }

        function renderPromotionPages(totalPages) {
            promotionPageList.replaceChildren();
            promotionPagination.hidden = totalPages <= 1;
            previousPageButton.disabled = currentPromotionPage <= 1;
            nextPageButton.disabled = currentPromotionPage >= totalPages;

            for (let page = 1; page <= totalPages; page += 1) {
                const pageButton = document.createElement('button');
                pageButton.className = 'content-button content-button--outline promotion-page-button';
                pageButton.type = 'button';
                pageButton.textContent = String(page);
                pageButton.setAttribute('aria-label', `Go to page ${page}`);

                if (page === currentPromotionPage) {
                    pageButton.setAttribute('aria-current', 'page');
                }

                pageButton.addEventListener('click', () => {
                    currentPromotionPage = page;
                    renderPromotions();
                    scrollToPromotionGrid();
                });
                promotionPageList.append(pageButton);
            }
        }

        function renderPromotions() {
            const filteredPromotions = getFilteredPromotions();
            const totalPromotions = filteredPromotions.length;
            const totalPages = Math.ceil(totalPromotions / promotionPageSize);
            currentPromotionPage = Math.min(currentPromotionPage, Math.max(totalPages, 1));

            const firstIndex = (currentPromotionPage - 1) * promotionPageSize;
            const pagePromotions = filteredPromotions.slice(firstIndex, firstIndex + promotionPageSize);
            const visibleStart = totalPromotions === 0 ? 0 : firstIndex + 1;
            const visibleEnd = Math.min(firstIndex + promotionPageSize, totalPromotions);

            promotionCards.forEach((card) => {
                card.hidden = true;
            });
            pagePromotions.forEach((card) => {
                card.hidden = false;
            });

            if (promotionCount) {
                promotionCount.textContent = `Showing ${visibleStart}–${visibleEnd} of ${totalPromotions} ${totalPromotions === 1 ? 'offer' : 'offers'}`;
            }
            if (promotionEmptyState) {
                promotionEmptyState.hidden = totalPromotions !== 0;
            }
            renderPromotionPages(totalPages);
        }

        promotionFilterCounts.forEach((countElement) => {
            const category = countElement.dataset.promotionFilterCount;
            const count = category === 'all'
                ? promotionCards.length
                : promotionCards.filter((card) => card.dataset.promotionCategory.split(/\s+/).includes(category)).length;
            countElement.textContent = String(count).padStart(2, '0');
        });

        promotionFilters.forEach((filterButton) => {
            filterButton.addEventListener('click', () => {
                selectedPromotionCategory = filterButton.dataset.promotionFilter;
                currentPromotionPage = 1;

                promotionFilters.forEach((button) => {
                    const isSelected = button === filterButton;
                    button.classList.toggle('is-active', isSelected);
                    button.setAttribute('aria-pressed', String(isSelected));
                });

                renderPromotions();
                scrollToPromotionGrid();
            });
        });

        previousPageButton.addEventListener('click', () => {
            if (currentPromotionPage > 1) {
                currentPromotionPage -= 1;
                renderPromotions();
                scrollToPromotionGrid();
            }
        });

        nextPageButton.addEventListener('click', () => {
            const totalPages = Math.ceil(getFilteredPromotions().length / promotionPageSize);

            if (currentPromotionPage < totalPages) {
                currentPromotionPage += 1;
                renderPromotions();
                scrollToPromotionGrid();
            }
        });

        renderPromotions();
    
    })();
    </script>
    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/header-scroll.js'); ?>"></script>
