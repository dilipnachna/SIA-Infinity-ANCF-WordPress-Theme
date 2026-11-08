<?php
/** Safe, explicit import of public settings and content metadata from replaced plugins. */
if (!defined('ABSPATH')) { exit; }

function sia_ancf_migration_template_supported(string $value): bool {
    if ($value === '' || strlen($value) > 10000 || preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) { return false; }
    preg_match_all('/%[a-zA-Z_][a-zA-Z0-9_]*%/', $value, $found);
    return !array_diff($found[0], ['%title%', '%sitename%', '%sep%', '%currentdate%', '%excerpt%']);
}

function sia_ancf_legacy_redirect_rules(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'rank_math_redirections';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { return []; }
    $rows = $wpdb->get_results("SELECT sources,url_to,header_code,status FROM `{$table}`", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $home = wp_parse_url(home_url('/'));
    if (!is_array($home) || empty($home['host'])) { return []; }
    $rules = [];$conflicts = [];
    foreach ($rows as $row) {
        if (($row['status'] ?? '') !== 'active' || (int)($row['header_code'] ?? 0) !== 301 || !is_string($row['url_to'] ?? null)) { continue; }
        $target_url = wp_parse_url($row['url_to']);
        if (!is_array($target_url) || !empty($target_url['query']) || !empty($target_url['fragment']) || isset($target_url['user']) || isset($target_url['pass'])) { continue; }
        if (!empty($target_url['host']) && strcasecmp((string)$target_url['host'], (string)$home['host']) !== 0) { continue; }
        $target = sia_ancf_redirect_path((string)($target_url['path'] ?? ''));
        $sources = maybe_unserialize($row['sources'] ?? '');
        if ($target === '' || !is_array($sources)) { continue; }
        foreach ($sources as $source) {
            if (!is_array($source) || ($source['comparison'] ?? '') !== 'exact' || !empty($source['ignore']) || !is_string($source['pattern'] ?? null)) { continue; }
            $path = sia_ancf_redirect_path($source['pattern']);
            if ($path === '' || $path === $target) { continue; }
            if (isset($rules[$path]) && $rules[$path] !== '/' . $target) { $conflicts[$path] = true; continue; }
            $rules[$path] = '/' . $target;
        }
    }
    foreach (array_keys($conflicts) as $path) { unset($rules[$path]); }
    // Reject cycles rather than turning a legacy mistake into a live loop.
    foreach (array_keys($rules) as $start) {
        $seen=[];$cursor=$start;
        while (isset($rules[$cursor]) && !isset($seen[$cursor])) { $seen[$cursor]=true;$cursor=sia_ancf_redirect_path($rules[$cursor]); }
        if (isset($seen[$cursor])) { foreach (array_keys($seen) as $path) { unset($rules[$path]); } }
    }
    return $rules;
}

/** @return array<string,int> */
function sia_ancf_migrate_legacy_plugins(bool $apply = false): array {
    $counts = ['metadata_planned'=>0,'metadata_written'=>0,'metadata_conflicts'=>0,'metadata_unsupported'=>0,'schema_planned'=>0,'schema_written'=>0,'schema_review_required'=>0,'settings_planned'=>0,'settings_written'=>0,'redirects_planned'=>0,'redirects_written'=>0];
    $map = [
        'rank_math_title'=>'_sia_seo_title_template',
        'rank_math_description'=>'_sia_seo_description_template',
        'rank_math_facebook_title'=>'_sia_seo_og_title_template',
        'rank_math_focus_keyword'=>'_sia_focus_keywords',
    ];
    $ids = get_posts(['post_type'=>['post','page'],'post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true]);
    foreach ($ids as $id) {
        foreach ($map as $source=>$target) {
            $value=get_post_meta($id,$source,true);
            if (!is_string($value) || $value==='') { continue; }
            if ($source !== 'rank_math_focus_keyword' && !sia_ancf_migration_template_supported($value)) { $counts['metadata_unsupported']++;continue; }
            if ($source === 'rank_math_focus_keyword') { $value=implode(', ',array_slice(array_values(array_filter(array_unique(array_map('trim',explode(',',$value))))),0,10)); }
            $existing=get_post_meta($id,$target,true);
            if ($existing !== '' && $existing !== $value) { $counts['metadata_conflicts']++;continue; }
            if ($existing === $value) { continue; }
            $counts['metadata_planned']++;
            if ($apply) { update_post_meta($id,$target,wp_slash($value));$counts['metadata_written']++; }
        }
        $primary=(int)get_post_meta($id,'rank_math_primary_category',true);
        if ($primary>0 && get_post_meta($id,'_sia_primary_silo_term_id',true)==='' && term_exists($primary,'category')) {
            $counts['metadata_planned']++;if($apply){update_post_meta($id,'_sia_primary_silo_term_id',$primary);$counts['metadata_written']++;}
        }
        $social=(int)get_post_meta($id,'rank_math_facebook_image_id',true);
        if($social>0&&wp_attachment_is_image($social)&&get_post_meta($id,'_sia_social_image_id',true)===''){
            $counts['metadata_planned']++;if($apply){update_post_meta($id,'_sia_social_image_id',$social);$counts['metadata_written']++;}
        }
        if (sia_ancf_post_is_noindex($id) && get_post_meta($id,'_sia_seo_noindex',true)!=='1') {
            $counts['metadata_planned']++;if($apply){update_post_meta($id,'_sia_seo_noindex','1');$counts['metadata_written']++;}
        }
        $post=get_post($id);
        if (!$post instanceof WP_Post || $post->post_status!=='publish' || $post->post_password!=='') { continue; }
        $blog=get_post_meta($id,'rank_math_schema_BlogPosting',false);
        if ($blog) {
            $valid=count($blog)===1&&is_array($blog[0])&&($blog[0]['@type']??'')==='BlogPosting'
                &&($blog[0]['headline']??'')==='%seo_title%'&&($blog[0]['description']??'')==='%seo_description%'
                &&!get_post_meta($id,'rank_math_schema_NewsArticle',false)&&!get_post_meta($id,'rank_math_schema_Article',false);
            if($valid&&get_post_meta($id,'_sia_article_schema_type',true)===''&&get_post_meta($id,'_sia_schema_title_source',true)===''){
                $counts['schema_planned']++;if($apply){update_post_meta($id,'_sia_article_schema_type','BlogPosting');update_post_meta($id,'_sia_schema_title_source','seo');$counts['schema_written']++;}
            }elseif(!$valid){$counts['schema_review_required']++;}
        }
        $faq=get_post_meta($id,'rank_math_schema_FAQPage',false);
        if($faq&&get_post_meta($id,'_sia_faq_schema',true)===''){
            $items=[];$valid=count($faq)===1&&is_array($faq[0])&&($faq[0]['@type']??'')==='FAQPage'&&is_array($faq[0]['mainEntity']??null);
            if($valid){foreach($faq[0]['mainEntity'] as $q){if(!is_array($q)||($q['@type']??'')!=='Question'||!is_string($q['name']??null)||!is_array($q['acceptedAnswer']??null)||!is_string($q['acceptedAnswer']['text']??null)){$valid=false;break;}$items[]=['question'=>$q['name'],'answer'=>$q['acceptedAnswer']['text']];}}
            if($valid&&sia_ancf_faq_validate($post,$items)){$data=['content_sha256'=>hash('sha256',$post->post_content),'items'=>$items];$counts['schema_planned']++;if($apply){update_post_meta($id,'_sia_faq_schema',wp_slash($data));$counts['schema_written']++;}}
            else{$counts['schema_review_required']++;}
        }
        $video=get_post_meta($id,'rank_math_schema_VideoObject',false);
        if($video&&get_post_meta($id,'_sia_video_schema',true)===''){
            $v=count($video)===1&&is_array($video[0])?$video[0]:[];$valid=(bool)$v;
            foreach(['name','description','thumbnailUrl','embedUrl','uploadDate'] as $field){if(!is_string($v[$field]??null)||trim($v[$field])==='')$valid=false;}
            foreach(['thumbnailUrl','embedUrl'] as $field){$u=wp_parse_url($v[$field]??'');if(!is_array($u)||!in_array(strtolower($u['scheme']??''),['http','https'],true)||empty($u['host'])||isset($u['user'])||isset($u['pass']))$valid=false;}
            $video_id=basename((string)(wp_parse_url($v['embedUrl']??'',PHP_URL_PATH)??''));if($video_id===''||!str_contains($post->post_content,$video_id)||!strtotime($v['uploadDate']??''))$valid=false;
            if($valid){$v=array_intersect_key($v,array_flip(['name','description','thumbnailUrl','embedUrl','contentUrl','uploadDate','duration','isFamilyFriendly']));$v['content_sha256']=hash('sha256',$post->post_content);$counts['schema_planned']++;if($apply){update_post_meta($id,'_sia_video_schema',wp_slash($v));$counts['schema_written']++;}}
            else{$counts['schema_review_required']++;}
        }
        if((get_post_meta($id,'rank_math_schema_NewsArticle',false)||get_post_meta($id,'rank_math_schema_LiveBlogPosting',false))
            && get_post_meta($id,'_sia_schema_legacy_review',true)===''){$counts['schema_review_required']++;}
    }
    $settings=[];
    $ga=get_option('googlesitekit_analytics-4_settings',[]);$ga=is_array($ga)?($ga['measurementID']??''):'';
    if (is_string($ga)&&preg_match('/\AG-[A-Z0-9]{4,20}\z/',$ga)) { $settings['sia_ga4_measurement_id']=$ga; }
    $ads=get_option('googlesitekit_adsense_settings',[]);$client=is_array($ads)&&!empty($ads['useSnippet'])?($ads['clientID']??''):'';
    if (is_string($client)&&preg_match('/\Aca-pub-[0-9]{16}\z/',$client)) { $adopt=get_option('sia_ancf_ad_placements',[]);$adopt=is_array($adopt)?$adopt:[];$adopt['client']=$client;$adopt['placements']=is_array($adopt['placements']??null)?$adopt['placements']:[];$settings['sia_ancf_ad_placements']=$adopt; }
    $instant=get_option('rank-math-options-instant-indexing',[]);$key=is_array($instant)?($instant['indexnow_api_key']??''):'';
    if (is_string($key)&&preg_match('/\A[A-Za-z0-9-]{8,128}\z/',$key)) { $settings['sia_indexnow_key']=$key;$settings['sia_indexnow_enabled']='1'; }
    $hubs=get_option('pubsubhubbub_endpoints',[]);$hubs=is_string($hubs)?preg_split('/\R/',$hubs):$hubs;$safe=[];
    foreach (is_array($hubs)?$hubs:[] as $hub) { if(!is_string($hub))continue;$u=wp_parse_url($hub);if(is_array($u)&&strtolower($u['scheme']??'')==='https'&&!empty($u['host'])&&!isset($u['user'])&&!isset($u['pass'])&&!isset($u['query'])&&!isset($u['fragment'])&&filter_var($hub,FILTER_VALIDATE_URL))$safe[]=esc_url_raw($hub,['https']); }
    $safe=array_slice(array_values(array_unique($safe)),0,5);if($safe){$settings['sia_websub_hubs']=$safe;$settings['sia_websub_enabled']='1';}
    foreach($settings as $name=>$value){$old=get_option($name,null);if($old!==null&&$old!==false&&$old!==''&&$old!==[]&&$old!==$value){$counts['metadata_conflicts']++;continue;}if($old===$value)continue;$counts['settings_planned']++;if($apply){update_option($name,$value,false);$counts['settings_written']++;}}
    $rules=sia_ancf_legacy_redirect_rules();$old=get_option('sia_seo_redirects',[]);$old=is_array($old)?$old:[];$merged=$old;
    foreach($rules as $source=>$target){if(isset($merged[$source])&&$merged[$source]!==$target){$counts['metadata_conflicts']++;continue;}if(!isset($merged[$source])){$merged[$source]=$target;$counts['redirects_planned']++;}}
    if($apply&&$merged!==$old){update_option('sia_seo_redirects',$merged,false);$counts['redirects_written']=$counts['redirects_planned'];}
    if($apply){update_option('sia_plugin_migration_version','1',false);}
    return $counts;
}

add_action('admin_menu',static function():void{
    add_theme_page(__('Plugin migration','sia-ancf-news'),__('Plugin migration','sia-ancf-news'),'manage_options','sia-plugin-migration',static function():void{
        if(!current_user_can('manage_options')){wp_die(esc_html__('Not permitted.','sia-ancf-news'),'', ['response'=>403]);}
        $plan=sia_ancf_migrate_legacy_plugins(false);echo '<div class="wrap"><h1>'.esc_html__('Plugin migration','sia-ancf-news').'</h1>';
        echo '<p>'.esc_html__('Copies only public IDs, safe SEO fields, exact same-site redirects and publishing endpoints. Google OAuth tokens and private credentials are never copied.','sia-ancf-news').'</p><table class="widefat striped"><tbody>';
        foreach($plan as $key=>$value){echo '<tr><th>'.esc_html(str_replace('_',' ',$key)).'</th><td>'.(int)$value.'</td></tr>';}
        echo '</tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="sia_plugin_migration_apply">';wp_nonce_field('sia_plugin_migration_apply');submit_button(__('Apply safe migration','sia-ancf-news'),'primary','submit',true,['onclick'=>"return confirm('Apply the reviewed safe migration?')"]);echo '</form></div>';
    });
});
add_action('admin_post_sia_plugin_migration_apply',static function():void{
    if(!current_user_can('manage_options')){wp_die(esc_html__('Not permitted.','sia-ancf-news'),'', ['response'=>403]);}check_admin_referer('sia_plugin_migration_apply');sia_ancf_migrate_legacy_plugins(true);wp_safe_redirect(admin_url('themes.php?page=sia-plugin-migration&migrated=1'));exit;
});
