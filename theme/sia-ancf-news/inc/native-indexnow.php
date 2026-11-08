<?php
/** Optional IndexNow publisher for eligible public posts; no Google Indexing API calls. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_indexnow_key(): string {
    $v=get_option('sia_indexnow_key','');
    if ((!is_string($v)||$v==='')&&!defined('RM_GIAPI_FILE')) {
        $legacy=get_option('rank-math-options-instant-indexing',[]);
        $v=is_array($legacy)?($legacy['indexnow_api_key']??''):'';
    }
    return is_string($v) && preg_match('/\A[A-Za-z0-9-]{8,128}\z/',$v) ? $v : '';
}
function sia_ancf_indexnow_active(): bool {
    if (defined('RM_GIAPI_FILE')||sia_ancf_indexnow_key()==='') { return false; }
    $enabled=get_option('sia_indexnow_enabled',null);
    return $enabled==='1'||$enabled===null;
}
add_action('admin_menu',static function (): void {
    add_theme_page(__('IndexNow', 'sia-ancf-news'),__('IndexNow', 'sia-ancf-news'),'manage_options','sia-indexnow',static function (): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'),'', ['response'=>403]); }
        echo '<div class="wrap"><h1>IndexNow</h1><p>' . esc_html__('Optional notifications for public post and page updates. This does not submit to the Google Indexing API.', 'sia-ancf-news') . '</p>';
        if (defined('RM_GIAPI_FILE')) { echo '<p>' . esc_html__('Instant Indexing plugin active; theme publisher disabled.', 'sia-ancf-news') . '</p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sia_indexnow_save">';wp_nonce_field('sia_indexnow_save');
        echo '<p><label>' . esc_html__('Public IndexNow key', 'sia-ancf-news') . ' <input type="text" name="key" value="' . esc_attr(sia_ancf_indexnow_key()) . '"></label></p>';
        echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked(get_option('sia_indexnow_enabled')==='1',true,false) . '> ' . esc_html__('Notify IndexNow after public updates', 'sia-ancf-news') . '</label></p>';submit_button();echo '</form></div>';
    });
});
add_action('admin_post_sia_indexnow_save',static function (): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'),'', ['response'=>403]); }
    check_admin_referer('sia_indexnow_save');
    $key=isset($_POST['key']) && is_string($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    $on=isset($_POST['enabled']) && $_POST['enabled']==='1';
    if (($key!=='' && !preg_match('/\A[A-Za-z0-9-]{8,128}\z/',$key)) || ($on && $key==='')) { wp_die(esc_html__('Invalid or missing IndexNow key.', 'sia-ancf-news'),'', ['response'=>400]); }
    if ($key==='') { delete_option('sia_indexnow_key'); } else { update_option('sia_indexnow_key',$key,false); }
    if ($on) { update_option('sia_indexnow_enabled','1',false); } else { delete_option('sia_indexnow_enabled'); }
    wp_safe_redirect(admin_url('themes.php?page=sia-indexnow&updated=1'));exit;
});
add_action('template_redirect',static function (): void {
    if (!sia_ancf_indexnow_active() || !(bool)get_option('blog_public') || !in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD'],true)) { return; }
    $path=wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']??''),PHP_URL_PATH);
    $home=wp_parse_url(home_url('/'),PHP_URL_PATH);
    if (!is_string($path)||!is_string($home)||!str_starts_with($path,$home)) { return; }
    $relative=substr($path,strlen($home));$key=sia_ancf_indexnow_key();
    if (!hash_equals($key.'.txt',$relative)) { return; }
    status_header(200);header('Content-Type: text/plain; charset=UTF-8');
    if (($_SERVER['REQUEST_METHOD']??'')==='GET') { echo esc_html($key); } exit;
},-100);
function sia_ancf_indexnow_eligible(int $id): bool {
    $p=get_post($id);
    return $p instanceof WP_Post && in_array($p->post_type,['post','page'],true) && $p->post_status==='publish' && $p->post_password==='' && !sia_ancf_post_is_noindex($id);
}
function sia_ancf_indexnow_public_url(string $url): string {
    $home=wp_parse_url(home_url('/'));$parsed=wp_parse_url($url);
    if (!is_array($home)||!is_array($parsed)||empty($home['host'])||empty($parsed['host'])
        || strtolower($parsed['host'])!==strtolower($home['host'])||!in_array(strtolower($parsed['scheme']??''),['http','https'],true)
        || isset($parsed['user'])||isset($parsed['pass'])||isset($parsed['fragment'])) { return ''; }
    return esc_url_raw($url,['http','https']);
}
add_action('save_post',static function (int $id): void {
    if (!sia_ancf_indexnow_active() || !(bool)get_option('blog_public') || wp_is_post_revision($id) || wp_is_post_autosave($id) || !sia_ancf_indexnow_eligible($id)) { return; }
    if (!wp_next_scheduled('sia_indexnow_submit',[$id])) { wp_schedule_single_event(time()+10,'sia_indexnow_submit',[$id]); }
},90);
/** Queue a recrawl of the old public URL before it is trashed or deleted. */
function sia_ancf_indexnow_queue_removal(int $id): void {
    if (!sia_ancf_indexnow_active() || !(bool)get_option('blog_public') || !sia_ancf_indexnow_eligible($id)) { return; }
    $url=sia_ancf_indexnow_public_url((string)get_permalink($id));
    if ($url!==''&&!wp_next_scheduled('sia_indexnow_submit_url',[$url,0])) { wp_schedule_single_event(time()+10,'sia_indexnow_submit_url',[$url,0]); }
}
add_action('wp_trash_post','sia_ancf_indexnow_queue_removal',10,1);
add_action('before_delete_post','sia_ancf_indexnow_queue_removal',10,1);
function sia_ancf_indexnow_send_url(string $url,int $attempt=0): void {
    if (!sia_ancf_indexnow_active() || !(bool)get_option('blog_public') || $attempt<0 || $attempt>2) { return; }
    $url=sia_ancf_indexnow_public_url($url);if($url==='') { return; }
    if (wp_get_environment_type()==='local' && !apply_filters('sia_indexnow_allow_local_mock',false)) { return; }
    $home=wp_parse_url(home_url('/'));$key=sia_ancf_indexnow_key();$payload=['host'=>$home['host'],'key'=>$key,'keyLocation'=>home_url('/'.$key.'.txt'),'urlList'=>[$url]];
    $response=wp_safe_remote_post('https://api.indexnow.org/indexnow',['timeout'=>5,'redirection'=>0,'headers'=>['Content-Type'=>'application/json; charset=UTF-8'],'body'=>wp_json_encode($payload)]);
    $code=is_wp_error($response)?0:(int)wp_remote_retrieve_response_code($response);
    if (($code===0||$code===429||$code>=500)&&$attempt<2&&!wp_next_scheduled('sia_indexnow_submit_url',[$url,$attempt+1])) {
        wp_schedule_single_event(time()+(300*(2**$attempt)),'sia_indexnow_submit_url',[$url,$attempt+1]);
    }
    do_action('sia_indexnow_submitted',$response,$url,$attempt);
}
add_action('sia_indexnow_submit_url','sia_ancf_indexnow_send_url',10,2);
add_action('sia_indexnow_submit',static function (int $id): void {
    if (!sia_ancf_indexnow_active() || !(bool)get_option('blog_public') || !sia_ancf_indexnow_eligible($id)) { return; }
    sia_ancf_indexnow_send_url((string)get_permalink($id),0);
});
