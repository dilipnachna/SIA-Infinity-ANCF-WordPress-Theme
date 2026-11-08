<?php
/** Explicit draft-only copy of common editorial fields. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_copy_draft(int $source_id) {
    $source = get_post($source_id);
    if (!$source instanceof WP_Post || !in_array($source->post_type, ['post','page'], true)) { return new WP_Error('invalid_source', 'Invalid post.'); }
    $type = get_post_type_object($source->post_type);
    if (!$type || !current_user_can('edit_post', $source_id) || !current_user_can($type->cap->create_posts)) { return new WP_Error('forbidden', 'Not permitted.'); }
    $id = wp_insert_post(wp_slash([
        'post_type'=>$source->post_type, 'post_status'=>'draft', 'post_title'=>$source->post_title,
        'post_content'=>$source->post_content, 'post_excerpt'=>$source->post_excerpt,
        'post_author'=>get_current_user_id(), 'comment_status'=>$source->comment_status,
        'ping_status'=>$source->ping_status, 'menu_order'=>$source->menu_order,
        'post_parent'=>$source->post_parent,
    ]), true);
    if (is_wp_error($id)) { return $id; }
    foreach (get_object_taxonomies($source->post_type, 'objects') as $taxonomy) {
        if (!current_user_can($taxonomy->cap->assign_terms)) { continue; }
        $terms = wp_get_object_terms($source_id, $taxonomy->name, ['fields'=>'ids']);
        if (!is_wp_error($terms)) { wp_set_object_terms($id, array_map('intval', $terms), $taxonomy->name); }
    }
    foreach (['_thumbnail_id','_wp_page_template','_sia_seo_title_template','_sia_seo_description','_sia_seo_description_template','_sia_seo_og_title_template','_sia_seo_noindex','_sia_focus_keywords','_sia_social_image_id','_sia_content_type'] as $key) {
        if (metadata_exists('post', $source_id, $key)) { update_post_meta($id, $key, wp_slash(get_post_meta($source_id, $key, true))); }
    }
    return $id;
}
add_filter('post_row_actions', 'sia_ancf_copy_draft_action', 10, 2);
add_filter('page_row_actions', 'sia_ancf_copy_draft_action', 10, 2);
function sia_ancf_copy_draft_action(array $actions, WP_Post $post): array {
    $type = get_post_type_object($post->post_type);
    if (defined('DUPLICATE_POST_FILE') || !$type || !in_array($post->post_type, ['post','page'], true)
        || !current_user_can('edit_post', $post->ID) || !current_user_can($type->cap->create_posts)) { return $actions; }
    $GLOBALS['sia_ancf_copy_forms'][(int) $post->ID] = true;
    $actions['sia_copy_draft'] = '<button type="submit" form="sia-copy-post-' . (int) $post->ID . '" class="button-link">' . esc_html__('Copy to draft', 'sia-ancf-news') . '</button>';
    return $actions;
}
add_action('admin_footer', static function (): void {
    foreach (array_keys($GLOBALS['sia_ancf_copy_forms'] ?? []) as $id) {
        echo '<form id="sia-copy-post-' . (int) $id . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="sia_copy_draft"><input type="hidden" name="post" value="' . (int) $id . '">';
        wp_nonce_field('sia_copy_draft_' . (int) $id);
        echo '</form>';
    }
});
add_action('admin_post_sia_copy_draft', static function (): void {
    $id = isset($_POST['post']) ? absint($_POST['post']) : 0;
    check_admin_referer('sia_copy_draft_' . $id);
    $copy = sia_ancf_copy_draft($id);
    if (is_wp_error($copy)) { wp_die(esc_html__('Unable to copy this post.', 'sia-ancf-news'), '', ['response'=>403]); }
    wp_safe_redirect(admin_url('post.php?action=edit&post=' . (int) $copy));
    exit;
});
