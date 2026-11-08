<?php
/** Opt-in WebSub publisher fallback for public Atom and RSS feed topics. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_websub_active(): bool {
    if (class_exists('Pubsubhubbub\\Pubsubhubbub')) { return false; }
    return get_option('sia_websub_enabled') === '1' || (get_option('sia_websub_enabled',null)===null && sia_ancf_websub_hubs()!==[]);
}
function sia_ancf_websub_hubs(): array {
    $rows=get_option('sia_websub_hubs',[]);
    if ((!is_array($rows)||$rows===[])&&!class_exists('Pubsubhubbub\\Pubsubhubbub')) {
        $legacy=get_option('pubsubhubbub_endpoints',[]);
        $rows=is_string($legacy)?preg_split('/\R/',$legacy):$legacy;
    }
    if (!is_array($rows)) { return []; }
    $out=[];
    foreach ($rows as $raw) {
        if (!is_string($raw)) { continue; }
        $u=wp_parse_url($raw);
        if (!is_array($u) || strtolower($u['scheme'] ?? '')!=='https' || empty($u['host']) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment'])) { continue; }
        $url=esc_url_raw($raw,['https']);
        if ($url && !in_array($url,$out,true)) { $out[]=$url; }
    }
    return array_slice($out,0,5);
}
add_action('admin_menu', static function (): void {
    add_theme_page(__('WebSub publishing', 'sia-ancf-news'), __('WebSub publishing', 'sia-ancf-news'), 'manage_options', 'sia-websub', static function (): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response'=>403]); }
        echo '<div class="wrap"><h1>' . esc_html__('WebSub publishing', 'sia-ancf-news') . '</h1><p>' . esc_html__('Optional publishing for the main, author, category and tag feeds affected by a public post. Subscriber callbacks are not provided.', 'sia-ancf-news') . '</p>';
        if (class_exists('Pubsubhubbub\\Pubsubhubbub')) { echo '<p>' . esc_html__('WebSub plugin active: theme publisher is disabled.', 'sia-ancf-news') . '</p>'; }
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="sia_websub_save">';wp_nonce_field('sia_websub_save');
        echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked(get_option('sia_websub_enabled')==='1',true,false) . '> ' . esc_html__('Notify hubs when a public post is published', 'sia-ancf-news') . '</label></p>';
        echo '<p><label for="sia-websub-hubs">' . esc_html__('HTTPS hub URLs, one per line (maximum five)', 'sia-ancf-news') . '</label></p><textarea id="sia-websub-hubs" name="hubs" class="large-text" rows="5">' . esc_textarea(implode("\n",sia_ancf_websub_hubs())) . '</textarea>';
        submit_button();echo '</form></div>';
    });
});
add_action('admin_post_sia_websub_save', static function (): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'sia-ancf-news'), '', ['response'=>403]); }
    check_admin_referer('sia_websub_save');
    $input=isset($_POST['hubs']) && is_string($_POST['hubs']) ? wp_unslash($_POST['hubs']) : '';
    $rows=array_values(array_filter(array_map('trim',preg_split('/\R/',$input) ?: []),static fn($v)=>$v!==''));
    if (count($rows)>5) { wp_die(esc_html__('Too many hubs.', 'sia-ancf-news'), '', ['response'=>400]); }
    $hubs=[];
    foreach ($rows as $raw) {
        $u=wp_parse_url($raw);
        if (!is_array($u) || strtolower($u['scheme']??'')!=='https' || empty($u['host']) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment']) || !filter_var($raw,FILTER_VALIDATE_URL)) { wp_die(esc_html__('Invalid hub URL.', 'sia-ancf-news'), '', ['response'=>400]); }
        $hubs[]=esc_url_raw($raw,['https']);
    }
    $enabled=isset($_POST['enabled']) && $_POST['enabled']==='1';
    if ($enabled && !$hubs) { wp_die(esc_html__('Add a hub before enabling.', 'sia-ancf-news'), '', ['response'=>400]); }
    update_option('sia_websub_hubs',array_values(array_unique($hubs)),false);
    if ($enabled) { update_option('sia_websub_enabled','1',false); } else { delete_option('sia_websub_enabled'); }
    wp_safe_redirect(admin_url('themes.php?page=sia-websub&updated=1'));exit;
});
function sia_ancf_websub_rss_links(): void {
    if (!sia_ancf_websub_active() || !(bool)get_option('blog_public')) { return; }
    foreach(sia_ancf_websub_hubs() as $hub) { echo '<atom:link rel="hub" href="' . esc_url($hub) . '" />' . "\n"; }
}
function sia_ancf_websub_atom_links(): void {
    if (!sia_ancf_websub_active() || !(bool)get_option('blog_public')) { return; }
    foreach(sia_ancf_websub_hubs() as $hub) { echo '<link rel="hub" href="' . esc_url($hub) . '" />' . "\n"; }
}
add_action('rss2_head','sia_ancf_websub_rss_links');
add_action('atom_head','sia_ancf_websub_atom_links');
/** Return only same-site public feed topics affected by a post update. */
function sia_ancf_websub_topics(int $id): array {
    $post=get_post($id);$home=wp_parse_url(home_url('/'));
    if (!$post instanceof WP_Post || !is_array($home) || empty($home['host'])) { return []; }
    $topics=[];$tags=wp_get_post_tags($id);$categories=wp_get_post_categories($id);
    foreach (['atom','rss2'] as $type) {
        $topics[]=get_feed_link($type);
        foreach ($tags as $tag) { $topics[]=get_term_feed_link($tag->term_id,'post_tag',$type); }
        foreach ($categories as $category) { $topics[]=get_term_feed_link($category,'category',$type); }
        $topics[]=get_author_feed_link((int)$post->post_author,$type);
    }
    $safe=[];
    foreach (array_unique($topics) as $topic) {
        $parsed=is_string($topic) ? wp_parse_url($topic) : false;
        if (!is_array($parsed) || empty($parsed['host']) || strtolower($parsed['host'])!==strtolower($home['host'])
            || !in_array(strtolower($parsed['scheme']??''),['http','https'],true) || isset($parsed['user']) || isset($parsed['pass'])) { continue; }
        $safe[]=$topic;
        if (count($safe)>=100) { break; }
    }
    return $safe;
}
add_action('publish_post', static function (int $id): void {
    $post=get_post($id);
    if (!sia_ancf_websub_active() || !(bool)get_option('blog_public') || !$post || $post->post_password!=='' || wp_is_post_revision($id) || wp_is_post_autosave($id)) { return; }
    // Local staging never makes an actual hub request; tests may provide a mock transport.
    if (wp_get_environment_type()==='local' && !apply_filters('sia_websub_allow_local_mock',false)) { return; }
    $topics=sia_ancf_websub_topics($id);
    if (!$topics) { return; }
    $body='hub.mode=publish';foreach($topics as $topic) { $body.='&hub.url='.rawurlencode($topic); }
    foreach(sia_ancf_websub_hubs() as $hub) {
        $response=wp_safe_remote_post($hub,['timeout'=>5,'redirection'=>0,'headers'=>['Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8'],'body'=>$body]);
        do_action('sia_websub_published',$response,$hub,$topics,$id);
    }
});
