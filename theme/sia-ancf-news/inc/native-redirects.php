<?php
/** Exact same-site redirects, automatic URL-change rules and a bounded 404 monitor. */
if (!defined('ABSPATH')) { exit; }

function sia_ancf_redirect_path(string $path): string {
    if (preg_match('/%(?:2f|5c|00)/i', $path)) { return ''; }
    $path = trim(urldecode($path), '/');
    if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0")
        || preg_match('~(?:^|/)(?:\.\.?)(?:/|$)~', $path)
        || preg_match('~^(?:wp-admin|wp-login\.php|wp-json)(?:/|$)~i', $path)) { return ''; }
    return $path;
}

function sia_ancf_redirect_input_path(string $value, bool $allow_home = false): string {
    $value = trim($value);
    if ($allow_home && in_array($value, ['', '/'], true)) { return '/'; }
    if (str_contains($value, '?') || str_contains($value, '#') || preg_match('/[\r\n]/', $value)) { return ''; }
    $parts = wp_parse_url($value);
    if ($parts === false) { return ''; }
    if (isset($parts['host'])) {
        $home_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if (strtolower((string) $parts['host']) !== $home_host || isset($parts['user']) || isset($parts['pass'])) { return ''; }
        $value = (string) ($parts['path'] ?? '');
    }
    $path = sia_ancf_redirect_path($value);
    return $path === '' ? '' : $path;
}

function sia_ancf_redirect_rules(): array {
    $rules = get_option('sia_seo_redirects', []);
    if (!is_array($rules)) { return []; }
    $safe = [];
    foreach ($rules as $source => $target) {
        if (!is_string($source) || !is_string($target)) { continue; }
        $source = sia_ancf_redirect_input_path($source);
        $target = sia_ancf_redirect_input_path($target, true);
        if ($source !== '' && $target !== '' && $target !== '/' . $source) {
            $safe[$source] = $target === '/' ? '/' : '/' . ltrim($target, '/');
        }
    }
    return $safe;
}

function sia_ancf_redirect_final_target(string $source, string $target, array $rules): string {
    $seen = [$source => true];
    $cursor = $target;
    for ($hop = 0; $hop < 20; $hop++) {
        if ($cursor === '/') { return '/'; }
        $key = sia_ancf_redirect_input_path($cursor);
        if ($key === '' || isset($seen[$key])) { return ''; }
        $seen[$key] = true;
        if (!isset($rules[$key]) || !is_string($rules[$key])) { return '/' . ltrim($key, '/'); }
        $cursor = $rules[$key];
    }
    return '';
}

function sia_ancf_redirect_add_rule(string $source_input, string $target_input, string $origin = 'manual') {
    $source = sia_ancf_redirect_input_path($source_input);
    $target = sia_ancf_redirect_input_path($target_input, true);
    if ($source === '' || $target === '') {
        return new WP_Error('invalid_redirect', __('Use exact same-site paths without query strings or fragments.', 'sia-ancf-news'));
    }
    $target = $target === '/' ? '/' : '/' . ltrim($target, '/');
    $rules = sia_ancf_redirect_rules();
    $candidate = $rules;
    $candidate[$source] = $target;
    $final = sia_ancf_redirect_final_target($source, $target, $candidate);
    if ($final === '') { return new WP_Error('redirect_loop', __('The redirect would create a loop or an invalid chain.', 'sia-ancf-news')); }
    $candidate[$source] = $final;
    foreach ($candidate as $from => $to) {
        $resolved = sia_ancf_redirect_final_target($from, $to, $candidate);
        if ($resolved === '') { return new WP_Error('redirect_loop', __('An existing redirect chain would become invalid.', 'sia-ancf-news')); }
        $candidate[$from] = $resolved;
    }
    ksort($candidate, SORT_NATURAL | SORT_FLAG_CASE);
    update_option('sia_seo_redirects', $candidate, false);
    $meta = get_option('sia_seo_redirect_meta', []);
    $meta = is_array($meta) ? $meta : [];
    $now = current_time('mysql', true);
    $previous = isset($meta[$source]) && is_array($meta[$source]) ? $meta[$source] : [];
    $meta[$source] = [
        'origin' => sanitize_key($origin) ?: 'manual',
        'created_gmt' => (string) ($previous['created_gmt'] ?? $now),
        'updated_gmt' => $now,
        'hits' => absint($previous['hits'] ?? 0),
        'last_accessed_gmt' => sanitize_text_field((string) ($previous['last_accessed_gmt'] ?? '')),
    ];
    update_option('sia_seo_redirect_meta', $meta, false);
    return true;
}

