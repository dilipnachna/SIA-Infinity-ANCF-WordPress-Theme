<?php
/** Optional manual placements. No ad requests occur without valid saved settings. */
if (!defined('ABSPATH')) {
    exit;
}

function sia_ancf_native_ads_settings(): array {
    $value = get_option('sia_ancf_ad_placements', []);
    return is_array($value) ? $value : [];
}

/** Preserve Site Kit's public AdSense client while its own snippet is disabled. */
function sia_ancf_adsense_client(): string {
    $settings=sia_ancf_native_ads_settings();$client=$settings['client']??'';
    if (!is_string($client)||$client==='') {
        $legacy=get_option('googlesitekit_adsense_settings',[]);
        if (is_array($legacy)) { $client=$legacy['clientID']??''; }
    }
    return is_string($client)&&preg_match('/\Aca-pub-[0-9]{16}\z/',$client)?$client:'';
}

function sia_ancf_sitekit_adsense_snippet_active(): bool {
    if (!defined('GOOGLESITEKIT_VERSION')) { return false; }
    $settings=get_option('googlesitekit_adsense_settings',[]);
    return is_array($settings)&&!empty($settings['useSnippet'])
        &&is_string($settings['clientID']??null)&&preg_match('/\Aca-pub-[0-9]{16}\z/',$settings['clientID'])===1;
}

function sia_ancf_native_ads_config(string $location): ?array {
    $settings = sia_ancf_native_ads_settings();
    $item = $settings['placements'][$location] ?? null;
    $client = sia_ancf_adsense_client();
    if (!in_array($location, ['header', 'title', 'bottom'], true) || !is_array($item)
        || ($item['enabled'] ?? false) !== true || !is_string($client)
        || !preg_match('/\Aca-pub-[0-9]{16}\z/', $client)
        || !is_string($item['slot'] ?? null) || !preg_match('/\A[0-9]+\z/', $item['slot'])) {
        return null;
    }
    return ['client' => $client, 'slot' => $item['slot']];
}

function sia_ancf_native_ads_owned_elsewhere(): bool {
    return class_exists('SIA\\ANCF\\Ads\\Placements') || sia_ancf_sitekit_adsense_snippet_active();
}

