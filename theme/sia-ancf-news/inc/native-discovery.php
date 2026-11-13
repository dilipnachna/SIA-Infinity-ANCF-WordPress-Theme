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

/**
 * Find the hierarchical category path and root without relying on fixed slugs.
 * A new child category is discovered automatically with no theme changes.
 *
 * @return list<WP_Term>
 */
function sia_ancf_discovery_term_path(WP_Term $term, array $by_id): array {
    $path = [];
    $seen = [];
    $current = $term;
    while ($current instanceof WP_Term && !isset($seen[$current->term_id])) {
        $seen[$current->term_id] = true;
        array_unshift($path, $current);
        $parent = (int) $current->parent;
        $current = $parent > 0 ? ($by_id[$parent] ?? null) : null;
    }
    return $path;
}

/**
 * The llms.txt directory includes *all* public, indexable posts across every
 * top-level category and its descendants, grouped by the category hierarchy.
 * No latest-posts limit: newly published posts and child categories appear
 * automatically. The XML sitemap remains the canonical complete URL index.
 */
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

    $terms = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ]);
    $by_id = [];
    if (is_array($terms)) {
        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $by_id[(int) $term->term_id] = $term;
            }
        }
    }
    $paths = [];
    foreach ($by_id as $term_id => $term) {
        $path = sia_ancf_discovery_term_path($term, $by_id);
        if (!$path || $path[0]->slug === 'uncategorized') {
            continue;
        }
        $paths[$term_id] = $path;
    }

    // Read every published post in bounded batches: don't silently omit older
    // posts or leak password-protected or noindex posts.
    $grouped = [];
    $batch_size = 200;
    $page_number = 1;
    do {
        $posts = get_posts([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'has_password'       => false,
            'posts_per_page'     => $batch_size,
            'paged'              => $page_number,
            'orderby'            => 'date',
            'order'              => 'DESC',
            'ignore_sticky_posts'=> true,
            'suppress_filters'   => false,
        ]);
        if (!is_array($posts) || !$posts) {
            break;
        }
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post || !sia_ancf_discovery_is_public_post($post)) {
                continue;
            }
            $label = sia_ancf_discovery_markdown_label(get_the_title($post));
            $url = esc_url_raw(get_permalink($post));
            if ($label === '' || $url === '') {
                continue;
            }

            // Choose the most specific assigned category within each root.
            // Cross-posted stories may appear under both News and Articles.
            $selected = [];
            foreach (wp_get_post_categories((int) $post->ID) as $term_id) {
                $term_id = (int) $term_id;
                if (!isset($paths[$term_id])) {
                    continue;
                }
                $path = $paths[$term_id];
                $root_id = (int) $path[0]->term_id;
                if (!isset($selected[$root_id]) || count($path) > count($paths[$selected[$root_id]])) {
                    $selected[$root_id] = $term_id;
                }
            }
            foreach ($selected as $term_id) {
                $grouped[$term_id][] = '- [' . $label . '](' . $url . ')';
            }
        }
        $page_number++;
    } while (count($posts) === $batch_size);

    // Display top-level sections and each child category, even when a
    // category has no posts yet. The user can navigate the full taxonomy.
    uasort($paths, static function (array $a, array $b): int {
        $names_a = array_map(static fn(WP_Term $term): string => $term->name, $a);
        $names_b = array_map(static fn(WP_Term $term): string => $term->name, $b);
        return strcasecmp(implode(' / ', $names_a), implode(' / ', $names_b));
    });
    foreach ($paths as $term_id => $path) {
        $term = $by_id[$term_id];
        $heading = implode(' / ', array_map(
            static fn(WP_Term $item): string => sia_ancf_discovery_markdown_label($item->name),
            $path
        ));
        $term_url = get_term_link($term);
        $lines[] = '';
        $lines[] = '## ' . $heading;
        if (is_string($term_url) && $term_url !== '') {
            $lines[] = '- [' . sia_ancf_discovery_markdown_label($term->name) . '](' . esc_url_raw($term_url) . ')';
        }
        if (!empty($grouped[$term_id])) {
            $lines = array_merge($lines, $grouped[$term_id]);
        }
    }

    $lines[] = '';
    $lines[] = '## Discovery';
    $lines[] = '- [XML Sitemap](' . esc_url_raw(sia_ancf_discovery_sitemap_url()) . ')';
    $lines[] = '';
    $lines[] = '## Optional';
    $lines[] = '- [Full article text](' . esc_url_raw(home_url('/llms-full.txt')) . '): Complete public News and Articles content in Markdown.';
    $lines[] = '';
    $lines[] = 'For reporting, verify the publication date, sources and corrections on each original article.';
    return implode("\n", $lines) . "\n";
}


/**
 * Convert post body HTML to readable, safe Markdown. This is a direct
 * representation of the WordPress post body, not a generated summary.
 */