function sia_ancf_redirect_delete_rule(string $source_input): bool {
    $source = sia_ancf_redirect_input_path($source_input);
    if ($source === '') { return false; }
    $rules = sia_ancf_redirect_rules();
    if (!array_key_exists($source, $rules)) { return false; }
    unset($rules[$source]);
    update_option('sia_seo_redirects', $rules, false);
    $meta = get_option('sia_seo_redirect_meta', []);
    if (is_array($meta)) { unset($meta[$source]); update_option('sia_seo_redirect_meta', $meta, false); }
    return true;
}

function sia_ancf_redirect_record_hit(string $source): void {
    $meta = get_option('sia_seo_redirect_meta', []);
    $meta = is_array($meta) ? $meta : [];
    $row = isset($meta[$source]) && is_array($meta[$source]) ? $meta[$source] : [];
    $row['origin'] = sanitize_key((string) ($row['origin'] ?? 'imported')) ?: 'imported';
    $row['created_gmt'] = sanitize_text_field((string) ($row['created_gmt'] ?? current_time('mysql', true)));
    $row['updated_gmt'] = sanitize_text_field((string) ($row['updated_gmt'] ?? $row['created_gmt']));
    $row['hits'] = absint($row['hits'] ?? 0) + 1;
    $row['last_accessed_gmt'] = current_time('mysql', true);
    $meta[$source] = $row;
    update_option('sia_seo_redirect_meta', $meta, false);
}

add_action('template_redirect', static function (): void {
    if (!sia_ancf_native_seo_enabled() || is_admin() || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) { return; }
    $request = wp_unslash($_SERVER['REQUEST_URI'] ?? '');
    $path = wp_parse_url($request, PHP_URL_PATH);
    $key = is_string($path) ? sia_ancf_redirect_path($path) : '';
    $rules = sia_ancf_redirect_rules();
    if ($key === '' || !isset($rules[$key])) { return; }
    $target = $rules[$key];
    $target_path = ltrim($target, '/');
    if ($target === '/') {
        $url = home_url('/');
    } elseif (preg_match('~(?:^|/)[^/]+\.[a-z0-9]{1,8}$~i', $target_path)) {
        $url = home_url('/' . $target_path);
    } else {
        $url = home_url(user_trailingslashit($target_path, 'single'));
    }
    $query = wp_parse_url($request, PHP_URL_QUERY);
    if (is_string($query) && $query !== '' && !preg_match('/[\r\n]/', $query)) { $url .= '?' . $query; }
    sia_ancf_redirect_record_hit($key);
    if (wp_safe_redirect($url, 301, 'SIA Redirect Manager')) { exit; }
}, 0);

function sia_ancf_redirect_from_urls(string $old_url, string $new_url, string $origin): void {
    $old = sia_ancf_redirect_input_path($old_url);
    $new = sia_ancf_redirect_input_path($new_url, true);
    if ($old !== '' && $new !== '' && $old !== $new) { sia_ancf_redirect_add_rule($old, $new, $origin); }
}

