<?php
/**
 * Virtual root discovery documents. No filesystem writes or scheduled jobs.
 *
 * WordPress handles /robots.txt through its native rewrite and robots_txt
 * filter; /llms.txt is served during parse_request so rewrite flushing is
 * unnecessary. A physical file at the web root takes precedence in Nginx.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Dedicated SEO providers may own robots.txt; don't override their policy. */
function sia_ancf_discovery_external_seo_present(): bool {
    return defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')
        || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')
        || defined('THE_SEO_FRAMEWORK_VERSION');
}

/** The theme's canonical sitemap is /sitemap_index.xml on this site. */
function sia_ancf_discovery_sitemap_url(): string {
    return (string) apply_filters('sia_ancf_discovery_sitemap_url', home_url('/sitemap_index.xml'));
}

function sia_ancf_discovery_robots_content(bool $public): string {
    if (!$public) {
        return "User-agent: *\nDisallow: /\n";
    }
    return "User-agent: *\n"
        . "Disallow: /wp-admin/\n"
        . "Allow: /wp-admin/admin-ajax.php\n\n"
        . "Sitemap: " . esc_url_raw(sia_ancf_discovery_sitemap_url()) . "\n";
}

/**
 * All crawling user agents receive the same WordPress-compatible rules.
 * Respect WordPress' "Discourage search engines" setting; never re-enable
 * crawling for a site the owner marked private.
 */
add_filter('robots_txt', static function (string $output, bool $public): string {
    return sia_ancf_discovery_external_seo_present()
        ? $output : sia_ancf_discovery_robots_content($public);
}, 1000, 2);

/**
 * WordPress core's do_robots action can run after other sitemap/robots hooks.
 * Serve one canonical document before the default callback appends a second
 * sitemap. Keep the filter above for hosts that serve robots via that filter.
 */
add_action('do_robots', static function (): void {
    if (sia_ancf_discovery_external_seo_present()) {
        return;
    }
    status_header(200);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    nocache_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo sia_ancf_discovery_robots_content((bool) get_option('blog_public'));
    }
    exit;
}, 0);

/** Sanitize visible titles for Markdown link labels. */
function sia_ancf_discovery_markdown_label(string $text): string {
    $text = sanitize_text_field(wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $text = str_replace(['[', ']', '(', ')', '<', '>', '\\', "\r", "\n"], ' ', $text);
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

/** Only include public, non-password-protected, indexable content. */
function sia_ancf_discovery_is_public_post(WP_Post $post): bool {
    return $post->post_status === 'publish'
        && $post->post_password === ''
        && (!function_exists('sia_ancf_post_is_noindex') || !sia_ancf_post_is_noindex((int) $post->ID));
}

/** Generate a small, current discovery directory from real WordPress content. */
function sia_ancf_discovery_llms_content(): string {
    $name = sia_ancf_discovery_markdown_label((string) get_bloginfo('name'));
    $site = esc_url_raw(home_url('/'));
    $lines = ['# ' . ($name !== '' ? $name : 'Website')];
    if (!(bool) get_option('blog_public')) {
        $lines[] = '';
        $lines[] = '> Public search discovery is disabled for this website.';
        return implode("\n", $lines) . "\n";
    }

    $description = sia_ancf_discovery_markdown_label((string) get_bloginfo('description'));
    if ($description !== '') {
        $lines[] = '';
        $lines[] = '> ' . $description;
    }
    $lines[] = '';
    $lines[] = '## Website';
    $lines[] = '- [Home](' . $site . ')';

    $pages = [
        'about-us'                   => 'About Us',
        'contact-us'                 => 'Contact',
        'editorial-policy'           => 'Editorial Policy',
        'fact-checking-policy'       => 'Fact-Checking Policy',
        'correction-policy'          => 'Correction Policy',
        'ai-usage-disclosure-policy' => 'AI Usage Disclosure',
    ];
    foreach ($pages as $slug => $label) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page instanceof WP_Post && sia_ancf_discovery_is_public_post($page)) {
            $lines[] = '- [' . $label . '](' . esc_url_raw(get_permalink($page)) . ')';
        }
    }

    $posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'has_password' => false,
        'posts_per_page' => 16,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => false,
    ]);
    $latest = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post || !sia_ancf_discovery_is_public_post($post)) {
            continue;
        }
        $label = sia_ancf_discovery_markdown_label(get_the_title($post));
        if ($label === '') {
            continue;
        }
        $latest[] = '- [' . $label . '](' . esc_url_raw(get_permalink($post)) . ')';
        if (count($latest) >= 8) {
            break;
        }
    }
    if ($latest) {
        $lines[] = '';
        $lines[] = '## Recent published articles';
        $lines = array_merge($lines, $latest);
    }

    $lines[] = '';
    $lines[] = '## Discovery';
    $lines[] = '- [XML Sitemap](' . esc_url_raw(sia_ancf_discovery_sitemap_url()) . ')';
    $lines[] = '';
    $lines[] = 'For reporting, verify the publication date, sources and corrections on each original article.';
    return implode("\n", $lines) . "\n";
}

/**
 * Handle /llms.txt before WP_Query/template loading. Works with pretty
 * permalinks, without adding a rewrite rule or flushing the rewrite table.
 */
add_action('parse_request', static function (WP $wp): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        return;
    }
    $request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $base_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
    if (!is_string($request_path) || !is_string($base_path) || !str_starts_with($request_path, $base_path)) {
        return;
    }
    if (substr($request_path, strlen($base_path)) !== 'llms.txt') {
        return;
    }
    status_header(200);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    nocache_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    echo sia_ancf_discovery_llms_content();
    exit;
}, 0);
