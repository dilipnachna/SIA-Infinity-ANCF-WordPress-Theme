<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/inc/class-sia-publisher-intelligence.php';
require_once __DIR__ . '/inc/native-seo.php';
require_once __DIR__ . '/inc/native-theme-settings.php';
require_once __DIR__ . '/inc/native-category-sections.php';
require_once __DIR__ . '/inc/native-redirects.php';
require_once __DIR__ . '/inc/native-ads.php';
require_once __DIR__ . '/inc/native-toc.php';
require_once __DIR__ . '/inc/native-sitemaps.php';
require_once __DIR__ . '/inc/native-discovery.php';
require_once __DIR__ . '/inc/native-url-memory.php';
require_once __DIR__ . '/inc/native-avatars.php';
require_once __DIR__ . '/inc/native-copy-draft.php';
require_once __DIR__ . '/inc/native-semantic.php';
require_once __DIR__ . '/inc/native-related-stories.php';
require_once __DIR__ . '/inc/native-rank-smart.php';
require_once __DIR__ . '/inc/native-ancf-core.php';
require_once __DIR__ . '/inc/native-analytics.php';
require_once __DIR__ . '/inc/native-ga4.php';
require_once __DIR__ . '/inc/native-websub.php';
require_once __DIR__ . '/inc/native-webp.php';
require_once __DIR__ . '/inc/native-indexnow.php';
require_once __DIR__ . '/inc/native-faq.php';
require_once __DIR__ . '/inc/native-plugin-migration.php';
if (!class_exists('SIA_Entity_Author')) {
    require_once __DIR__ . '/inc/native-entity-author.php';
    SIA_Native_Entity_Author::boot();
}

function sia_ancf_news_setup(): void {
    load_theme_textdomain('sia-ancf-news', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 160, 'width' => 640, 'flex-height' => true, 'flex-width' => true]);
    add_editor_style('style.css');

    add_image_size('sia-news-hero', 1200, 675, false);
    add_image_size('sia-news-card', 720, 405, false);
    add_image_size('sia-news-thumb', 360, 203, false);

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

// Modern browsers render native emoji; avoid the extra detection script and
// stylesheet on every anonymous page.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
remove_filter('the_content_feed', 'wp_staticize_emoji');
remove_filter('comment_text_rss', 'wp_staticize_emoji');
remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

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
    $limit = max(1, $limit);
    $excluded = sia_ancf_news_home_excluded_category_ids();
    $excluded_map = array_fill_keys($excluded, true);
    $ordered = [];
    $seen = [];

    // When editors assign a Primary Menu, its top-level category order becomes
    // the newsroom section order. This gives editorial control without creating
    // another theme-specific category-order setting.
    $locations = get_nav_menu_locations();
    if (!empty($locations['primary'])) {
        $items = wp_get_nav_menu_items((int) $locations['primary']);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ((int) $item->menu_item_parent !== 0 || $item->type !== 'taxonomy' || $item->object !== 'category') {
                    continue;
                }

                $term_id = absint($item->object_id);
                if ($term_id <= 0 || isset($excluded_map[$term_id]) || isset($seen[$term_id])) {
                    continue;
                }

                $term = get_term($term_id, 'category');
                if (!$term instanceof WP_Term || is_wp_error($term) || $term->slug === 'uncategorized' || (int) $term->count <= 0) {
                    continue;
                }

                $ordered[] = $term;
                $seen[$term_id] = true;
                if (count($ordered) >= $limit) {
                    return $ordered;
                }
            }
        }
    }

    $categories = get_categories([
        'taxonomy'   => 'category',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => max(12, $limit * 3),
        'exclude'    => array_values(array_unique(array_merge($excluded, array_map('intval', array_keys($seen))))),
    ]);

    foreach ($categories as $category) {
        if (!isset($category->slug) || $category->slug === 'uncategorized' || isset($seen[(int) $category->term_id])) {
            continue;
        }
        $ordered[] = $category;
        $seen[(int) $category->term_id] = true;
        if (count($ordered) >= $limit) {
            break;
        }
    }

    return $ordered;
}

