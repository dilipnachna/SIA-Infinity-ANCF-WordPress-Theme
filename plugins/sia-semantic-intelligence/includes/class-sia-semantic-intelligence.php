<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SIA_Semantic_Intelligence {
    public static function boot(): void {
        add_action('add_meta_boxes_post', [self::class, 'add_meta_box']);
        add_action('save_post_post', [self::class, 'save_meta'], 20, 2);
    }

    public static function add_meta_box(): void {
        add_meta_box('sia-primary-silo', 'SIA Primary Semantic Silo', [self::class, 'render_meta_box'], 'post', 'side', 'default');
    }

    public static function render_meta_box(WP_Post $post): void {
        wp_nonce_field('sia_primary_silo_save', 'sia_primary_silo_nonce');
        $selected = (int) get_post_meta($post->ID, '_sia_primary_silo_term_id', true);
        $categories = wp_get_post_categories($post->ID, ['fields' => 'all']);

        echo '<p>Select one already-assigned category as the semantic parent of this story.</p>';
        echo '<select style="width:100%" name="sia_primary_silo_term_id">';
        echo '<option value="0">— Not set —</option>';
        foreach ($categories as $category) {
            printf('<option value="%d" %s>%s</option>', (int) $category->term_id, selected($selected, (int) $category->term_id, false), esc_html($category->name));
        }
        echo '</select>';
        if (!$categories) {
            echo '<p><em>Assign at least one category first.</em></p>';
        }
        echo '<p><em>v0.1 does not alter category URLs, breadcrumbs, schema, or related posts.</em></p>';
    }

    public static function save_meta(int $post_id, WP_Post $post): void {
        if (!isset($_POST['sia_primary_silo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sia_primary_silo_nonce'])), 'sia_primary_silo_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $term_id = isset($_POST['sia_primary_silo_term_id']) ? absint($_POST['sia_primary_silo_term_id']) : 0;
        if ($term_id === 0) {
            delete_post_meta($post_id, '_sia_primary_silo_term_id');
            return;
        }
        $assigned = wp_get_post_categories($post_id, ['fields' => 'ids']);
        if (in_array($term_id, array_map('intval', $assigned), true)) {
            update_post_meta($post_id, '_sia_primary_silo_term_id', $term_id);
        }
    }
}
