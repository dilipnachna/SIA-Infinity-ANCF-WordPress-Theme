<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/inc/class-sia-publisher-intelligence.php';

function sia_ancf_news_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 120, 'width' => 480, 'flex-height' => true, 'flex-width' => true]);
    register_nav_menus(['primary' => __('Primary Menu', 'sia-ancf-news')]);
}
add_action('after_setup_theme', 'sia_ancf_news_setup');

function sia_ancf_news_assets(): void {
    wp_enqueue_style('sia-ancf-news', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'sia_ancf_news_assets');

SIA_Publisher_Intelligence::boot();