add_action('wp_enqueue_scripts', static function (): void {
    if (sia_ancf_native_ads_owned_elsewhere() || is_feed() || is_preview()) {
        return;
    }
    $client=sia_ancf_adsense_client();
    foreach (['header', 'title', 'bottom'] as $location) {
        $config = sia_ancf_native_ads_config($location);
        if ($config) {
            $client=$config['client'];
            break;
        }
    }
    if ($client!=='') {
            $src = add_query_arg('client', $client, 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
            $settings=sia_ancf_native_ads_settings();$delay=max(0,min(15000,(int)($settings['delay_ms']??8000)));
            wp_enqueue_script('sia-native-ads-loader',get_template_directory_uri().'/assets/ads-loader.js',[],wp_get_theme()->get('Version'),['in_footer'=>true]);
            wp_localize_script('sia-native-ads-loader','siaNativeAds',['src'=>$src,'delayMs'=>$delay]);
    }
});

function sia_ancf_native_ad_render(string $location): void {
    static $rendered = [];
    if (sia_ancf_native_ads_owned_elsewhere() || is_admin() || is_feed() || is_preview() || isset($rendered[$location])) {
        return;
    }
    $config = sia_ancf_native_ads_config($location);
    if (!$config) {
        return;
    }
    $rendered[$location] = true;
    echo '<div class="sia-native-ad" data-sia-ad-placement="' . esc_attr($location) . '">';
    echo '<span class="sia-native-ad__label">' . esc_html__('Advertisement', 'sia-ancf-news') . '</span>';
    echo '<ins class="adsbygoogle" style="display:block" data-ad-client="' . esc_attr($config['client']) . '" data-ad-slot="' . esc_attr($config['slot']) . '" data-ad-format="auto" data-full-width-responsive="true"></ins>';
    echo '</div>';
}
add_action('sia_ancf_header_ad', static function (): void { sia_ancf_native_ad_render('header'); });
add_action('sia_ancf_after_article_title', static function (): void { sia_ancf_native_ad_render('title'); });
add_action('sia_ancf_after_article_content', static function (): void { sia_ancf_native_ad_render('bottom'); });

add_action('admin_menu', static function (): void {
    if (sia_ancf_native_ads_owned_elsewhere()) {
        return;
    }
    add_theme_page(__('Ad placements', 'sia-ancf-news'), __('Ad placements', 'sia-ancf-news'), 'manage_options', 'sia-native-ads', static function (): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = sia_ancf_native_ads_settings();
        echo '<div class="wrap"><h1>' . esc_html__('Ad placements', 'sia-ancf-news') . '</h1><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="sia_native_ads_save">';
        wp_nonce_field('sia_native_ads_save');
        $client = is_string($settings['client'] ?? null) ? $settings['client'] : '';
        echo '<p><label>' . esc_html__('AdSense publisher ID', 'sia-ancf-news') . ' <input type="text" name="client" value="' . esc_attr($client) . '" placeholder="ca-pub-0000000000000000"></label></p>';
        $delay=max(0,min(15000,(int)($settings['delay_ms']??8000)));
        echo '<p><label>' . esc_html__('Fallback delay without interaction (milliseconds)', 'sia-ancf-news') . ' <input type="number" min="0" max="15000" step="500" name="delay_ms" value="'.(int)$delay.'"></label></p>';
        echo '<p class="description">'.esc_html__('Ads start on the first scroll, pointer, touch or keyboard interaction. This delay is the fallback for visitors who do not interact.','sia-ancf-news').'</p>';
        foreach (['header' => __('Header', 'sia-ancf-news'), 'title' => __('After article title', 'sia-ancf-news'), 'bottom' => __('After article', 'sia-ancf-news')] as $key => $label) {
            $item = $settings['placements'][$key] ?? [];
            $item = is_array($item) ? $item : [];
            echo '<p><label><input type="checkbox" name="enabled[' . esc_attr($key) . ']" value="1" ' . checked(($item['enabled'] ?? false) === true, true, false) . '> ' . esc_html($label) . '</label> ';
            $slot = is_string($item['slot'] ?? null) ? $item['slot'] : '';
            echo '<label>' . esc_html__('Slot ID', 'sia-ancf-news') . ' <input type="text" inputmode="numeric" name="slot[' . esc_attr($key) . ']" value="' . esc_attr($slot) . '"></label></p>';
        }
        submit_button();
        echo '</form></div>';
    });
});

add_action('admin_post_sia_native_ads_save', static function (): void {
    if (!current_user_can('manage_options') || sia_ancf_native_ads_owned_elsewhere()) {
        wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response' => 403]);
    }
    check_admin_referer('sia_native_ads_save');
    $client = isset($_POST['client']) && is_string($_POST['client']) ? sanitize_text_field(wp_unslash($_POST['client'])) : '';
    $slots = isset($_POST['slot']) && is_array($_POST['slot']) ? wp_unslash($_POST['slot']) : [];
    $enabled = isset($_POST['enabled']) && is_array($_POST['enabled']) ? wp_unslash($_POST['enabled']) : [];
    if ($client !== '' && !preg_match('/\Aca-pub-[0-9]{16}\z/', $client)) {
        wp_die(esc_html__('Invalid publisher ID.', 'sia-ancf-news'), '', ['response' => 400]);
    }
    $delay=isset($_POST['delay_ms'])?absint($_POST['delay_ms']):8000;$delay=max(0,min(15000,$delay));
    $settings = ['client' => $client, 'delay_ms'=>$delay, 'placements' => []];
    foreach (['header', 'title', 'bottom'] as $key) {
        $slot = isset($slots[$key]) && is_string($slots[$key]) ? sanitize_text_field($slots[$key]) : '';
        $on = isset($enabled[$key]) && $enabled[$key] === '1';
        if (($slot !== '' && !preg_match('/\A[0-9]+\z/', $slot)) || ($on && ($slot === '' || $client === ''))) {
            wp_die(esc_html__('Invalid or missing slot ID.', 'sia-ancf-news'), '', ['response' => 400]);
        }
        $settings['placements'][$key] = ['enabled' => $on, 'slot' => $slot];
    }
    update_option('sia_ancf_ad_placements', $settings, false);
    wp_safe_redirect(admin_url('themes.php?page=sia-native-ads&updated=1'));
    exit;
});
