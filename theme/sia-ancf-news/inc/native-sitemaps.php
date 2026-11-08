<?php
/** Specialized sitemap fallback; no provider-generated sitemap is duplicated. */
if (!defined('ABSPATH')) {
    exit;
}

function sia_ancf_sitemap_query(string $kind, int $page = 1): ?WP_Query {
    $meta = [
        'relation' => 'AND',
        ['relation' => 'OR', ['key' => '_sia_seo_noindex', 'compare' => 'NOT EXISTS'], ['key' => '_sia_seo_noindex', 'value' => '1', 'compare' => '!=']],
        ['relation' => 'OR', ['key' => 'rank_math_robots', 'compare' => 'NOT EXISTS'], ['key' => 'rank_math_robots', 'value' => 'noindex', 'compare' => 'NOT LIKE']],
    ];
    $args = [
        'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
        'posts_per_page' => max(1, min(1000, (int) apply_filters('sia_ancf_sitemap_page_size', 1000))), 'paged' => max(1, $page), 'ignore_sticky_posts' => true, 'no_found_rows' => false,
        'orderby' => 'date', 'order' => 'DESC', 'meta_query' => $meta,
    ];
    if ($kind === 'news') {
        $root = function_exists('sia_ancf_news_category_id') ? sia_ancf_news_category_id() : 0;
        if ($root <= 0) {
            return null;
        }
        $args['tax_query'] = [[
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => [$root],
            'include_children' => true,
        ]];
        $args['date_query'] = [['column' => 'post_date_gmt', 'after' => gmdate('Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS), 'before' => gmdate('Y-m-d H:i:s'), 'inclusive' => true]];
    } elseif ($kind === 'video') {
        $args['meta_query'][] = ['key' => '_sia_video_schema', 'compare' => 'EXISTS'];
    } else {
        return null;
    }
    return new WP_Query($args);
}

function sia_ancf_sitemap_video(WP_Post $post): ?array {
    if (get_post_meta($post->ID, '_sia_video_unavailable', true) === '1') {
        return null;
    }
    $data = get_post_meta($post->ID, '_sia_video_schema', true);
    if (!is_array($data)) {
        return null;
    }
    if (isset($data['content_sha256']) && (!is_string($data['content_sha256'])
        || !preg_match('/\A[a-f0-9]{64}\z/', $data['content_sha256'])
        || !hash_equals(hash('sha256', $post->post_content), $data['content_sha256']))) {
        return null;
    }
    foreach (['name', 'description', 'thumbnailUrl', 'embedUrl', 'uploadDate'] as $key) {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            return null;
        }
    }
    foreach (['thumbnailUrl', 'embedUrl'] as $key) {
        $url = wp_parse_url($data[$key]);
        if (!is_array($url) || !in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)
            || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
            return null;
        }
    }
    $video_id = basename((string) wp_parse_url($data['embedUrl'], PHP_URL_PATH));
    if ($video_id === '' || !str_contains($post->post_content, $video_id) || strtotime($data['uploadDate']) === false) {
        return null;
    }
    return $data;
}

add_action('wp_sitemaps_init', static function (): void {
    if (!sia_ancf_native_seo_enabled() || !(bool) get_option('blog_public')) {
        return;
    }
    if (!class_exists('SIA_ANCF_Special_Sitemaps')) {
        class SIA_ANCF_Special_Sitemaps extends WP_Sitemaps_Provider {
            protected $name = 'sia-special';
            protected $object_type = 'sia-special';
            public function get_url_list($page_num, $object_subtype = '') { return []; }
            public function get_max_num_pages($object_subtype = '') { return 1; }
            public function get_sitemap_entries() {
                $entries = [];
                foreach (['news', 'video'] as $kind) {
                    $query = sia_ancf_sitemap_query($kind);
                    $pages = max(1, (int) $query->max_num_pages);
                    for ($page = 1; $page <= $pages; $page++) {
                        $entries[] = ['loc' => home_url('/' . $kind . '-sitemap' . ($page === 1 ? '' : $page) . '.xml')];
                    }
                }
                return $entries;
            }
        }
    }
    wp_register_sitemap_provider('sia-special', new SIA_ANCF_Special_Sitemaps());
});

add_action('template_redirect', static function (): void {
    if (!sia_ancf_native_seo_enabled() || !(bool) get_option('blog_public')
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        return;
    }
    $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $home = wp_parse_url(home_url('/'), PHP_URL_PATH);
    $relative = is_string($path) && is_string($home) && str_starts_with($path, $home) ? substr($path, strlen($home)) : '';
    if (!preg_match('/\A(news|video)-sitemap([2-9]|[1-9][0-9]+)?\.xml\z/', $relative, $matches)) {
        return;
    }
    $kind = $matches[1];
    $page = isset($matches[2]) ? (int) $matches[2] : 1;
    $query = sia_ancf_sitemap_query($kind, $page);
    if (!$query) {
        status_header(404);
        exit;
    }
    if ($page > max(1, (int) $query->max_num_pages)) {
        status_header(404);
        exit;
    }
    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
    foreach ($query->posts as $post) {
        $url = get_permalink($post);
        if (!$url) {
            continue;
        }
        if ($kind === 'news') {
            $lang = explode('-', get_bloginfo('language'))[0] ?: 'en';
            echo '<url><loc>' . esc_xml($url) . '</loc><news:news><news:publication><news:name>' . esc_xml(get_bloginfo('name')) . '</news:name><news:language>' . esc_xml($lang) . '</news:language></news:publication>';
            echo '<news:publication_date>' . esc_xml(get_post_time(DATE_W3C, true, $post)) . '</news:publication_date><news:title>' . esc_xml(wp_strip_all_tags($post->post_title)) . '</news:title></news:news></url>';
            continue;
        }
        $video = sia_ancf_sitemap_video($post);
        if (!$video) {
            continue;
        }
        echo '<url><loc>' . esc_xml($url) . '</loc><video:video><video:thumbnail_loc>' . esc_xml(esc_url_raw($video['thumbnailUrl'])) . '</video:thumbnail_loc>';
        echo '<video:title>' . esc_xml(wp_html_excerpt(wp_strip_all_tags($video['name']), 100, '')) . '</video:title><video:description>' . esc_xml(wp_html_excerpt(wp_strip_all_tags($video['description']), 2048, '')) . '</video:description>';
        echo '<video:player_loc>' . esc_xml(esc_url_raw($video['embedUrl'])) . '</video:player_loc><video:publication_date>' . esc_xml(gmdate(DATE_W3C, strtotime($video['uploadDate']))) . '</video:publication_date>';
        $duration = $video['duration'] ?? '';
        if (is_string($duration) && preg_match('/\APT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?\z/', $duration, $m)) {
            $seconds = ((int) ($m[1] ?? 0) * 3600) + ((int) ($m[2] ?? 0) * 60) + (int) ($m[3] ?? 0);
            if ($seconds > 0 && $seconds <= 28800) {
                echo '<video:duration>' . (int) $seconds . '</video:duration>';
            }
        }
        if (isset($video['isFamilyFriendly'])) {
            echo '<video:family_friendly>' . (in_array($video['isFamilyFriendly'], [true, 1, '1', 'yes'], true) ? 'yes' : 'no') . '</video:family_friendly>';
        }
        echo '</video:video></url>';
    }
    echo '</urlset>';
    exit;
}, -2);

/**
 * Serve the common Rank Math/Core sitemap routes directly. This deliberately
 * avoids rewrite-rule dependence after an SEO plugin is deactivated.
 */
function sia_ancf_native_sitemap_relative_path(): string {
    $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $home = wp_parse_url(home_url('/'), PHP_URL_PATH);
    if (!is_string($path) || !is_string($home) || !str_starts_with($path, $home)) {
        return '';
    }
    return ltrim(substr($path, strlen($home)), '/');
}

function sia_ancf_native_sitemap_meta_query(): array {
    return [
        'relation' => 'AND',
        ['relation' => 'OR', ['key' => '_sia_seo_noindex', 'compare' => 'NOT EXISTS'], ['key' => '_sia_seo_noindex', 'value' => '1', 'compare' => '!=']],
        ['relation' => 'OR', ['key' => 'rank_math_robots', 'compare' => 'NOT EXISTS'], ['key' => 'rank_math_robots', 'value' => 'noindex', 'compare' => 'NOT LIKE']],
    ];
}

function sia_ancf_native_sitemap_posts(string $post_type, int $page = 1): WP_Query {
    return new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'has_password' => false,
        'posts_per_page' => max(1, min(1000, (int) apply_filters('sia_ancf_sitemap_page_size', 1000))),
        'paged' => max(1, $page),
        'ignore_sticky_posts' => true,
        'no_found_rows' => false,
        'orderby' => 'modified',
        'order' => 'DESC',
        'meta_query' => sia_ancf_native_sitemap_meta_query(),
    ]);
}

