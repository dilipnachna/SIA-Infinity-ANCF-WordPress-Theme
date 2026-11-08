<?php
/** Opt-in GA4 tag fallback; intentionally separate from Site Kit OAuth/reporting. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_ga4_id(): string {
    $value=get_option('sia_ga4_measurement_id','');
    if (!is_string($value) || $value==='') {
        $legacy=get_option('googlesitekit_analytics-4_settings',[]);
        $value=is_array($legacy) ? ($legacy['measurementID']??'') : '';
    }
    return is_string($value) && preg_match('/\AG-[A-Z0-9]{4,20}\z/', $value) ? $value : '';
}
function sia_ancf_sitekit_ga4_snippet_active(): bool {
    if (!defined('GOOGLESITEKIT_VERSION')) { return false; }
    $settings=get_option('googlesitekit_analytics-4_settings',[]);
    return is_array($settings)&&!empty($settings['useSnippet'])
        &&is_string($settings['measurementID']??null)&&preg_match('/\AG-[A-Z0-9]{4,20}\z/',$settings['measurementID'])===1;
}
add_action('admin_menu', static function (): void {
    add_theme_page(__('Analytics tag', 'sia-ancf-news'), __('Analytics tag', 'sia-ancf-news'), 'manage_options', 'sia-ga4', static function (): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response'=>403]); }
        echo '<div class="wrap"><h1>' . esc_html__('Analytics tag', 'sia-ancf-news') . '</h1><p>' . esc_html__('Optional GA4 tag. It loads only after the visitor permits analytics. This does not provide Site Kit reports or ad consent management.', 'sia-ancf-news') . '</p>';
        if (sia_ancf_sitekit_ga4_snippet_active()) { echo '<p>' . esc_html__('Site Kit currently owns the frontend tag, so this fallback is disabled to avoid duplicates.', 'sia-ancf-news') . '</p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sia_ga4_save">';
        wp_nonce_field('sia_ga4_save');
        echo '<p><label>' . esc_html__('GA4 measurement ID', 'sia-ancf-news') . ' <input type="text" name="measurement_id" value="' . esc_attr(sia_ancf_ga4_id()) . '" placeholder="G-XXXXXXXXXX"></label></p><p>' . esc_html__('Leave blank to disable.', 'sia-ancf-news') . '</p>';
        submit_button(); echo '</form></div>';
    });
});
add_action('admin_post_sia_ga4_save', static function (): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response'=>403]); }
    check_admin_referer('sia_ga4_save');
    $id=isset($_POST['measurement_id']) && is_string($_POST['measurement_id']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['measurement_id']))) : '';
    if ($id!=='' && !preg_match('/\AG-[A-Z0-9]{4,20}\z/', $id)) { wp_die(esc_html__('Invalid measurement ID.', 'sia-ancf-news'), '', ['response'=>400]); }
    if ($id==='') { delete_option('sia_ga4_measurement_id'); } else { update_option('sia_ga4_measurement_id', $id, false); }
    wp_safe_redirect(admin_url('themes.php?page=sia-ga4&updated=1')); exit;
});
add_action('wp_enqueue_scripts', static function (): void {
    $id=sia_ancf_ga4_id();
    if (!$id || sia_ancf_sitekit_ga4_snippet_active() || is_feed() || is_preview()) { return; }
    wp_enqueue_script('sia-ga4-consent', get_template_directory_uri() . '/assets/ga4-consent.js', [], '0.1.0', ['in_footer'=>true]);
    wp_localize_script('sia-ga4-consent','siaGA4Config',['id'=>$id]);
});
add_action('wp_footer', static function (): void {
    if (!sia_ancf_ga4_id() || sia_ancf_sitekit_ga4_snippet_active() || is_feed() || is_preview()) { return; }
    echo '<section id="sia-analytics-consent" class="sia-analytics-consent" hidden aria-label="' . esc_attr__('Analytics choices', 'sia-ancf-news') . '">';
    echo '<p>' . esc_html__('Allow analytics to help us understand site usage? This choice applies to analytics only.', 'sia-ancf-news') . '</p>';
    echo '<button type="button" data-sia-analytics="accept">' . esc_html__('Allow analytics', 'sia-ancf-news') . '</button> ';
    echo '<button type="button" data-sia-analytics="reject">' . esc_html__('Decline analytics', 'sia-ancf-news') . '</button></section>';
    echo '<button type="button" id="sia-analytics-change" class="sia-analytics-change">' . esc_html__('Analytics choices', 'sia-ancf-news') . '</button>';
});
