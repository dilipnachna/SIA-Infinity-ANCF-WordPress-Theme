<?php
/** Minimal TOC fallback for the site's existing TOC+ settings. */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', static function (): void {
    if (!defined('TOC_VERSION') && !shortcode_exists('toc')) {
        add_shortcode('toc', static function (): string { return '<!-- sia-native-toc -->'; });
    }
    if (!defined('TOC_VERSION') && !shortcode_exists('no_toc')) {
        add_shortcode('no_toc', static function (): string { return '<!-- sia-native-no-toc -->'; });
    }
}, 20);

add_filter('the_content', static function (string $content): string {
    if (defined('TOC_VERSION') || is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)
        || !is_singular('post') || !in_the_loop() || !is_main_query() || post_password_required()) {
        return $content;
    }
    $marker = '<!-- sia-native-toc -->';
    $disabled = '<!-- sia-native-no-toc -->';
    if (str_contains($content, $disabled)) {
        return str_replace([$disabled, $marker], '', $content);
    }
    $raw = get_option('toc-options', []);
    $opt = is_array($raw) ? $raw : [];
    $types = $opt['auto_insert_post_types'] ?? ['post'];
    $has_marker = str_contains($content, $marker);
    if ((!$has_marker && (!is_array($types) || !in_array('post', $types, true)))
        || (!empty($opt['exclude'])) || (!empty($opt['show_heirarchy']))
        || (isset($opt['position']) && (int) $opt['position'] !== 1)
        || strlen($content) > 1048576
        || preg_match('~<(?:script|style|pre|code|textarea|template)\b~i', $content)) {
        return str_replace($marker, '', $content);
    }
    $levels = $opt['heading_levels'] ?? [1, 2, 3];
    $levels = is_array($levels) ? array_map('intval', $levels) : [1, 2, 3];
    $minimum = max(2, min(20, (int) ($opt['start'] ?? 2)));
    $pattern = '~(<h([1-6])\b[^>]*>)(.*?)(</h\2\s*>)~isu';
    if (!preg_match_all($pattern, $content, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return str_replace($marker, '', $content);
    }
    $selected = [];
    foreach ($found as $match) {
        $title = trim(wp_strip_all_tags($match[3][0]));
        if (in_array((int) $match[2][0], $levels, true) && $title !== '') {
            $selected[] = $match;
        }
    }
    if (count($selected) < $minimum || count($selected) > 100) {
        return str_replace($marker, '', $content);
    }
    $existing = [];
    if (preg_match_all('~\bid\s*=\s*["\']([^"\']+)["\']~i', $content, $ids)) {
        $existing = array_fill_keys($ids[1], true);
    }
    $used = [];
    $items = [];
    foreach ($selected as $match) {
        $raw_title = trim(wp_strip_all_tags($match[3][0]));
        $slug = remove_accents($raw_title);
        $slug = str_replace(["\r", "\n", '&amp;'], [' ', ' ', ''], $slug);
        $slug = preg_replace('/[^a-zA-Z0-9 _-]*/', '', $slug);
        $slug = rtrim(str_replace(['  ', ' '], '_', $slug), '-_');
        if (($opt['lowercase'] ?? true)) {
            $slug = strtolower($slug);
        }
        if ($slug === '') {
            $slug = sanitize_html_class((string) ($opt['fragment_prefix'] ?? 'i'), 'i');
        }
        if (($opt['hyphenate'] ?? true)) {
            $slug = str_replace('--', '-', str_replace('_', '-', $slug));
        }
        $used[$slug] = ($used[$slug] ?? 0) + 1;
        $id = $slug . ($used[$slug] > 1 ? '-' . $used[$slug] : '');
        if (isset($existing[$id])) {
            return str_replace($marker, '', $content);
        }
        $existing[$id] = true;
        $items[] = ['match' => $match, 'id' => $id, 'title' => html_entity_decode($raw_title, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8')];
    }
    $label = isset($opt['heading_text']) && is_string($opt['heading_text']) ? wp_strip_all_tags($opt['heading_text']) : __('Contents', 'sia-ancf-news');
    $nav = '<nav class="sia-native-toc" aria-label="' . esc_attr__('Contents', 'sia-ancf-news') . '"><strong>' . esc_html($label) . '</strong><ul>';
    foreach ($items as $item) {
        $nav .= '<li><a href="#' . esc_attr($item['id']) . '">' . esc_html($item['title']) . '</a></li>';
    }
    $nav .= '</ul></nav>';
    $result = '';
    $cursor = 0;
    foreach ($items as $index => $item) {
        $match = $item['match'];
        $offset = $match[0][1];
        $result .= substr($content, $cursor, $offset - $cursor);
        if ($index === 0 && !$has_marker) {
            $result .= $nav;
        }
        $result .= $match[1][0] . '<span id="' . esc_attr($item['id']) . '">' . $match[3][0] . '</span>' . $match[4][0];
        $cursor = $offset + strlen($match[0][0]);
    }
    $result .= substr($content, $cursor);
    return str_replace($marker, $has_marker ? $nav : '', $result);
}, 20);