function sia_ancf_native_sitemap_terms(string $taxonomy, int $page = 1) {
    if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
        return new WP_Error('invalid_taxonomy');
    }
    $size = max(1, min(1000, (int) apply_filters('sia_ancf_sitemap_page_size', 1000)));
    return array_slice(sia_ancf_native_sitemap_indexable_terms($taxonomy), (max(1, $page) - 1) * $size, $size);
}

function sia_ancf_native_sitemap_indexable_terms(string $taxonomy): array {
    if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
        return [];
    }
    static $cache = [];
    if (isset($cache[$taxonomy])) {
        return $cache[$taxonomy];
    }
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'orderby' => 'term_id',
        'order' => 'ASC',
    ]);
    if (is_wp_error($terms)) {
        return $cache[$taxonomy] = [];
    }
    return $cache[$taxonomy] = array_values(array_filter(
        $terms,
        static fn($term): bool => $term instanceof WP_Term && sia_ancf_term_has_indexable_posts($term)
    ));
}

function sia_ancf_native_sitemap_term_count(string $taxonomy): int {
    if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
        return 0;
    }
    return count(sia_ancf_native_sitemap_indexable_terms($taxonomy));
}

function sia_ancf_native_sitemap_headers(): void {
    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow', true);
}

function sia_ancf_native_sitemap_index(): void {
    $size = max(1, min(1000, (int) apply_filters('sia_ancf_sitemap_page_size', 1000)));
    $entries = [];
    foreach (['post', 'page'] as $post_type) {
        $query = sia_ancf_native_sitemap_posts($post_type);
        for ($page = 1; $page <= (int) $query->max_num_pages; $page++) {
            $entries[] = home_url('/' . $post_type . '-sitemap' . ($page === 1 ? '' : $page) . '.xml');
        }
    }
    foreach (['category', 'post_tag'] as $taxonomy) {
        $term_pages = (int) ceil(sia_ancf_native_sitemap_term_count($taxonomy) / $size);
        for ($page = 1; $page <= $term_pages; $page++) {
            $entries[] = home_url('/' . $taxonomy . '-sitemap' . ($page === 1 ? '' : $page) . '.xml');
        }
    }
    foreach (['news', 'video'] as $kind) {
        $query = sia_ancf_sitemap_query($kind);
        if ($query && $query->found_posts > 0) {
            for ($page = 1; $page <= (int) $query->max_num_pages; $page++) {
                $entries[] = home_url('/' . $kind . '-sitemap' . ($page === 1 ? '' : $page) . '.xml');
            }
        }
    }
    sia_ancf_native_sitemap_headers();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($entries as $url) {
        echo '<sitemap><loc>' . esc_xml($url) . '</loc></sitemap>';
    }
    echo '</sitemapindex>';
    exit;
}

