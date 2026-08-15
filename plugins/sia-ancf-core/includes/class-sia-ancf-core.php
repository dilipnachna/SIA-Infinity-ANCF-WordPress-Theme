<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SIA_ANCF_Core {
    private const CONTENT_TYPES = [
        'news', 'breaking', 'explainer', 'analysis', 'opinion', 'guide', 'live', 'sponsored',
    ];

    private const EDITORIAL_STATES = [
        'idea', 'draft', 'research', 'verify', 'edit', 'approve', 'publish', 'monitor', 'update', 'correct', 'archive',
    ];

    private const COMMERCIAL_TYPES = [
        'editorial',
        'sponsored_article',
        'guest_contribution',
        'brand_story',
        'existing_content_sponsorship',
    ];

    private const HOME_PLACEMENTS = [
        'normal',
        'lead',
        'top',
        'breaking',
        'section_featured',
    ];

    public static function boot(): void {
        add_action('init', [self::class, 'register_meta']);
        add_action('admin_menu', [self::class, 'register_admin_page']);
        add_action('admin_post_sia_ancf_save_homepage_settings', [self::class, 'save_homepage_settings']);
        add_action('add_meta_boxes_post', [self::class, 'register_story_meta_box']);
        add_action('save_post_post', [self::class, 'save_story_meta'], 10, 2);
    }

    public static function register_meta(): void {
        register_post_meta('post', '_sia_content_type', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_content_type'],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_post_meta('post', '_sia_editorial_state', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_editorial_state'],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_post_meta('post', '_sia_ai_assistance', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_ai_assistance'],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_post_meta('post', '_sia_commercial_type', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_commercial_type'],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_post_meta('post', '_sia_home_eligible', [
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => false,
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_post_meta('post', '_sia_home_exclude', [
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => false,
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_post_meta('post', '_sia_home_placement', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_home_placement'],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);
    }

    public static function register_admin_page(): void {
        add_management_page(
            'SIA ANCF',
            'SIA ANCF',
            'manage_options',
            'sia-ancf',
            [self::class, 'render_admin_page']
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $excluded = self::homepage_excluded_categories();
        $categories = get_categories([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        echo '<div class="wrap"><h1>SIA Infinity ANCF</h1>';
        echo '<p><strong>Version:</strong> ' . esc_html(SIA_ANCF_CORE_VERSION) . '</p>';
        echo '<p><strong>Runtime mode:</strong> ' . esc_html(SIA_ANCF_RUNTIME_MODE) . '</p>';
        echo '<p>Publisher intelligence separates publication state from homepage authority. Search/index state, URLs and external SEO output are not changed by homepage controls.</p>';

        if (isset($_GET['sia_home_saved']) && $_GET['sia_home_saved'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Homepage editorial settings saved.</p></div>';
        }

        echo '<hr><h2>Homepage Editorial Control</h2>';
        echo '<p>Exclude utility, result, legacy or other categories from the main newsroom without deleting, redirecting or noindexing their URLs.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="sia_ancf_save_homepage_settings">';
        wp_nonce_field('sia_ancf_homepage_settings', 'sia_ancf_homepage_nonce');

        if ($categories) {
            echo '<fieldset style="max-width:760px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 18px">';
            foreach ($categories as $category) {
                $term_id = (int) $category->term_id;
                echo '<label style="padding:6px 0"><input type="checkbox" name="sia_home_excluded_categories[]" value="' . esc_attr((string) $term_id) . '" ' . checked(in_array($term_id, $excluded, true), true, false) . '> ' . esc_html($category->name) . ' <span style="color:#646970">(' . esc_html((string) $category->count) . ')</span></label>';
            }
            echo '</fieldset>';
        } else {
            echo '<p>No categories found.</p>';
        }

        submit_button('Save Homepage Exclusions');
        echo '</form>';
        echo '<p><em>Category exclusion controls homepage presentation only. It does not alter canonical, robots, sitemap, redirects, archives, Search Console visibility or direct URLs.</em></p>';
        echo '</div>';
    }

    public static function save_homepage_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change these settings.', 'sia-ancf-core'));
        }

        check_admin_referer('sia_ancf_homepage_settings', 'sia_ancf_homepage_nonce');

        $raw = isset($_POST['sia_home_excluded_categories']) ? (array) wp_unslash($_POST['sia_home_excluded_categories']) : [];
        $term_ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
        update_option('sia_ancf_home_excluded_categories', $term_ids, false);

        wp_safe_redirect(add_query_arg([
            'page' => 'sia-ancf',
            'sia_home_saved' => '1',
        ], admin_url('tools.php')));
        exit;
    }

    public static function homepage_excluded_categories(): array {
        $value = get_option('sia_ancf_home_excluded_categories', []);
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    public static function register_story_meta_box(): void {
        add_meta_box('sia-ancf-story', 'SIA ANCF Story', [self::class, 'render_story_meta_box'], 'post', 'side', 'default');
    }

    public static function render_story_meta_box(WP_Post $post): void {
        wp_nonce_field('sia_ancf_story_meta', 'sia_ancf_story_nonce');
        $content_type = get_post_meta($post->ID, '_sia_content_type', true) ?: 'news';
        $editorial_state = get_post_meta($post->ID, '_sia_editorial_state', true) ?: 'draft';
        $ai_assistance = get_post_meta($post->ID, '_sia_ai_assistance', true) ?: 'none';
        $commercial_type = get_post_meta($post->ID, '_sia_commercial_type', true) ?: 'editorial';
        $home_eligible_raw = get_post_meta($post->ID, '_sia_home_eligible', true);
        $home_eligible = $home_eligible_raw === '' ? true : (bool) $home_eligible_raw;
        $home_exclude = (bool) get_post_meta($post->ID, '_sia_home_exclude', true);
        $home_placement = get_post_meta($post->ID, '_sia_home_placement', true) ?: 'normal';

        self::render_select('sia_content_type', 'Content type', self::CONTENT_TYPES, $content_type);
        self::render_select('sia_editorial_state', 'Editorial state', self::EDITORIAL_STATES, $editorial_state);
        self::render_select('sia_ai_assistance', 'AI assistance', ['none', 'research', 'draft', 'edit', 'mixed'], $ai_assistance);
        self::render_select('sia_commercial_type', 'Commercial type', self::COMMERCIAL_TYPES, $commercial_type);

        echo '<hr><p><strong>Homepage</strong></p>';
        echo '<input type="hidden" name="sia_home_eligible" value="0">';
        echo '<p><label><input type="checkbox" name="sia_home_eligible" value="1" ' . checked($home_eligible, true, false) . '> Eligible for homepage</label></p>';
        self::render_select('sia_home_placement', 'Placement', self::HOME_PLACEMENTS, $home_placement);
        echo '<input type="hidden" name="sia_home_exclude" value="0">';
        echo '<p><label><input type="checkbox" name="sia_home_exclude" value="1" ' . checked($home_exclude, true, false) . '> Exclude from main newsroom</label></p>';
        echo '<p><small>Published ≠ Homepage Eligible ≠ Top Story. These controls change presentation only; they do not change the URL or index state.</small></p>';
    }

    private static function render_select(string $name, string $label, array $values, string $selected): void {
        echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><br>';
        echo '<select style="width:100%" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
        foreach ($values as $value) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($selected, $value, false), esc_html(ucwords(str_replace('_', ' ', $value))));
        }
        echo '</select></p>';
    }

    public static function save_story_meta(int $post_id, WP_Post $post): void {
        if (!isset($_POST['sia_ancf_story_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sia_ancf_story_nonce'])), 'sia_ancf_story_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $map = [
            'sia_content_type' => '_sia_content_type',
            'sia_editorial_state' => '_sia_editorial_state',
            'sia_ai_assistance' => '_sia_ai_assistance',
            'sia_commercial_type' => '_sia_commercial_type',
        ];
        foreach ($map as $input => $meta_key) {
            if (isset($_POST[$input])) {
                update_post_meta($post_id, $meta_key, sanitize_key(wp_unslash($_POST[$input])));
            }
        }

        $home_eligible = isset($_POST['sia_home_eligible']) && sanitize_key(wp_unslash($_POST['sia_home_eligible'])) === '1';
        $home_exclude = isset($_POST['sia_home_exclude']) && sanitize_key(wp_unslash($_POST['sia_home_exclude'])) === '1';
        $home_placement = isset($_POST['sia_home_placement']) ? self::sanitize_home_placement(sanitize_key(wp_unslash($_POST['sia_home_placement']))) : 'normal';

        update_post_meta($post_id, '_sia_home_eligible', $home_eligible ? '1' : '0');
        update_post_meta($post_id, '_sia_home_exclude', $home_exclude ? '1' : '0');
        update_post_meta($post_id, '_sia_home_placement', $home_placement);

        if ($home_placement === 'lead' && !$home_exclude && $home_eligible) {
            self::demote_other_leads($post_id);
        }
    }

    private static function demote_other_leads(int $post_id): void {
        $other_ids = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'fields' => 'ids',
            'posts_per_page' => 50,
            'post__not_in' => [$post_id],
            'meta_key' => '_sia_home_placement',
            'meta_value' => 'lead',
            'no_found_rows' => true,
        ]);

        foreach ($other_ids as $other_id) {
            update_post_meta((int) $other_id, '_sia_home_placement', 'normal');
        }
    }

    public static function sanitize_content_type(string $value): string {
        return in_array($value, self::CONTENT_TYPES, true) ? $value : 'news';
    }

    public static function sanitize_editorial_state(string $value): string {
        return in_array($value, self::EDITORIAL_STATES, true) ? $value : 'draft';
    }

    public static function sanitize_ai_assistance(string $value): string {
        return in_array($value, ['none', 'research', 'draft', 'edit', 'mixed'], true) ? $value : 'none';
    }

    public static function sanitize_commercial_type(string $value): string {
        return in_array($value, self::COMMERCIAL_TYPES, true) ? $value : 'editorial';
    }

    public static function sanitize_home_placement(string $value): string {
        return in_array($value, self::HOME_PLACEMENTS, true) ? $value : 'normal';
    }
}
