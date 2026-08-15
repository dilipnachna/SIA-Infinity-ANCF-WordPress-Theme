<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SIA_URL_Memory {
    public static function boot(): void {
        add_action('save_post', [self::class, 'snapshot_on_save'], 20, 3);
        add_action('post_updated', [self::class, 'snapshot_on_update'], 20, 3);
        add_action('wp_trash_post', [self::class, 'snapshot_on_trash'], 10, 1);
        add_action('before_delete_post', [self::class, 'snapshot_on_delete'], 10, 2);
        add_action('add_meta_boxes', [self::class, 'add_meta_box']);
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sia_ancf_url_memory';
    }

    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            url TEXT NOT NULL,
            event_type VARCHAR(32) NOT NULL DEFAULT 'snapshot',
            post_status VARCHAR(32) NULL,
            observed_at_gmt DATETIME NOT NULL,
            signals_json LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY event_type (event_type)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function snapshot_on_save(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, ['post', 'page'], true)) {
            return;
        }
        self::record($post_id, 'snapshot', $post);
    }

    public static function snapshot_on_update(int $post_id, WP_Post $post_after, WP_Post $post_before): void {
        if ($post_after->post_name !== $post_before->post_name) {
            self::record($post_id, 'slug_change', $post_after, [
                'previous_slug' => $post_before->post_name,
                'current_slug' => $post_after->post_name,
            ]);
        }
        if ($post_after->post_status !== $post_before->post_status) {
            self::record($post_id, 'status_change', $post_after, [
                'previous_status' => $post_before->post_status,
                'current_status' => $post_after->post_status,
            ]);
        }
    }

    public static function snapshot_on_trash(int $post_id): void {
        $post = get_post($post_id);
        if ($post instanceof WP_Post) {
            self::record($post_id, 'trash', $post);
        }
    }

    public static function snapshot_on_delete(int $post_id, WP_Post $post): void {
        self::record($post_id, 'delete', $post);
    }

    private static function record(int $post_id, string $event, WP_Post $post, array $signals = []): void {
        global $wpdb;
        $url = get_permalink($post_id);
        if (!is_string($url) || $url === '') {
            return;
        }

        $table = self::table_name();
        $last = $wpdb->get_row($wpdb->prepare(
            "SELECT url, event_type FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 1",
            $post_id
        ));
        if ($event === 'snapshot' && $last && $last->url === $url && $last->event_type === 'snapshot') {
            return;
        }

        $wpdb->insert($table, [
            'post_id' => $post_id,
            'url' => esc_url_raw($url),
            'event_type' => sanitize_key($event),
            'post_status' => sanitize_key($post->post_status),
            'observed_at_gmt' => current_time('mysql', true),
            'signals_json' => $signals ? wp_json_encode($signals) : null,
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);
    }

    public static function add_meta_box(string $post_type): void {
        if (!in_array($post_type, ['post', 'page'], true)) {
            return;
        }
        add_meta_box('sia-url-memory', 'SIA URL Memory', [self::class, 'render_meta_box'], $post_type, 'side', 'low');
    }

    public static function render_meta_box(WP_Post $post): void {
        global $wpdb;
        $table = self::table_name();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT url, event_type, observed_at_gmt FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 5",
            $post->ID
        ));

        echo '<p><strong>Observe-only.</strong> No redirects or deletion blocks are active.</p>';
        if (!$rows) {
            echo '<p>No URL observations recorded yet.</p>';
            return;
        }
        echo '<ol style="margin-left:1.2em">';
        foreach ($rows as $row) {
            echo '<li><code>' . esc_html($row->event_type) . '</code><br><small>' . esc_html($row->observed_at_gmt) . '</small><br><span style="word-break:break-all">' . esc_html($row->url) . '</span></li>';
        }
        echo '</ol>';
    }
}
