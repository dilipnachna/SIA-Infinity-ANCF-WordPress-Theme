<?php
/** Conservative metadata fallback when a dedicated SEO provider is absent. */
if (!defined('ABSPATH')) {
    exit;
}

function sia_ancf_native_seo_enabled(): bool {
    $rank = defined('RANK_MATH_VERSION') || class_exists('RankMath');
    // Rank Math can be installed yet decline to boot its frontend integration
    // when onboarding/registration has not been completed or skipped.
    if ($rank && class_exists('RankMath\\Helper') && method_exists('RankMath\\Helper', 'is_invalid_registration')) {
        $rank = !\RankMath\Helper::is_invalid_registration();
    }
    $provider = $rank || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION')
        || defined('SEOPRESS_VERSION') || defined('THE_SEO_FRAMEWORK_VERSION');
    return (bool) apply_filters('sia_ancf_native_seo_enabled', !$provider);
}

/** News is a taxonomy decision: the configured News root and descendants. */
function sia_ancf_news_category_id(): int {
    $selected = absint(get_option('sia_ancf_news_root_category', 0));
    $term = $selected > 0 ? get_term($selected, 'category') : get_category_by_slug('news');
    $fallback = $term instanceof WP_Term && !is_wp_error($term) ? (int) $term->term_id : 0;
    return max(0, (int) apply_filters('sia_ancf_news_category_id', $fallback));
}

function sia_ancf_post_is_news_category(int $post_id): bool {
    if ((bool) get_option('sia_ancf_all_posts_are_news', false)) {
        return get_post_type($post_id) === 'post';
    }
    $root = sia_ancf_news_category_id();
    if ($root <= 0) {
        return false;
    }
    foreach (wp_get_post_categories($post_id) as $term_id) {
        $term_id = (int) $term_id;
        if ($term_id === $root || term_is_ancestor_of($root, $term_id, 'category')) {
            return true;
        }
    }
    return false;
}

/**
 * Read a theme-owned value first, then a compatible Rank Math value after
 * Rank Math has stopped providing frontend output. This keeps existing public
 * metadata intact while an administrator completes the explicit migration.
 */
function sia_ancf_seo_post_value(int $post_id, string $native_key, string $legacy_key = ''): string {
    $value = get_post_meta($post_id, $native_key, true);
    if ((!is_string($value) || $value === '') && $legacy_key !== '' && sia_ancf_native_seo_enabled()) {
        $value = get_post_meta($post_id, $legacy_key, true);
    }
    return is_string($value) ? $value : '';
}

/** Preserve both native and legacy noindex choices after Rank Math removal. */
function sia_ancf_post_is_noindex(int $post_id): bool {
    if (get_post_meta($post_id, '_sia_seo_noindex', true) === '1') {
        return true;
    }
    if (!sia_ancf_native_seo_enabled()) {
        return false;
    }
    $robots = get_post_meta($post_id, 'rank_math_robots', true);
    if (is_string($robots)) {
        $robots = maybe_unserialize($robots);
    }
    return is_array($robots)
        ? in_array('noindex', array_map('strval', $robots), true)
        : is_string($robots) && preg_match('/(?:^|[,\s])noindex(?:$|[,\s])/i', $robots) === 1;
}

/**
 * A taxonomy archive is non-empty only when it can expose at least one
 * published, public and indexable post. Parent categories include descendants.
 */
function sia_ancf_term_has_indexable_posts(WP_Term $term): bool {
    if (!in_array($term->taxonomy, ['category', 'post_tag'], true)) {
        return false;
    }
    static $cache = [];
    $key = $term->taxonomy . ':' . $term->term_id;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $query = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'publish',
        'has_password' => false,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'tax_query' => [[
            'taxonomy' => $term->taxonomy,
            'field' => 'term_id',
            'terms' => [(int) $term->term_id],
            'include_children' => $term->taxonomy === 'category',
        ]],
        'meta_query' => [
            'relation' => 'AND',
            ['relation' => 'OR', ['key' => '_sia_seo_noindex', 'compare' => 'NOT EXISTS'], ['key' => '_sia_seo_noindex', 'value' => '1', 'compare' => '!=']],
            ['relation' => 'OR', ['key' => 'rank_math_robots', 'compare' => 'NOT EXISTS'], ['key' => 'rank_math_robots', 'value' => 'noindex', 'compare' => 'NOT LIKE']],
        ],
    ]);
    $cache[$key] = !empty($query->posts);
    return $cache[$key];
}

