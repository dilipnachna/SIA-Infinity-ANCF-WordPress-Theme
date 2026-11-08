<?php
/** Local, read-only view over existing Rank Math analytics tables. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_analytics_table(string $suffix): ?string {
    global $wpdb;
    if (!in_array($suffix, ['gsc', 'ga'], true)) { return null; }
    $table = $wpdb->prefix . 'rank_math_analytics_' . $suffix;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table ? $table : null;
}
add_action('admin_menu', static function (): void {
    add_management_page(__('Local analytics archive', 'sia-ancf-news'), __('Local analytics archive', 'sia-ancf-news'), 'manage_options', 'sia-local-analytics', static function (): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response'=>403]); }
        global $wpdb;
        echo '<div class="wrap"><h1>' . esc_html__('Local analytics archive', 'sia-ancf-news') . '</h1><p>' . esc_html__('Read-only history from existing Rank Math tables; this does not connect to Google or refresh data.', 'sia-ancf-news') . '</p>';
        $gsc = sia_ancf_analytics_table('gsc');
        if ($gsc) {
            $totals=$wpdb->get_row("SELECT COUNT(*) AS rows_count, MIN(created) AS first_date, MAX(created) AS last_date, COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(impressions),0) AS impressions FROM `$gsc`");
            if (!$totals) { echo '<p>' . esc_html__('Search Console data could not be read.', 'sia-ancf-news') . '</p>'; }
            else {
                echo '<h2>Search Console</h2><p>' . esc_html(sprintf(__('Records: %1$s; range: %2$s to %3$s; clicks: %4$s; impressions: %5$s.', 'sia-ancf-news'), number_format_i18n((int)$totals->rows_count), (string)$totals->first_date, (string)$totals->last_date, number_format_i18n((int)$totals->clicks), number_format_i18n((int)$totals->impressions))) . '</p>';
                $months=$wpdb->get_results("SELECT DATE_FORMAT(created, '%Y-%m') AS month, SUM(clicks) AS clicks, SUM(impressions) AS impressions FROM `$gsc` GROUP BY month ORDER BY month DESC LIMIT 24");
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Month', 'sia-ancf-news') . '</th><th>' . esc_html__('Clicks', 'sia-ancf-news') . '</th><th>' . esc_html__('Impressions', 'sia-ancf-news') . '</th></tr></thead><tbody>';
                foreach ($months as $month) { echo '<tr><td>' . esc_html($month->month) . '</td><td>' . esc_html(number_format_i18n((int)$month->clicks)) . '</td><td>' . esc_html(number_format_i18n((int)$month->impressions)) . '</td></tr>'; }
                echo '</tbody></table>';
            }
        } else { echo '<p>' . esc_html__('No Search Console history table found.', 'sia-ancf-news') . '</p>'; }
        $ga = sia_ancf_analytics_table('ga');
        if ($ga) {
            $totals=$wpdb->get_row("SELECT COUNT(*) AS rows_count, MIN(created) AS first_date, MAX(created) AS last_date, COALESCE(SUM(pageviews),0) AS pageviews, COALESCE(SUM(visitors),0) AS visitors FROM `$ga`");
            if ($totals) { echo '<h2>Analytics</h2><p>' . esc_html(sprintf(__('Records: %1$s; range: %2$s to %3$s; pageviews: %4$s; summed visitors: %5$s.', 'sia-ancf-news'), number_format_i18n((int)$totals->rows_count), (string)$totals->first_date, (string)$totals->last_date, number_format_i18n((int)$totals->pageviews), number_format_i18n((int)$totals->visitors))) . '</p><p>' . esc_html__('Summed visitors may count the same person more than once.', 'sia-ancf-news') . '</p>'; }
        }
        echo '</div>';
    });
});
