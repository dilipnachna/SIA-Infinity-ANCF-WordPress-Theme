<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Rank_Smart {
    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register_admin_page']);
        add_action('add_meta_boxes', [self::class, 'register_meta_boxes']);
        add_filter('sia_publisher_integration_status', [self::class, 'publisher_integration_status'], 20, 2);
        add_filter('sia_publisher_intelligence_context', [self::class, 'publisher_context'], 20, 2);
    }

    public static function register_admin_page(): void {
        add_management_page(
            'Rank Smart',
            'Rank Smart',
            'manage_options',
            'rank-smart',
            [self::class, 'render_admin_page']
        );
    }

    public static function register_meta_boxes(string $post_type): void {
        if (!in_array($post_type, ['post', 'page'], true)) {
            return;
        }

        add_meta_box(
            'rank-smart-readonly-audit',
            'Rank Smart — Read-only SEO Intelligence',
            [self::class, 'render_meta_box'],
            $post_type,
            'normal',
            'high'
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $authority = self::detect_existing_seo_authority();
        $published_posts = wp_count_posts('post');
        $published_pages = wp_count_posts('page');

        echo '<div class="wrap"><h1>Rank Smart</h1>';
        echo '<p><strong>Version:</strong> ' . esc_html(RANK_SMART_VERSION) . '</p>';
        echo '<p><strong>Mode:</strong> ' . esc_html(RANK_SMART_MODE) . '</p>';
        echo '<p><strong>Observed SEO authority:</strong> ' . esc_html($authority) . '</p>';
        echo '<p>Rank Smart v0.3 audits and combines evidence. It does not emit canonical tags, robots directives, redirects, schema, sitemaps, titles or meta descriptions.</p>';
        echo '<h2>Current local scope</h2><ul>';
        echo '<li>Published posts: ' . esc_html((string) ($published_posts->publish ?? 0)) . '</li>';
        echo '<li>Published pages: ' . esc_html((string) ($published_pages->publish ?? 0)) . '</li>';
        echo '<li>URL history: ' . (class_exists('SIA_URL_Memory') ? 'available' : 'not connected') . '</li>';
        echo '<li>Search Console provider: ' . esc_html(self::provider_label('search_console')) . '</li>';
        echo '<li>Analytics provider: ' . esc_html(self::provider_label('analytics')) . '</li>';
        echo '<li>AdSense provider: ' . esc_html(self::provider_label('adsense')) . '</li>';
        echo '<li>Backlink provider: ' . esc_html(self::provider_label('backlinks')) . '</li>';
        echo '</ul>';
        echo '<p><em>External connectors are provider contracts in v0.3. OAuth/API ingestion is not enabled yet.</em></p></div>';
    }

    public static function render_meta_box(WP_Post $post): void {
        $audit = self::audit_post($post);
        $risk = $audit['change_risk'];

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">';
        self::render_card('URL / Identity', [
            'Current URL' => $audit['url'] ?: 'Unavailable',
            'Canonical candidate' => $audit['canonical_candidate'] ?: 'Unavailable',
            'Post status' => $post->post_status,
            'Index candidate' => $audit['index_candidate'] ? 'Public candidate' : 'Review',
        ]);
        self::render_card('Content Signals', [
            'Title characters' => (string) $audit['title_chars'],
            'Slug characters' => (string) $audit['slug_chars'],
            'Latin-only slug' => $audit['latin_slug'] ? 'Yes' : 'No',
            'Excerpt present' => $audit['excerpt_present'] ? 'Yes' : 'No',
            'Featured image' => $audit['featured_image_label'],
            'Primary silo' => $audit['primary_silo'],
            'Author' => $audit['author'],
        ]);
        self::render_card('URL Memory', [
            'Observations' => (string) $audit['url_history']['count'],
            'Slug changes' => (string) $audit['url_history']['slug_changes'],
            'Last event' => $audit['url_history']['last_event'],
            'Change risk' => $risk['level'] . ' (' . $risk['score'] . '/100)',
        ]);
        self::render_card('External Evidence', [
            'Search Console' => self::evidence_label($audit['search']),
            'Analytics' => self::evidence_label($audit['analytics']),
            'AdSense' => self::evidence_label($audit['revenue']),
            'Backlinks' => self::evidence_label($audit['backlinks']),
        ]);
        echo '</div>';

        if (!empty($risk['reasons'])) {
            echo '<p><strong>Change-risk evidence:</strong> ' . esc_html(implode(' · ', $risk['reasons'])) . '</p>';
        }
        echo '<p><em>Read-only. Rank Smart does not change this URL or current SEO output.</em></p>';
    }

    private static function render_card(string $title, array $rows): void {
        echo '<section style="border:1px solid #dcdcde;background:#fff;padding:12px;border-radius:6px"><h3 style="margin-top:0">' . esc_html($title) . '</h3>';
        foreach ($rows as $label => $value) {
            echo '<div style="display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #f0f0f1;padding:4px 0"><span style="color:#646970">' . esc_html((string) $label) . '</span><strong style="text-align:right;word-break:break-word">' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</section>';
    }

    public static function audit_post(WP_Post $post): array {
        $url = get_permalink($post->ID);
        $canonical = function_exists('wp_get_canonical_url') ? wp_get_canonical_url($post) : $url;
        $title = get_the_title($post);
        $slug = (string) $post->post_name;
        $author = get_userdata((int) $post->post_author);
        $primary_silo = self::primary_silo_label($post->ID);
        $image = self::featured_image_context($post->ID);
        $url_history = self::url_history($post->ID);

        $search = self::provider_evidence('search_console', $post);
        $analytics = self::provider_evidence('analytics', $post);
        $revenue = self::provider_evidence('adsense', $post);
        $backlinks = self::provider_evidence('backlinks', $post);

        $audit = [
            'post_id' => $post->ID,
            'url' => is_string($url) ? $url : '',
            'canonical_candidate' => is_string($canonical) ? $canonical : '',
            'index_candidate' => self::is_public_index_candidate($post),
            'title_chars' => self::text_length($title),
            'slug_chars' => self::text_length($slug),
            'latin_slug' => $slug === '' || preg_match('/^[\x20-\x7E]+$/', $slug) === 1,
            'excerpt_present' => trim((string) $post->post_excerpt) !== '',
            'featured_image_label' => $image['label'],
            'featured_image_width' => $image['width'],
            'primary_silo' => $primary_silo,
            'author' => $author ? $author->display_name : 'Not set',
            'url_history' => $url_history,
            'search' => $search,
            'analytics' => $analytics,
            'revenue' => $revenue,
            'backlinks' => $backlinks,
            'seo_authority' => self::detect_existing_seo_authority(),
        ];

        $audit['change_risk'] = self::calculate_change_risk($post, $audit);

        return apply_filters('rank_smart_post_audit', $audit, $post);
    }

    public static function publisher_integration_status(array $integrations, WP_Post $post): array {
        $integrations['rank_smart'] = [
            'active' => true,
            'label' => 'Read-only connected',
            'version' => RANK_SMART_VERSION,
        ];
        return $integrations;
    }

    public static function publisher_context(array $context, WP_Post $post): array {
        $context['rank_smart'] = self::audit_post($post);
        return $context;
    }

    private static function primary_silo_label(int $post_id): string {
        $term_id = (int) get_post_meta($post_id, '_sia_primary_silo_term_id', true);
        if ($term_id <= 0) {
            return 'Not set';
        }
        $term = get_term($term_id, 'category');
        return ($term && !is_wp_error($term)) ? $term->name : 'Not set';
    }

    private static function featured_image_context(int $post_id): array {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return ['width' => 0, 'label' => 'Not set'];
        }
        $meta = wp_get_attachment_metadata($thumbnail_id);
        $width = is_array($meta) && isset($meta['width']) ? (int) $meta['width'] : 0;
        if ($width <= 0) {
            return ['width' => 0, 'label' => 'Present'];
        }
        return ['width' => $width, 'label' => $width . 'px wide'];
    }

    private static function is_public_index_candidate(WP_Post $post): bool {
        if ((int) get_option('blog_public', 1) !== 1) {
            return false;
        }
        if ($post->post_status !== 'publish' || post_password_required($post)) {
            return false;
        }
        $object = get_post_type_object($post->post_type);
        return (bool) ($object && $object->publicly_queryable);
    }

    private static function url_history(int $post_id): array {
        $empty = ['available' => false, 'count' => 0, 'slug_changes' => 0, 'last_event' => 'Unavailable'];
        if (!class_exists('SIA_URL_Memory')) {
            return $empty;
        }

        global $wpdb;
        $table = SIA_URL_Memory::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return $empty;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id));
        $slug_changes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND event_type = 'slug_change'", $post_id));
        $last = $wpdb->get_var($wpdb->prepare("SELECT event_type FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 1", $post_id));

        return [
            'available' => true,
            'count' => $count,
            'slug_changes' => $slug_changes,
            'last_event' => $last ? (string) $last : 'None',
        ];
    }

    private static function provider_evidence(string $provider, WP_Post $post): array {
        $evidence = [
            'provider' => $provider,
            'connected' => false,
            'observed_at_gmt' => null,
            'metrics' => [],
        ];

        return apply_filters('rank_smart_provider_evidence', $evidence, $provider, $post);
    }

    private static function provider_label(string $provider): string {
        $state = [
            'provider' => $provider,
            'connected' => false,
            'label' => 'Provider-ready / not connected',
        ];
        $state = apply_filters('rank_smart_provider_state', $state, $provider);
        return !empty($state['connected']) ? (string) ($state['label'] ?? 'Connected') : (string) ($state['label'] ?? 'Not connected');
    }

    private static function evidence_label(array $evidence): string {
        if (empty($evidence['connected'])) {
            return 'Not connected';
        }
        if (empty($evidence['metrics'])) {
            return 'Connected / no evidence';
        }
        return 'Evidence available';
    }

    private static function calculate_change_risk(WP_Post $post, array $audit): array {
        $score = 0;
        $reasons = [];

        if ($post->post_status === 'publish') {
            $score += 10;
            $reasons[] = 'published URL';
        }
        if ($audit['url_history']['count'] > 1) {
            $score += 15;
            $reasons[] = 'URL history exists';
        }
        if ($audit['url_history']['slug_changes'] > 0) {
            $score += 20;
            $reasons[] = 'previous slug change recorded';
        }

        foreach (['search' => 'search evidence', 'analytics' => 'analytics evidence', 'revenue' => 'revenue evidence', 'backlinks' => 'backlink evidence'] as $key => $label) {
            if (self::has_positive_metric($audit[$key])) {
                $score += 20;
                $reasons[] = $label;
            }
        }

        $score = min(100, $score);
        $level = $score >= 60 ? 'HIGH' : ($score >= 30 ? 'MEDIUM' : 'LOW');

        return [
            'score' => $score,
            'level' => $level,
            'reasons' => $reasons,
            'meaning' => 'Evidence-based risk of changing/removing this URL; not a ranking score.',
        ];
    }

    private static function has_positive_metric(array $evidence): bool {
        if (empty($evidence['connected']) || empty($evidence['metrics']) || !is_array($evidence['metrics'])) {
            return false;
        }
        foreach ($evidence['metrics'] as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return true;
            }
        }
        return false;
    }

    private static function detect_existing_seo_authority(): string {
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            return 'Rank Math observed';
        }
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            return 'Yoast SEO observed';
        }
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO')) {
            return 'All in One SEO observed';
        }
        return 'WordPress / none detected';
    }

    private static function text_length(string $text): int {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