/** Expand only the documented subset accepted by the staged importer. */
function sia_ancf_seo_template(string $template, WP_Post $post): string {
    $excerpt = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $values = [
        '%title%' => wp_strip_all_tags($post->post_title),
        '%sitename%' => wp_strip_all_tags(get_bloginfo('name')),
        '%sep%' => sanitize_text_field((string) get_option('sia_seo_title_separator', '–')),
        '%currentdate%' => wp_date(get_option('date_format')),
        '%excerpt%' => wp_strip_all_tags(strip_shortcodes($excerpt)),
    ];
    $text = strtr($template, $values);
    // Unknown placeholders are not made public as if they were valid output.
    return preg_match('/%[a-zA-Z_][a-zA-Z0-9_]*%/', $text) ? '' : trim(wp_strip_all_tags($text, true));
}

/** Reject migrated snapshots as soon as content or current SEO inputs change. */
function sia_ancf_schema_snapshot_fingerprint(WP_Post $post, string $title, string $description): string {
    return hash('sha256', (string) wp_json_encode([
        'post_id' => $post->ID,
        'post_modified_gmt' => $post->post_modified_gmt,
        'content_sha256' => hash('sha256', $post->post_content),
        'title' => $title,
        'description' => $description,
        'day' => wp_date('Y-m-d'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

add_action('add_meta_boxes', static function (): void {
    if (!sia_ancf_native_seo_enabled()) {
        return;
    }
    add_meta_box('sia-native-seo', __('Search appearance', 'sia-ancf-news'), static function (WP_Post $post): void {
        wp_nonce_field('sia_native_seo_save', 'sia_native_seo_nonce');
        $description = sia_ancf_seo_post_value($post->ID, '_sia_seo_description');
        $noindex = sia_ancf_post_is_noindex($post->ID);
        $focus = sia_ancf_seo_post_value($post->ID, '_sia_focus_keywords', 'rank_math_focus_keyword');
        echo '<p><label for="sia-seo-description">' . esc_html__('Search description', 'sia-ancf-news') . '</label></p>';
        echo '<textarea class="widefat" id="sia-seo-description" name="sia_seo_description" rows="3" maxlength="320">' . esc_textarea($description) . '</textarea>';
        echo '<p><label for="sia-focus-keywords">' . esc_html__('Focus keywords (comma separated)', 'sia-ancf-news') . '</label></p>';
        echo '<input class="widefat" id="sia-focus-keywords" name="sia_focus_keywords" maxlength="500" value="' . esc_attr($focus) . '">';
        echo '<p><label><input type="checkbox" name="sia_seo_noindex" value="1" ' . checked($noindex, true, false) . '> ' . esc_html__('Ask search engines not to index this page', 'sia-ancf-news') . '</label></p>';
        if (is_array(get_post_meta($post->ID, '_sia_video_schema', true))) {
            $unavailable = get_post_meta($post->ID, '_sia_video_unavailable', true) === '1';
            echo '<input type="hidden" name="sia_video_control_present" value="1">';
            echo '<p><label><input type="checkbox" name="sia_video_unavailable" value="1" ' . checked($unavailable, true, false) . '> ' . esc_html__('Video unavailable: exclude video from structured data and video sitemap', 'sia-ancf-news') . '</label></p>';
        }

    }, ['post', 'page'], 'side', 'default');
});

add_action('save_post', static function (int $post_id): void {
    if (!sia_ancf_native_seo_enabled() || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)
        || !current_user_can('edit_post', $post_id)
        || !isset($_POST['sia_native_seo_nonce']) || !is_string($_POST['sia_native_seo_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sia_native_seo_nonce'])), 'sia_native_seo_save')) {
        return;
    }
    $description = isset($_POST['sia_seo_description']) && is_string($_POST['sia_seo_description'])
        ? sanitize_text_field(wp_unslash($_POST['sia_seo_description'])) : '';
    $description = wp_html_excerpt($description, 320, '');
    if ($description === '') {
        delete_post_meta($post_id, '_sia_seo_description');
    } else {
        update_post_meta($post_id, '_sia_seo_description', $description);
    }
    $focus = isset($_POST['sia_focus_keywords']) && is_string($_POST['sia_focus_keywords'])
        ? sanitize_text_field(wp_unslash($_POST['sia_focus_keywords'])) : '';
    $focus = implode(', ', array_slice(array_values(array_filter(array_unique(array_map('trim', explode(',', $focus))))), 0, 10));
    if ($focus === '') {
        delete_post_meta($post_id, '_sia_focus_keywords');
    } else {
        update_post_meta($post_id, '_sia_focus_keywords', $focus);
    }
    if (isset($_POST['sia_video_control_present']) && $_POST['sia_video_control_present'] === '1') {
        if (isset($_POST['sia_video_unavailable']) && $_POST['sia_video_unavailable'] === '1') {
            update_post_meta($post_id, '_sia_video_unavailable', '1');
        } else {
            delete_post_meta($post_id, '_sia_video_unavailable');
        }
    }
    if (isset($_POST['sia_seo_noindex']) && $_POST['sia_seo_noindex'] === '1') {
        update_post_meta($post_id, '_sia_seo_noindex', '1');
    } else {
        delete_post_meta($post_id, '_sia_seo_noindex');
    }
});

add_filter('wp_robots', static function (array $robots): array {
    if (sia_ancf_native_seo_enabled() && is_singular() && sia_ancf_post_is_noindex(get_queried_object_id())) {
        $robots['noindex'] = true;
        unset($robots['index']);
    }
    return $robots;
});

add_filter('wp_robots', static function (array $robots): array {
    if (!sia_ancf_native_seo_enabled() || !(is_category() || is_tag())) {
        return $robots;
    }
    $term = get_queried_object();
    if ($term instanceof WP_Term && !sia_ancf_term_has_indexable_posts($term)) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
        unset($robots['index'], $robots['nofollow']);
    }
    return $robots;
}, 20);

add_filter('wp_sitemaps_posts_query_args', static function (array $args): array {
    if (!sia_ancf_native_seo_enabled()) {
        return $args;
    }
    $indexable = [
        'relation' => 'OR',
        ['key' => '_sia_seo_noindex', 'compare' => 'NOT EXISTS'],
        ['key' => '_sia_seo_noindex', 'value' => '1', 'compare' => '!='],
    ];
    $legacy_indexable = [
        'relation' => 'OR',
        ['key' => 'rank_math_robots', 'compare' => 'NOT EXISTS'],
        ['key' => 'rank_math_robots', 'value' => 'noindex', 'compare' => 'NOT LIKE'],
    ];
    $previous = $args['meta_query'] ?? [];
    $args['meta_query'] = $previous
        ? ['relation' => 'AND', $previous, $indexable, $legacy_indexable]
        : ['relation' => 'AND', $indexable, $legacy_indexable];
    return $args;
});

// Keep common legacy sitemap entry points discoverable after a staged SEO
// migration. News and video sitemaps require their own validated data and
// deliberately are not redirected to the generic index.
add_action('template_redirect', static function (): void {
    if (!sia_ancf_native_seo_enabled() || !(bool) get_option('blog_public')
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        return;
    }
    $request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
    $relative = is_string($request_path) && is_string($home_path) && str_starts_with($request_path, $home_path)
        ? substr($request_path, strlen($home_path)) : '';
    if (in_array($relative, ['sitemap_index.xml', 'post-sitemap.xml', 'page-sitemap.xml', 'category-sitemap.xml'], true)) {
        if (wp_safe_redirect(home_url('/wp-sitemap.xml'), 301, 'SIA News')) {
            exit;
        }
    }
}, -1);

add_filter('pre_get_document_title', static function ($title) {
    if (sia_ancf_native_seo_enabled() && get_query_var('sia_latest') !== '1' && is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $template = sia_ancf_seo_post_value($post->ID, '_sia_seo_title_template', 'rank_math_title');
            if (is_string($template) && $template !== '') {
                $resolved = sia_ancf_seo_template($template, $post);
                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }
    }
    if (sia_ancf_native_seo_enabled() && get_query_var('sia_latest') === '1') {
        return sprintf('%s | %s', __('Latest News', 'sia-ancf-news'), get_bloginfo('name'));
    }
    return $title;
});

add_action('wp_head', static function (): void {
    if (!sia_ancf_native_seo_enabled() || is_404() || is_search() || is_feed()) {
        return;
    }
    $latest = get_query_var('sia_latest') === '1';
    $object = get_queried_object();
    if (!$latest && is_singular() && $object instanceof WP_Post && post_password_required($object)) {
        // Never put protected body, excerpt or image into public metadata.
        return;
    }
    $url = '';
    $description = '';
    $is_custom_description = false;
    $image = '';
    if ($latest) {
        $url = sia_ancf_news_posts_index_url();
        $description = get_bloginfo('description');
        $page = max(1, (int) get_query_var('paged'));
        if ($page > 1) {
            $url = add_query_arg('paged', $page, $url);
        }
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    } elseif (is_front_page()) {
        $url = home_url('/');
        $description = get_bloginfo('description');
        if ($object instanceof WP_Post) {
            $custom = sia_ancf_seo_post_value($object->ID, '_sia_seo_description');
            $template = sia_ancf_seo_post_value($object->ID, '_sia_seo_description_template', 'rank_math_description');
            if (is_string($custom) && $custom !== '') {
                $description = $custom;
                $is_custom_description = true;
            } elseif (is_string($template) && $template !== '') {
                $resolved = sia_ancf_seo_template($template, $object);
                if ($resolved !== '') {
                    $description = $resolved;
                    $is_custom_description = true;
                }
            }
        }
    } elseif (is_singular() && $object instanceof WP_Post) {
        $url = get_permalink($object);
        $description = sia_ancf_seo_post_value($object->ID, '_sia_seo_description');
        $is_custom_description = $description !== '';
        if ($description === '') {
            $template = sia_ancf_seo_post_value($object->ID, '_sia_seo_description_template', 'rank_math_description');
            if (is_string($template) && $template !== '') {
                $description = sia_ancf_seo_template($template, $object);
                $is_custom_description = $description !== '';
            }
        }
        if ($description === '') {
            $description = has_excerpt($object) ? $object->post_excerpt : $object->post_content;
        }
        $image = get_the_post_thumbnail_url($object, 'full') ?: '';
    } elseif (is_category() || is_tag() || is_tax()) {
        $term_url = get_term_link($object);
        $url = is_wp_error($term_url) ? '' : $term_url;
        $description = term_description($object);
    } elseif (is_author() && $object instanceof WP_User) {
        $url = get_author_posts_url($object->ID);
        $description = get_the_author_meta('description', $object->ID);
    }
    if (!$latest && (is_category() || is_tag() || is_tax() || is_author()) && $url) {
        if (get_query_var('paged') > 1) {
            $url = get_pagenum_link((int) get_query_var('paged'));
        }
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    }
    $description = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes((string) $description))));
    if (!$is_custom_description) {
        $description = wp_html_excerpt($description, 160, '…');
    }
    if ($description !== '') {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
    if (!$url) {
        return;
    }
    $title = html_entity_decode(wp_get_document_title(), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $is_article = !$latest && !is_front_page() && is_singular('post');
    $social_title = $title;
    $social_image = $image;
    if (!$latest && $object instanceof WP_Post && is_singular()) {
        $social_template = sia_ancf_seo_post_value($object->ID, '_sia_seo_og_title_template', 'rank_math_facebook_title');
        if (is_string($social_template) && $social_template !== '') {
            $social_resolved = sia_ancf_seo_template($social_template, $object);
            if ($social_resolved !== '') {
                $social_title = $social_resolved;
            }
        }
        $social_image_id=(int)get_post_meta($object->ID,'_sia_social_image_id',true);
        if(!$social_image_id){$social_image_id=(int)get_post_meta($object->ID,'rank_math_facebook_image_id',true);}
        if($social_image_id&&wp_attachment_is_image($social_image_id)){$candidate=wp_get_attachment_image_url($social_image_id,'full');if(is_string($candidate)&&$candidate!==''){$social_image=$candidate;}}
    }
    $tags = [
        'og:type' => $is_article ? 'article' : 'website',
        'og:title' => $social_title,
        'og:url' => $url,
        'og:site_name' => get_bloginfo('name'),
        'og:locale' => get_locale(),
    ];
    if ($description !== '') {
        $tags['og:description'] = $description;
    }
    if ($social_image !== '') {
        $tags['og:image'] = $social_image;
    }
    foreach ($tags as $property => $value) {
        echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($value) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="' . ($social_image ? 'summary_large_image' : 'summary') . '">' . "\n";

    $site_id = home_url('/#website');
    $org_id = home_url('/#organization');
    $organization = ['@type' => 'Organization', '@id' => $org_id, 'name' => get_bloginfo('name'), 'url' => home_url('/')];
    $organization = apply_filters('sia_publisher_schema_organization', $organization, $object, $url);
    $website = ['@type' => 'WebSite', '@id' => $site_id, 'name' => get_bloginfo('name'), 'url' => home_url('/'), 'publisher' => ['@id' => $org_id], 'inLanguage' => get_bloginfo('language')];
    $website = apply_filters('sia_publisher_schema_website', $website, $object, $url);
    $graph = [is_array($organization) ? $organization : [], is_array($website) ? $website : []];
    $page_node = ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'isPartOf' => ['@id' => $site_id], 'inLanguage' => get_bloginfo('language')];
    if ($description !== '') {
        $page_node['description'] = $description;
    }
    $page_node = apply_filters('sia_publisher_schema_webpage', $page_node, $object, $url);
    $graph[] = is_array($page_node) ? $page_node : [];
    if ($is_article && $object instanceof WP_Post) {
        $author_id = (int) $object->post_author;
        // NewsArticle is restricted to the configured News category tree.
        // Every other post is Article, regardless of legacy plugin metadata.
        $article_type = sia_ancf_post_is_news_category($object->ID) ? 'NewsArticle' : 'Article';
        $article_headline = html_entity_decode(wp_strip_all_tags(get_the_title($object)), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $article = [
            '@type' => $article_type, '@id' => $url . '#article',
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'headline' => $article_headline, 'datePublished' => get_the_date(DATE_W3C, $object),
            'dateModified' => get_the_modified_date(DATE_W3C, $object),
            'publisher' => ['@id' => $org_id], 'inLanguage' => get_bloginfo('language'),
        ];
        if ($description !== '') {
            $article['description'] = $description;
        }
        // A WordPress author can represent a team; do not invent a person.
        $author_name = get_the_author_meta('display_name', $author_id);
        if ($author_id > 0 && $author_name !== '') {
            $article['author'] = ['name' => $author_name, 'url' => get_author_posts_url($author_id)];
        }
        if ($image) {
            $article['image'] = [$image];
        }
        $article = apply_filters('sia_publisher_schema_article', $article, $object, $url);
        $graph[] = is_array($article) ? $article : [];
        if (function_exists('sia_ancf_sitemap_video')) {
            $video = sia_ancf_sitemap_video($object);
            if ($video) {
                $video_node = [
                    '@type' => 'VideoObject', '@id' => $url . '#video',
                    'name' => wp_strip_all_tags($video['name']),
                    'description' => wp_strip_all_tags($video['description']),
                    'thumbnailUrl' => esc_url_raw($video['thumbnailUrl']),
                    'uploadDate' => $video['uploadDate'],
                    'embedUrl' => esc_url_raw($video['embedUrl']),
                    'isPartOf' => ['@id' => $url . '#webpage'],
                ];
                if (isset($video['duration']) && is_string($video['duration']) && preg_match('/\APT(?:(?:\d+)H)?(?:(?:\d+)M)?(?:(?:\d+)S)?\z/', $video['duration']) && $video['duration'] !== 'PT0M0S') {
                    $video_node['duration'] = $video['duration'];
                }
                $graph[] = $video_node;
            }
        }
    }
    if (!$latest && is_singular() && $object instanceof WP_Post && function_exists('sia_ancf_faq_schema')) {
        $faq = sia_ancf_faq_schema($object, $url);
        if ($faq) { $graph[] = $faq; }
    }
    $graph = apply_filters('sia_publisher_schema_graph', array_values(array_filter($graph, 'is_array')), $object, $url);
    $graph = is_array($graph) ? array_values(array_filter($graph, 'is_array')) : [];
    $json = wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json !== false) {
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is safely encoded above.
    }
}, 5);
