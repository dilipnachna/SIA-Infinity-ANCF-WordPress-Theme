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

    public static function boot(): void {
        add_action('init', [self::class, 'register_meta']);
        add_action('admin_menu', [self::class, 'register_admin_page']);
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
        echo '<div class="wrap"><h1>SIA Infinity ANCF</h1>';
        echo '<p><strong>Version:</strong> ' . esc_html(SIA_ANCF_CORE_VERSION) . '</p>';
        echo '<p><strong>Runtime mode:</strong> ' . esc_html(SIA_ANCF_RUNTIME_MODE) . '</p>';
        echo '<p>Publisher-intelligence mode observes and models editorial state. It does not replace canonical, robots, redirects, schema, sitemaps, or external SEO authority.</p></div>';
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

        self::render_select('sia_content_type', 'Content type', self::CONTENT_TYPES, $content_type);
        self::render_select('sia_editorial_state', 'Editorial state', self::EDITORIAL_STATES, $editorial_state);
        self::render_select('sia_ai_assistance', 'AI assistance', ['none', 'research', 'draft', 'edit', 'mixed'], $ai_assistance);
        self::render_select('sia_commercial_type', 'Commercial type', self::COMMERCIAL_TYPES, $commercial_type);
        echo '<p><em>v0.2 stores publishing metadata only. External SEO, AI, and monetization services remain separate authorities.</em></p>';
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
}