function sia_ancf_news_posts_index_url(): string {
    return add_query_arg('sia_latest', '1', home_url('/'));
}

// A dedicated read-only archive avoids depending on a posts page that may
// redirect to an unrelated category. No rewrite rules or database writes.
add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'sia_latest';
    return $vars;
});
add_action('pre_get_posts', static function (WP_Query $query): void {
    if (is_admin() || !$query->is_main_query() || $query->get('sia_latest') !== '1') {
        return;
    }
    $query->is_page = false;
    $query->is_singular = false;
    $query->is_home = true;
    $query->is_404 = false;
    $query->set('page_id', 0);
    $query->set('pagename', '');
    $query->set('post_type', 'post');
    $query->set('post_status', 'publish');
    $query->set('orderby', 'date');
    $query->set('order', 'DESC');
    $query->set('ignore_sticky_posts', true);
    $query->set('category__not_in', sia_ancf_news_home_excluded_category_ids());
});
add_filter('template_include', static function (string $template): string {
    if (get_query_var('sia_latest') === '1') {
        $archive = locate_template('index.php');
        return $archive ?: $template;
    }
    return $template;
});

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

function sia_ancf_news_was_meaningfully_updated(int $post_id, int $minimum_seconds = 60): bool {
    $published = (int) get_post_time('U', true, $post_id);
    $modified = (int) get_post_modified_time('U', true, $post_id);

    return $published > 0 && $modified > ($published + max(0, $minimum_seconds));
}

function sia_ancf_news_modified_label(int $post_id): string {
    if (!sia_ancf_news_was_meaningfully_updated($post_id)) {
        return '';
    }

    $date = get_the_modified_date('', $post_id);
    $time = get_the_modified_time(get_option('time_format'), $post_id);

    return sprintf(
        /* translators: 1: modified date, 2: modified time */
        __('Updated %1$s at %2$s', 'sia-ancf-news'),
        $date,
        $time
    );
}

function sia_ancf_news_thumbnail(int $post_id, string $size = 'sia-news-card', string $class = ''): void {
    $permalink = get_permalink($post_id);
    $label = sia_ancf_news_post_category($post_id);
    $has_image = has_post_thumbnail($post_id);
    $accessible_name = get_the_title($post_id);
    if (!$has_image) {
        $accessible_name .= ' — ' . ($label ? $label->name : get_bloginfo('name'));
    }

    echo '<a class="sia-media ' . esc_attr($class) . '" href="' . esc_url($permalink) . '" aria-label="' . esc_attr($accessible_name) . '">';
    if ($has_image) {
        // Legacy custom sizes were hard-cropped in existing WordPress metadata.
        // Use the built-in proportional sizes so old graphic/text thumbnails
        // also render uncut without regenerating or replacing media assets.
        $display_size = match ($size) {
            'sia-news-hero', 'sia-news-card' => 'large',
            'sia-news-thumb' => 'medium_large',
            default => $size,
        };
        echo get_the_post_thumbnail($post_id, $display_size, [
            'loading' => $size === 'sia-news-hero' ? 'eager' : 'lazy',
            'fetchpriority' => $size === 'sia-news-hero' ? 'high' : 'auto',
            'decoding' => 'async',
        ]);
    } else {
        echo '<span class="sia-media-placeholder" aria-hidden="true"><span>' . esc_html($label ? $label->name : get_bloginfo('name')) . '</span></span>';
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
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'fields'              => 'ids',
    ], $args));

    $ids = array_map('intval', $query->posts);
    // ID-only queries otherwise leave each card to fetch its own post, terms,
    // and metadata. Prime them in batches before the template renders cards.
    if ($ids && function_exists('_prime_post_caches')) {
        _prime_post_caches($ids, true, true);
    }
    return $ids;
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
