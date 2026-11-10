<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:18087';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PORT'] = '18087';
define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';
if (!is_blog_installed()) {fwrite(STDERR,"NOT_INSTALLED\n");exit(2);}
update_option('home','http://127.0.0.1:18087');
update_option('siteurl','http://127.0.0.1:18087');
update_option('blog_public',true);
switch_theme('sia-ancf-news');
update_option('permalink_structure','/%postname%/');
flush_rewrite_rules();
$terms=[];
foreach (['science','civic','culture'] as $slug) {
    $res=wp_insert_term(ucfirst($slug),'category',['slug'=>$slug]);
    if(is_wp_error($res)){fwrite(STDERR,$res->get_error_message());exit(3);}
    $terms[$slug]=(int)$res['term_id'];
}
$spec=[
 ['Universal Science Bulletin','universal-science-bulletin','science'],
 ['City Council Public Report','city-council-public-report','civic'],
 ['Climate Field Research Notes','climate-field-research','science'],
 ['Public Library Opening','library-opening','civic'],
 ['Arts and Culture Festival','arts-culture-festival','culture'],
 ['Science Center New Lab','science-center-new-lab','science'],
 ['Weekly Local Update','weekly-local-update','civic'],
 ['World Technology Overview','world-technology-overview','culture'],
 ['Solar Mission Launch Details','solar-mission-launch','science'],
 ['Solar Mission Satellite Update','solar-mission-satellite','science'],
 ['Solar Mission Rocket Progress','solar-mission-rocket','science'],
 ['Solar Mission Research Report','solar-mission-research','science'],
];
$posts=[];
foreach($spec as [$title,$slug,$category]){
 $body = str_starts_with($slug, 'solar-mission') ? '<p>Solar mission space research satellite and rocket launch details.</p><p>Mission progress and scientific coverage.</p>' : '<p>Fixture story for WordPress theme compatibility.</p><h2>Details</h2><p>Published test content.</p>';
 $id=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$body,'post_category'=>[$terms[$category]]],true);
 if(is_wp_error($id)){fwrite(STDERR,$id->get_error_message());exit(4);}
 $posts[$slug]=(int)$id;
}
if (class_exists('SIA_Native_Fibonacci_KNN_Inlinks')) {fwrite(STDERR, 'LEGACY_FIBONACCI_STILL_ACTIVE\\n');exit(5);}
echo json_encode(['terms'=>$terms,'posts'=>$posts,'wordpress'=>get_bloginfo('version'),'theme'=>wp_get_theme()->get('Version')],JSON_UNESCAPED_SLASHES);