function sia_ancf_discovery_markdown_node(DOMNode $node): string {
    if ($node instanceof DOMText) {
        return (string) $node->nodeValue;
    }
    if (!$node instanceof DOMElement) {
        return '';
    }
    $tag = strtolower($node->tagName);
    if (in_array($tag, ['script', 'style', 'noscript', 'iframe', 'form', 'svg', 'nav', 'footer', 'header', 'button', 'input'], true)) {
        return '';
    }
    if ($tag === 'br') {
        return "\n";
    }
    if ($tag === 'img') {
        // Images do not have a text body; avoid duplicating responsive image URLs.
        $alt = sia_ancf_discovery_markdown_label($node->getAttribute('alt'));
        return $alt !== '' ? ' [Image: ' . $alt . '] ' : '';
    }
    $inner = '';
    foreach ($node->childNodes as $child) {
        $inner .= sia_ancf_discovery_markdown_node($child);
    }
    if ($tag === 'a') {
        $href = (string) $node->getAttribute('href');
        $url = wp_parse_url($href);
        if (is_array($url) && in_array(strtolower((string) ($url['scheme'] ?? '')), ['https', 'http'], true)
            && !empty($url['host'])) {
            $label = sia_ancf_discovery_markdown_label(trim($inner));
            return $label !== '' ? '[' . $label . '](' . esc_url_raw($href) . ')' : '';
        }
        return $inner;
    }
    if ($tag === 'li') {
        return "\n- " . trim($inner) . "\n";
    }
    if (in_array($tag, ['ul', 'ol'], true)) {
        return "\n" . trim($inner) . "\n\n";
    }
    if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
        return "\n\n### " . trim($inner) . "\n\n";
    }
    if (in_array($tag, ['strong', 'b'], true)) {
        return '**' . trim($inner) . '**';
    }
    if (in_array($tag, ['em', 'i'], true)) {
        return '*' . trim($inner) . '*';
    }
    if ($tag === 'blockquote') {
        return "\n\n> " . trim($inner) . "\n\n";
    }
    if ($tag === 'tr') {
        return trim($inner) . "\n";
    }
    if (in_array($tag, ['th', 'td'], true)) {
        return trim($inner) . ' | ';
    }
    if (in_array($tag, ['p', 'div', 'section', 'article', 'figure', 'figcaption', 'pre', 'table'], true)) {
        return "\n\n" . trim($inner) . "\n\n";
    }
    return $inner;
}

function sia_ancf_discovery_post_markdown(string $html): string {
    $html = strip_shortcodes($html);
    $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        if (!$loaded) {
            return trim(wp_strip_all_tags($html));
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $output = '';
        if ($body instanceof DOMNode) {
            foreach ($body->childNodes as $node) {
                $output .= sia_ancf_discovery_markdown_node($node);
            }
        }
        $output = (string) preg_replace('/[ \t]+\n/u', "\n", $output);
        $output = (string) preg_replace('/\n{3,}/u', "\n\n", $output);
        return trim($output);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/**
 * The optional llms-full.txt convention combines every public News/Articles
 * post body. Stream in batches so publication growth never silently truncates.
 * One post appears once even when assigned to multiple categories.
 */
function sia_ancf_discovery_full_markdown(): void {
    $name = sia_ancf_discovery_markdown_label((string) get_bloginfo('name'));
    echo '# ' . ($name !== '' ? $name : 'Website') . " — Full public articles\n\n";
    if (!(bool) get_option('blog_public')) {
        echo "> Public search discovery is disabled for this website.\n";
        return;
    }
    echo '> Source: ' . esc_url_raw(home_url('/')) . "\n";
    echo '> Directory: ' . esc_url_raw(home_url('/llms.txt')) . "\n\n";

    // Use all root and child categories except the WordPress catch-all.
    $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
    $by_id = [];
    if (is_array($terms)) {
        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $by_id[(int) $term->term_id] = $term;
            }
        }
    }
    $paths = [];
    foreach ($by_id as $id => $term) {
        $path = sia_ancf_discovery_term_path($term, $by_id);
        if ($path && $path[0]->slug !== 'uncategorized') {
            $paths[$id] = $path;
        }
    }

    $batch_size = 100;
    $page_number = 1;
    do {
        $posts = get_posts([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'has_password'       => false,
            'posts_per_page'     => $batch_size,
            'paged'              => $page_number,
            'orderby'            => 'date',
            'order'              => 'DESC',
            'ignore_sticky_posts'=> true,
            'suppress_filters'   => false,
        ]);
        if (!is_array($posts) || !$posts) {
            break;
        }
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post || !sia_ancf_discovery_is_public_post($post)) {
                continue;
            }
            $selected = [];
            foreach (wp_get_post_categories((int) $post->ID) as $term_id) {
                if (isset($paths[$term_id])) {
                    $selected[] = $paths[$term_id];
                }
            }
            if (!$selected) {
                continue;
            }
            $title = sia_ancf_discovery_markdown_label(get_the_title($post));
            $body = sia_ancf_discovery_post_markdown((string) $post->post_content);
            if ($title === '' || $body === '') {
                continue;
            }
            $categories = [];
            foreach ($selected as $path) {
                $categories[] = implode(' / ', array_map(
                    static fn(WP_Term $term): string => sia_ancf_discovery_markdown_label($term->name),
                    $path
                ));
            }
            $categories = array_values(array_unique($categories));
            echo "## " . $title . "\n\n";
            echo '- URL: ' . esc_url_raw(get_permalink($post)) . "\n";
            echo '- Category: ' . implode(', ', $categories) . "\n";
            echo '- Published: ' . get_post_time('c', true, $post) . "\n";
            echo '- Updated: ' . get_post_modified_time('c', true, $post) . "\n\n";
            echo $body . "\n\n---\n\n";
        }
        $page_number++;
    } while (count($posts) === $batch_size);
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
    $filename = substr($request_path, strlen($base_path));
    if (!in_array($filename, ['llms.txt', 'llms-full.txt'], true)) {
        return;
    }
    status_header(200);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    nocache_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    if ($filename === 'llms-full.txt') {
        sia_ancf_discovery_full_markdown();
    } else {
        echo sia_ancf_discovery_llms_content();
    }
    exit;
}, 0);