function sia_ancf_native_sitemap_urlset(string $type, int $page): void {
    $urls = [];
    $max_pages = 0;
    if (in_array($type, ['post', 'page'], true)) {
        $query = sia_ancf_native_sitemap_posts($type, $page);
        $max_pages = (int) $query->max_num_pages;
        foreach ($query->posts as $post) {
            $loc = get_permalink($post);
            if (!$loc) {
                continue;
            }
            $modified = get_post_modified_time(DATE_W3C, true, $post);
            $urls[] = ['loc' => $loc, 'lastmod' => is_string($modified) ? $modified : ''];
        }
    } elseif (in_array($type, ['category', 'post_tag'], true)) {
        $size = max(1, min(1000, (int) apply_filters('sia_ancf_sitemap_page_size', 1000)));
        $max_pages = (int) ceil(sia_ancf_native_sitemap_term_count($type) / $size);
        $terms = sia_ancf_native_sitemap_terms($type, $page);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $loc = get_term_link($term);
                if (!is_wp_error($loc)) {
                    $urls[] = ['loc' => $loc, 'lastmod' => ''];
                }
            }
        }
    }
    if ($page > $max_pages || $max_pages < 1) {
        status_header(404);
        exit;
    }
    sia_ancf_native_sitemap_headers();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) {
        echo '<url><loc>' . esc_xml($url['loc']) . '</loc>';
        if ($url['lastmod'] !== '') {
            echo '<lastmod>' . esc_xml($url['lastmod']) . '</lastmod>';
        }
        echo '</url>';
    }
    echo '</urlset>';
    exit;
}

add_action('template_redirect', static function (): void {
    if (!sia_ancf_native_seo_enabled() || !(bool) get_option('blog_public')
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        return;
    }
    $relative = sia_ancf_native_sitemap_relative_path();
    if (in_array($relative, ['sitemap_index.xml', 'wp-sitemap.xml'], true)) {
        sia_ancf_native_sitemap_index();
    }
    if (preg_match('/\A(post|page|category|post_tag|tag)-sitemap([2-9]|[1-9][0-9]+)?\.xml\z/', $relative, $matches)) {
        $type = $matches[1] === 'tag' ? 'post_tag' : $matches[1];
        sia_ancf_native_sitemap_urlset($type, isset($matches[2]) ? (int) $matches[2] : 1);
    }
}, -20);
