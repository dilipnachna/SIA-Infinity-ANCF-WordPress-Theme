<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SIA_Publisher_Intelligence {
    public static function boot(): void {
        add_action('add_meta_boxes_post', [self::class, 'add_meta_box'], 40);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'sia-publisher-intelligence',
            'SIA Publisher Intelligence',
            [self::class, 'render_meta_box'],
            'post',
            'normal',
            'high'
        );
    }

    public static function admin_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $css = '.sia-pi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}'
            . '.sia-pi-card{border:1px solid #dcdcde;border-radius:6px;background:#fff;padding:14px}'
            . '.sia-pi-card h3{margin:0 0 10px;font-size:14px}'
            . '.sia-pi-row{display:flex;justify-content:space-between;gap:12px;padding:5px 0;border-bottom:1px solid #f0f0f1}'
            . '.sia-pi-row:last-child{border-bottom:0}'
            . '.sia-pi-label{color:#646970}'
            . '.sia-pi-value{text-align:right;font-weight:600;word-break:break-word}'
            . '.sia-pi-status{display:inline-block;padding:2px 7px;border-radius:999px;background:#f0f0f1;font-size:11px;font-weight:700}'
            . '.sia-pi-ok{background:#edfaef;color:#116329}.sia-pi-warn{background:#fff8e5;color:#6c4a00}.sia-pi-off{background:#f0f0f1;color:#50575e}'
            . '.sia-pi-note{margin:12px 0 0;color:#646970;font-size:12px}';

        wp_add_inline_style('common', $css);
    }

    public static function render_meta_box(WP_Post $post): void {
        $context = self::build_context($post);
        $context = apply_filters('sia_publisher_intelligence_context', $context, $post);

        echo '<div class="sia-pi-grid">';
        self::render_content_card($context);
        self::render_homepage_card($context);
        self::render_url_card($context);
        self::render_rank_smart_card($context);
        self::render_ai_card($context);
        self::render_discover_card($context);
        self::render_monetization_card($context);
        echo '</div>';

        echo '<p class="sia-pi-note">Homepage authority is presentation-only. It does not change canonical tags, robots, redirects, schema, sitemaps, search indexing, AI publishing, or paid-link authority.</p>';
        do_action('sia_publisher_intelligence_after_sections', $post, $context);
    }

    private static function build_context(WP_Post $post): array {
        $primary_silo_id = (int) get_post_meta($post->ID, '_sia_primary_silo_term_id', true);
        $primary_silo = $primary_silo_id ? get_term($primary_silo_id, 'category') : null;
        $author = get_userdata((int) $post->post_author);
        $url_memory = self::url_memory_context($post->ID);
        $discover = self::discover_context($post, $primary_silo_id, $author);
        $home_eligible_raw = get_post_meta($post->ID, '_sia_home_eligible', true);
        $home_eligible = $home_eligible_raw === '' ? true : (bool) $home_eligible_raw;
        $home_exclude = (bool) get_post_meta($post->ID, '_sia_home_exclude', true);
        $home_placement = (string) (get_post_meta($post->ID, '_sia_home_placement', true) ?: 'normal');
        $excluded_by_category = false;
        $excluded_categories = function_exists('sia_ancf_news_home_excluded_category_ids') ? sia_ancf_news_home_excluded_category_ids() : [];
        if ($excluded_categories) {
            $post_categories = wp_get_post_categories($post->ID, ['fields' => 'ids']);
            $excluded_by_category = (bool) array_intersect(array_map('intval', $post_categories), $excluded_categories);
        }

        $integrations = [
            'rank_smart' => self::integration_status('rank_smart'),
            'sia_ai' => self::integration_status('sia_ai'),
            'brand_studio' => self::integration_status('brand_studio'),
        ];
        $integrations = apply_filters('sia_publisher_integration_status', $integrations, $post);

        return [
            'content_type' => (string) (get_post_meta($post->ID, '_sia_content_type', true) ?: 'news'),
            'editorial_state' => (string) (get_post_meta($post->ID, '_sia_editorial_state', true) ?: 'draft'),
            'ai_assistance' => (string) (get_post_meta($post->ID, '_sia_ai_assistance', true) ?: 'none'),
            'commercial_type' => (string) (get_post_meta($post->ID, '_sia_commercial_type', true) ?: 'editorial'),
            'primary_silo' => ($primary_silo && !is_wp_error($primary_silo)) ? $primary_silo->name : 'Not set',
            'author' => $author ? $author->display_name : 'Not set',
            'url' => get_permalink($post->ID) ?: '',
            'url_memory' => $url_memory,
            'discover' => $discover,
            'homepage' => [
                'eligible' => $home_eligible,
                'excluded' => $home_exclude,
                'excluded_by_category' => $excluded_by_category,
                'placement' => $home_placement,
                'effective' => $home_eligible && !$home_exclude && !$excluded_by_category,
            ],
            'integrations' => $integrations,
        ];
    }

    private static function integration_status(string $integration): array {
        $active = false;
        $label = 'Not connected';

        if ($integration === 'rank_smart') {
            $active = defined('RANK_SMART_VERSION') || class_exists('Rank_Smart') || function_exists('rank_smart');
        } elseif ($integration === 'sia_ai') {
            $active = defined('SIA_AI_VERSION') || class_exists('SIA_AI_Client') || function_exists('sia_ai');
        } elseif ($integration === 'brand_studio') {
            $active = defined('SIA_BRAND_STUDIO_VERSION') || class_exists('SIA_Brand_Studio');
        }

        if ($active) {
            $label = 'Connected';
        }

        return ['active' => $active, 'label' => $label];
    }

    private static function url_memory_context(int $post_id): array {
        if (!class_exists('SIA_URL_Memory')) {
            return ['active' => false, 'count' => 0, 'last_event' => 'Unavailable', 'risk' => 'Unknown'];
        }

        global $wpdb;
        $table = SIA_URL_Memory::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return ['active' => false, 'count' => 0, 'last_event' => 'No table', 'risk' => 'Unknown'];
        }

        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id));
        $last = $wpdb->get_row($wpdb->prepare("SELECT event_type FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 1", $post_id));

        $risk = $count > 1 ? 'History found' : ($count === 1 ? 'Observed' : 'No history yet');

        return [
            'active' => true,
            'count' => $count,
            'last_event' => $last ? (string) $last->event_type : 'None',
            'risk' => $risk,
        ];
    }

    private static function discover_context(WP_Post $post, int $primary_silo_id, $author): array {
        $image_ok = false;
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $meta = wp_get_attachment_metadata($thumbnail_id);
            $width = isset($meta['width']) ? (int) $meta['width'] : 0;
            $image_ok = $width >= 1200;
        }

        return [
            'large_image' => $image_ok,
            'author' => (bool) $author,
            'primary_silo' => $primary_silo_id > 0,
            'published' => $post->post_status === 'publish',
        ];
    }

    private static function render_content_card(array $context): void {
        echo '<section class="sia-pi-card"><h3>Content</h3>';
        self::row('Primary Silo', $context['primary_silo']);
        self::row('Author', $context['author']);
        self::row('Content Type', self::humanize($context['content_type']));
        self::row('Editorial State', self::humanize($context['editorial_state']));
        echo '</section>';
    }

    private static function render_homepage_card(array $context): void {
        $home = $context['homepage'];
        echo '<section class="sia-pi-card"><h3>Homepage Authority</h3>';
        self::status_row('Effective', ['active' => (bool) $home['effective'], 'label' => $home['effective'] ? 'Eligible' : 'Excluded']);
        self::row('Placement', self::humanize((string) $home['placement']));
        self::row('Post Control', $home['excluded'] ? 'Excluded' : ($home['eligible'] ? 'Eligible' : 'Not eligible'));
        self::row('Category Rule', $home['excluded_by_category'] ? 'Excluded category' : 'Allowed');
        echo '</section>';
    }

    private static function render_url_card(array $context): void {
        $memory = $context['url_memory'];
        echo '<section class="sia-pi-card"><h3>URL Memory</h3>';
        self::row('Status', $memory['active'] ? 'Active' : 'Unavailable');
        self::row('Observations', (string) $memory['count']);
        self::row('Last Event', self::humanize($memory['last_event']));
        self::row('Signal', $memory['risk']);
        echo '</section>';
    }

    private static function render_rank_smart_card(array $context): void {
        $status = $context['integrations']['rank_smart'];
        echo '<section class="sia-pi-card"><h3>Rank Smart</h3>';
        self::status_row('SEO Engine', $status);
        self::row('Search Data', $status['active'] ? 'Provider controlled' : 'Not connected');
        self::row('SEO Authority', $status['active'] ? 'External module' : 'Existing SEO plugin');
        echo '</section>';
    }

    private static function render_ai_card(array $context): void {
        $status = $context['integrations']['sia_ai'];
        echo '<section class="sia-pi-card"><h3>English / SIA AI</h3>';
        self::status_row('SIA AI', $status);
        self::row('Assistance', self::humanize($context['ai_assistance']));
        self::row('English Draft', $status['active'] ? 'Provider available' : 'Not connected');
        echo '</section>';
    }

    private static function render_discover_card(array $context): void {
        $discover = $context['discover'];
        echo '<section class="sia-pi-card"><h3>Discover Readiness</h3>';
        self::check_row('1200px+ image', $discover['large_image']);
        self::check_row('Author identity', $discover['author']);
        self::check_row('Primary silo', $discover['primary_silo']);
        self::check_row('Published', $discover['published']);
        echo '</section>';
    }

    private static function render_monetization_card(array $context): void {
        $brand = $context['integrations']['brand_studio'];
        echo '<section class="sia-pi-card"><h3>Monetization</h3>';
        self::row('Content Mode', self::humanize($context['commercial_type']));
        self::status_row('Brand Studio', $brand);
        self::row('Commercial Value', $brand['active'] ? 'Provider controlled' : 'Not scored');
        echo '</section>';
    }

    private static function row(string $label, string $value): void {
        echo '<div class="sia-pi-row"><span class="sia-pi-label">' . esc_html($label) . '</span><span class="sia-pi-value">' . esc_html($value) . '</span></div>';
    }

    private static function status_row(string $label, array $status): void {
        $class = !empty($status['active']) ? 'sia-pi-ok' : 'sia-pi-off';
        echo '<div class="sia-pi-row"><span class="sia-pi-label">' . esc_html($label) . '</span><span class="sia-pi-status ' . esc_attr($class) . '">' . esc_html((string) $status['label']) . '</span></div>';
    }

    private static function check_row(string $label, bool $ok): void {
        $status = $ok ? ['active' => true, 'label' => 'Ready'] : ['active' => false, 'label' => 'Review'];
        self::status_row($label, $status);
    }

    private static function humanize(string $value): string {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }
}
