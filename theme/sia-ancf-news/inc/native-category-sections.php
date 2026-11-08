<?php
/** Native base-less category routes for the Article and News section trees. */
if (!defined('ABSPATH')) { exit; }

function sia_ancf_category_path(WP_Term $term): string {
    if ($term->taxonomy !== 'category') { return ''; }
    $parts = [];
    foreach (array_reverse(get_ancestors($term->term_id, 'category', 'taxonomy')) as $ancestor_id) {
        $ancestor = get_term($ancestor_id, 'category');
        if ($ancestor instanceof WP_Term && !is_wp_error($ancestor)) { $parts[] = $ancestor->slug; }
    }
    $parts[] = $term->slug;
    return implode('/', array_filter(array_map('sanitize_title', $parts)));
}

add_filter('category_link', static function (string $url, int $term_id): string {
    $term = get_term($term_id, 'category');
    if (!$term instanceof WP_Term || is_wp_error($term)) { return $url; }
    $path = sia_ancf_category_path($term);
    return $path === '' ? $url : home_url(user_trailingslashit($path, 'category'));
}, 20, 2);

function sia_ancf_register_category_rewrite_rules(): void {
    $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
    if (is_wp_error($terms)) { return; }
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) { continue; }
        $path = sia_ancf_category_path($term);
        if ($path === '') { continue; }
        $quoted = preg_quote($path, '~');
        add_rewrite_rule('^' . $quoted . '/?$', 'index.php?category_name=' . $path, 'top');
        add_rewrite_rule('^' . $quoted . '/page/([0-9]{1,})/?$', 'index.php?category_name=' . $path . '&paged=$matches[1]', 'top');
        add_rewrite_rule('^' . $quoted . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$', 'index.php?category_name=' . $path . '&feed=$matches[1]', 'top');
    }
}
add_action('init', 'sia_ancf_register_category_rewrite_rules', 20);
add_action('init', static function (): void {
    if (get_option('sia_category_rewrite_flush_pending') === '1') {
        flush_rewrite_rules(false);
        delete_option('sia_category_rewrite_flush_pending');
    }
}, 99);
foreach (['created_category', 'edited_category', 'delete_category'] as $sia_category_hook) {
    add_action($sia_category_hook, static function (): void {
        update_option('sia_category_rewrite_flush_pending', '1', false);
    }, 90);
}
unset($sia_category_hook);

add_action('template_redirect', static function (): void {
    if (!is_category() || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) { return; }
    $request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (!is_string($request_path) || !preg_match('~/(?:category)/~i', $request_path)) { return; }
    $term = get_queried_object();
    $target = $term instanceof WP_Term ? get_term_link($term) : null;
    if (is_string($target) && wp_safe_redirect($target, 301, 'SIA Category Sections')) { exit; }
}, -30);