add_action('post_updated', static function (int $post_id, WP_Post $after, WP_Post $before): void {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)
        || $before->post_status !== 'publish' || $after->post_status !== 'publish'
        || !in_array($after->post_type, ['post', 'page'], true)) { return; }
    $old = get_permalink($before);
    $new = get_permalink($after);
    if (is_string($old) && is_string($new) && $old !== $new) { sia_ancf_redirect_from_urls($old, $new, 'automatic-post'); }
}, 40, 3);

$GLOBALS['sia_ancf_term_old_urls'] = [];
add_action('edit_term', static function (int $term_id, int $term_taxonomy_id, string $taxonomy): void {
    if ($taxonomy !== 'category') { return; }
    $term = get_term($term_id, $taxonomy);
    $url = $term instanceof WP_Term ? get_term_link($term) : null;
    if (is_string($url)) { $GLOBALS['sia_ancf_term_old_urls'][$term_id] = $url; }
}, 10, 3);
add_action('edited_category', static function (int $term_id): void {
    $old = $GLOBALS['sia_ancf_term_old_urls'][$term_id] ?? '';
    $term = get_term($term_id, 'category');
    $new = $term instanceof WP_Term ? get_term_link($term) : null;
    if (is_string($old) && is_string($new) && $old !== '' && $old !== $new) { sia_ancf_redirect_from_urls($old, $new, 'automatic-category'); }
    unset($GLOBALS['sia_ancf_term_old_urls'][$term_id]);
}, 40);

add_action('template_redirect', static function (): void {
    if (!sia_ancf_native_seo_enabled() || !is_404() || is_admin()
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) { return; }
    $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $key = is_string($path) ? sia_ancf_redirect_path($path) : '';
    if ($key === '') { return; }
    $log = get_option('sia_404_log', []);
    $log = is_array($log) ? $log : [];
    $row = isset($log[$key]) && is_array($log[$key]) ? $log[$key] : [];
    $log[$key] = [
        'hits' => absint($row['hits'] ?? 0) + 1,
        'first_seen_gmt' => sanitize_text_field((string) ($row['first_seen_gmt'] ?? current_time('mysql', true))),
        'last_seen_gmt' => current_time('mysql', true),
    ];
    uasort($log, static fn(array $a, array $b): int => strcmp((string) ($b['last_seen_gmt'] ?? ''), (string) ($a['last_seen_gmt'] ?? '')));
    update_option('sia_404_log', array_slice($log, 0, 200, true), false);
}, 90);

add_action('admin_menu', static function (): void {
    add_management_page(__('Redirects', 'sia-ancf-news'), __('Redirects', 'sia-ancf-news'), 'manage_options', 'sia-redirects', 'sia_ancf_redirect_admin_page');
});

