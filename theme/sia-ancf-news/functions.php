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
    add_theme_support('editor-styles');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 160, 'width' => 640, 'flex-height' => true, 'flex-width' => true]);

    add_image_size('sia-news-hero', 1200, 675, true);
    add_image_size('sia-news-card', 720, 405, true);
    add_image_size('sia-news-thumb', 360, 203, true);

    register_nav_menus([
        'primary' => __('Primary Menu', 'sia-ancf-news'),
        'utility' => __('Utility Menu', 'sia-ancf-news'),
        'footer'  => __('Footer Menu', 'sia-ancf-news'),
    ]);
}
add_action('after_setup_theme', 'sia_ancf_news_setup');

function sia_ancf_news_assets(): void {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('sia-ancf-news', get_stylesheet_uri(), [], $version);

    $navigation = get_template_directory() . '/assets/navigation.js';
    if (is_file($navigation)) {
        wp_enqueue_script(
            'sia-ancf-news-navigation',
            get_template_directory_uri() . '/assets/navigation.js',
            [],
            $version,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'sia_ancf_news_assets');

function sia_ancf_news_fallback_menu(): void {
    $categories = sia_ancf_news_section_categories(6);

    echo '<ul class="primary-menu">';
    echo '<li><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Home', 'sia-ancf-news') . '</a></li>';
    foreach ($categories as $category) {
        echo '<li><a href="' . esc_url(get_category_link($category)) . '">' . esc_html($category->name) . '</a></li>';
    }
    echo '</ul>';
}

function sia_ancf_news_home_excluded_category_ids(): array {
    $ids = get_option('sia_ancf_home_excluded_categories', []);
    if (!is_array($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('absint', $ids))));
}

function sia_ancf_news_section_categories(int $limit = 4): array {
    $excluded = sia_ancf_news_home_excluded_category_ids();
    $categories = get_categories([
        'taxonomy'   => 'category',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => max(12, $limit * 3),
        'exclude'    => $excluded,
    ]);

    $categories = array_values(array_filter($categories, static function ($category): bool {
        return isset($category->slug) && !in_array($category->slug, ['uncategorized'], true);
    }));

    return array_slice($categories, 0, $limit);
}

function sia_ancf_news_post_category(int $post_id): ?WP_Term {
    $categories = get_the_category($post_id);
    foreach ($categories as $category) {
        if ($category->slug !== 'uncategorized') {
            return $category;
        }
    }
    return $categories[0] ?? null;
}

function sia_ancf_news_post_meta(int $post_id, bool $show_author = true): string {
    $parts = [];
    $parts[] = esc_html(get_the_date('', $post_id));

    if ($show_author) {
        $author_id = (int) get_post_field('post_author', $post_id);
        $author = get_the_author_meta('display_name', $author_id);
        if ($author !== '') {
            $parts[] = esc_html($author);
        }
    }

    return implode('<span aria-hidden="true"> · </span>', $parts);
}

function sia_ancf_news_thumbnail(int $post_id, string $size = 'sia-news-card', string $class = ''): void {
    $permalink = get_permalink($post_id);
    $label = sia_ancf_news_post_category($post_id);

    echo '<a class="sia-media ' . esc_attr($class) . '" href="' . esc_url($permalink) . '" aria-label="' . esc_attr(get_the_title($post_id)) . '">';
    if (has_post_thumbnail($post_id)) {
        echo get_the_post_thumbnail($post_id, $size, [
            'loading' => $size === 'sia-news-hero' ? 'eager' : 'lazy',
            'fetchpriority' => $size === 'sia-news-hero' ? 'high' : 'auto',
            'decoding' => 'async',
        ]);
    } else {
        echo '<span class="sia-media-placeholder"><span>' . esc_html($label ? $label->name : get_bloginfo('name')) . '</span></span>';
    }
    echo '</a>';
}

function sia_ancf_news_render_card(int $post_id, string $variant = 'standard'): void {
    $category = sia_ancf_news_post_category($post_id);
    $permalink = get_permalink($post_id);
    $classes = 'sia-story-card sia-story-card--' . sanitize_html_class($variant);

    echo '<article class="' . esc_attr($classes) . '">';
    sia_ancf_news_thumbnail($post_id, $variant === 'compact' ? 'sia-news-thumb' : 'sia-news-card');
    echo '<div class="sia-story-card__body">';
    if ($category) {
        echo '<a class="sia-eyebrow" href="' . esc_url(get_category_link($category)) . '">' . esc_html($category->name) . '</a>';
    }
    echo '<h3 class="sia-story-card__title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a></h3>';
    echo '<div class="sia-meta">' . wp_kses_post(sia_ancf_news_post_meta($post_id, false)) . '</div>';
    if ($variant !== 'compact') {
        $excerpt = trim((string) get_the_excerpt($post_id));
        if ($excerpt !== '') {
            echo '<p class="sia-story-card__excerpt">' . esc_html(wp_trim_words($excerpt, 24)) . '</p>';
        }
    }
    echo '</div></article>';
}

function sia_ancf_news_query_ids(array $args): array {
    $query = new WP_Query(array_merge([
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'ignore_sticky_posts' => false,
        'no_found_rows'       => true,
        'fields'              => 'ids',
    ], $args));

    return array_map('intval', $query->posts);
}

function sia_ancf_news_home_meta_query(?string $placement = null): array {
    $meta_query = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            [
                'key' => '_sia_home_eligible',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_sia_home_eligible',
                'value' => '1',
                'compare' => '=',
            ],
        ],
        [
            'relation' => 'OR',
            [
                'key' => '_sia_home_exclude',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_sia_home_exclude',
                'value' => '1',
                'compare' => '!=',
            ],
        ],
    ];

    if ($placement !== null) {
        $meta_query[] = [
            'key' => '_sia_home_placement',
            'value' => sanitize_key($placement),
            'compare' => '=',
        ];
    }

    return $meta_query;
}

function sia_ancf_news_home_query_ids(array $args = [], ?string $placement = null): array {
    $defaults = [
        'posts_per_page' => 12,
        'category__not_in' => sia_ancf_news_home_excluded_category_ids(),
        'meta_query' => sia_ancf_news_home_meta_query($placement),
    ];

    if (isset($args['meta_query']) && is_array($args['meta_query'])) {
        $defaults['meta_query'][] = $args['meta_query'];
        unset($args['meta_query']);
    }

    return sia_ancf_news_query_ids(array_merge($defaults, $args));
}

function sia_ancf_news_fill_ids(array $preferred, array $fallback, int $limit, array $exclude = []): array {
    $result = [];
    $blocked = array_fill_keys(array_map('intval', $exclude), true);

    foreach (array_merge($preferred, $fallback) as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || isset($blocked[$post_id]) || in_array($post_id, $result, true)) {
            continue;
        }
        $result[] = $post_id;
        if (count($result) >= $limit) {
            break;
        }
    }

    return $result;
}

function sia_ancf_news_related_ids(int $post_id, int $limit = 3): array {
    $category = sia_ancf_news_post_category($post_id);
    $args = [
        'posts_per_page' => $limit,
        'post__not_in'   => [$post_id],
    ];

    if ($category) {
        $args['cat'] = (int) $category->term_id;
    }

    return sia_ancf_news_query_ids($args);
}

SIA_Publisher_Intelligence::boot();
