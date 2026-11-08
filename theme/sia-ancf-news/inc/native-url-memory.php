<?php
/** URL observation fallback; reuses existing SIA URL Memory storage. */
if (!defined('ABSPATH')) { exit; }
add_action('after_switch_theme', static function (): void {
    if (class_exists('SIA_URL_Memory')) { return; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = $wpdb->prefix . 'sia_ancf_url_memory';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint unsigned NOT NULL,
        url text NOT NULL,
        event_type varchar(32) NOT NULL DEFAULT 'snapshot',
        post_status varchar(32) NULL,
        observed_at_gmt datetime NOT NULL,
        signals_json longtext NULL,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY event_type (event_type)
    ) $charset;");
});
function sia_ancf_memory_available(): bool {
    global $wpdb;
    if (class_exists('SIA_URL_Memory')) { return false; }
    static $exists = null;
    if ($exists === null) {
        $table = $wpdb->prefix . 'sia_ancf_url_memory';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
    return $exists;
}
function sia_ancf_memory_record(int $id, string $event, WP_Post $post, array $signals = []): void {
    global $wpdb;
    if (!sia_ancf_memory_available() || wp_is_post_revision($id) || wp_is_post_autosave($id) || !in_array($post->post_type, ['post','page'], true)) { return; }
    $url = get_permalink($id);
    if (!is_string($url) || $url === '') { return; }
    $table = $wpdb->prefix . 'sia_ancf_url_memory';
    if ($event === 'snapshot') {
        $last = $wpdb->get_row($wpdb->prepare("SELECT url,event_type FROM `$table` WHERE post_id=%d ORDER BY id DESC LIMIT 1", $id));
        if ($last && $last->url === $url && $last->event_type === 'snapshot') { return; }
    }
    $wpdb->insert($table, ['post_id'=>$id,'url'=>esc_url_raw($url),'event_type'=>sanitize_key($event),'post_status'=>sanitize_key($post->post_status),'observed_at_gmt'=>current_time('mysql',true),'signals_json'=>$signals ? wp_json_encode($signals) : null], ['%d','%s','%s','%s','%s','%s']);
}
add_action('save_post', static function (int $id, WP_Post $post): void { sia_ancf_memory_record($id, 'snapshot', $post); }, 20, 2);
add_action('post_updated', static function (int $id, WP_Post $after, WP_Post $before): void {
    if ($after->post_name !== $before->post_name) { sia_ancf_memory_record($id, 'slug_change', $after, ['previous_slug'=>$before->post_name,'current_slug'=>$after->post_name]); }
    if ($after->post_status !== $before->post_status) { sia_ancf_memory_record($id, 'status_change', $after, ['previous_status'=>$before->post_status,'current_status'=>$after->post_status]); }
},20,3);
add_action('wp_trash_post', static function (int $id): void { $post=get_post($id); if ($post instanceof WP_Post) { sia_ancf_memory_record($id,'trash',$post); } });
add_action('before_delete_post', static function (int $id, WP_Post $post): void { sia_ancf_memory_record($id,'delete',$post); },10,2);
add_action('add_meta_boxes', static function (string $type): void {
    if (!in_array($type, ['post','page'], true) || !sia_ancf_memory_available()) { return; }
    add_meta_box('sia-native-url-memory', __('URL history', 'sia-ancf-news'), static function (WP_Post $post): void {
        global $wpdb;
        if (!current_user_can('edit_post', $post->ID)) { return; }
        $table=$wpdb->prefix . 'sia_ancf_url_memory';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT url,event_type,observed_at_gmt FROM `$table` WHERE post_id=%d ORDER BY id DESC LIMIT 5",$post->ID));
        echo '<p>' . esc_html__('Observation only; no redirects are created.', 'sia-ancf-news') . '</p><ol>';
        foreach ($rows as $row) { echo '<li><code>' . esc_html($row->event_type) . '</code><br><small>' . esc_html($row->observed_at_gmt) . '</small><br>' . esc_html($row->url) . '</li>'; }
        echo '</ol>';
    }, $type, 'side', 'low');
});