function sia_ancf_redirect_admin_page(): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response' => 403]); }
    $rules = sia_ancf_redirect_rules();
    $meta = get_option('sia_seo_redirect_meta', []); $meta = is_array($meta) ? $meta : [];
    $log = get_option('sia_404_log', []); $log = is_array($log) ? $log : [];
    echo '<div class="wrap"><h1>' . esc_html__('SIA Redirect Manager', 'sia-ancf-news') . '</h1>';
    echo '<p>' . esc_html__('Exact same-site permanent redirects only. Post, page and category URL changes create redirects automatically and chains are flattened.', 'sia-ancf-news') . '</p>';
    echo '<h2>' . esc_html__('Add or update a 301 redirect', 'sia-ancf-news') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sia_redirect_save">';
    wp_nonce_field('sia_redirect_save');
    echo '<p><label>' . esc_html__('Source path', 'sia-ancf-news') . ' <input class="regular-text" name="source" required placeholder="old-path"></label></p>';
    echo '<p><label>' . esc_html__('Destination path', 'sia-ancf-news') . ' <input class="regular-text" name="target" required placeholder="new-path or /"></label></p>';
    submit_button(__('Save permanent redirect', 'sia-ancf-news'));
    echo '</form><h2>' . sprintf(esc_html__('Redirects (%d)', 'sia-ancf-news'), count($rules)) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__('Source', 'sia-ancf-news') . '</th><th>' . esc_html__('Destination', 'sia-ancf-news') . '</th><th>' . esc_html__('Origin', 'sia-ancf-news') . '</th><th>' . esc_html__('Hits', 'sia-ancf-news') . '</th><th>' . esc_html__('Action', 'sia-ancf-news') . '</th></tr></thead><tbody>';
    foreach ($rules as $source => $target) {
        $row = isset($meta[$source]) && is_array($meta[$source]) ? $meta[$source] : [];
        echo '<tr><td><code>/' . esc_html($source) . '</code></td><td><code>' . esc_html($target) . '</code></td><td>' . esc_html((string) ($row['origin'] ?? 'imported')) . '</td><td>' . absint($row['hits'] ?? 0) . '</td><td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sia_redirect_delete"><input type="hidden" name="source" value="' . esc_attr($source) . '">';
        wp_nonce_field('sia_redirect_delete_' . $source); submit_button(__('Delete', 'sia-ancf-news'), 'delete small', 'submit', false); echo '</form></td></tr>';
    }
    if (!$rules) { echo '<tr><td colspan="5">' . esc_html__('No redirects.', 'sia-ancf-news') . '</td></tr>'; }
    echo '</tbody></table><h2>' . sprintf(esc_html__('404 monitor (%d)', 'sia-ancf-news'), count($log)) . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sia_redirect_clear_404">';
    wp_nonce_field('sia_redirect_clear_404'); submit_button(__('Clear 404 log', 'sia-ancf-news'), 'secondary', 'submit', false);
    echo '</form><table class="widefat striped"><thead><tr><th>' . esc_html__('Path', 'sia-ancf-news') . '</th><th>' . esc_html__('Hits', 'sia-ancf-news') . '</th><th>' . esc_html__('Last seen (GMT)', 'sia-ancf-news') . '</th></tr></thead><tbody>';
    foreach ($log as $path => $row) { echo '<tr><td><code>/' . esc_html((string) $path) . '</code></td><td>' . absint($row['hits'] ?? 0) . '</td><td>' . esc_html((string) ($row['last_seen_gmt'] ?? '')) . '</td></tr>'; }
    if (!$log) { echo '<tr><td colspan="3">' . esc_html__('No recorded 404 paths.', 'sia-ancf-news') . '</td></tr>'; }
    echo '</tbody></table></div>';
}

add_action('admin_post_sia_redirect_save', static function (): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response' => 403]); }
    check_admin_referer('sia_redirect_save');
    $source = isset($_POST['source']) && is_string($_POST['source']) ? wp_unslash($_POST['source']) : '';
    $target = isset($_POST['target']) && is_string($_POST['target']) ? wp_unslash($_POST['target']) : '';
    $result = sia_ancf_redirect_add_rule($source, $target, 'manual');
    $args = is_wp_error($result) ? ['sia_error' => $result->get_error_code()] : ['updated' => '1'];
    wp_safe_redirect(add_query_arg($args, admin_url('tools.php?page=sia-redirects'))); exit;
});
add_action('admin_post_sia_redirect_delete', static function (): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response' => 403]); }
    $source = isset($_POST['source']) && is_string($_POST['source']) ? wp_unslash($_POST['source']) : '';
    check_admin_referer('sia_redirect_delete_' . $source); sia_ancf_redirect_delete_rule($source);
    wp_safe_redirect(add_query_arg('deleted', '1', admin_url('tools.php?page=sia-redirects'))); exit;
});
add_action('admin_post_sia_redirect_clear_404', static function (): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response' => 403]); }
    check_admin_referer('sia_redirect_clear_404'); delete_option('sia_404_log');
    wp_safe_redirect(add_query_arg('cleared', '1', admin_url('tools.php?page=sia-redirects'))); exit;
});
