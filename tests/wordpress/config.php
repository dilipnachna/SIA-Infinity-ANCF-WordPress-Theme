<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:18087';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PORT'] = '18087';
require '/var/www/html/wp-load.php';
$mode=$argv[1]??'';
$cats=json_decode(file_get_contents('/qa-scripts/fixture.json'),true);
if(!is_array($cats)){echo "MISSING_FIXTURE\n";exit(2);}
if($mode==='categories'){
 $news=term_exists('news','category') ?: wp_insert_term('Breaking News','category',['slug'=>'news']);
 $article=term_exists('article','category') ?: wp_insert_term('Features','category',['slug'=>'article']);
 if(is_wp_error($news)||is_wp_error($article)){echo "TERM_CREATION_ERROR\n";exit(3);}
 update_option('sia_ancf_news_root_category',(int)$news['term_id']);
 update_option('sia_ancf_article_root_category',(int)$article['term_id']);
 update_option('sia_ancf_all_posts_are_news',false);
 wp_set_post_terms($cats['posts']['universal-science-bulletin'],[(int)$news['term_id']],'category');
 wp_set_post_terms($cats['posts']['city-council-public-report'],[(int)$article['term_id']],'category');
 $result=['mode'=>$mode,'news_id'=>(int)$news['term_id'],'article_id'=>(int)$article['term_id']];
} elseif($mode==='custom-slugs'){
 $bulletins=term_exists('bulletins','category') ?: wp_insert_term('Bulletins','category',['slug'=>'bulletins']);
 $features=term_exists('long-reads','category') ?: wp_insert_term('Long Reads','category',['slug'=>'long-reads']);
 if(is_wp_error($bulletins)||is_wp_error($features)){echo 'CUSTOM_TERM_FAILED';exit(5);}
 update_option('sia_ancf_news_root_category',(int)$bulletins['term_id']);
 update_option('sia_ancf_article_root_category',(int)$features['term_id']);
 update_option('sia_ancf_all_posts_are_news',false);
 wp_set_post_terms($cats['posts']['climate-field-research'],[(int)$bulletins['term_id']],'category');
 wp_set_post_terms($cats['posts']['library-opening'],[(int)$features['term_id']],'category');
 $result=['mode'=>$mode,'bulletins_id'=>(int)$bulletins['term_id'],'features_id'=>(int)$features['term_id']];
} elseif($mode==='news-only'){
 update_option('sia_ancf_all_posts_are_news',true);
 $result=['mode'=>$mode,'all_posts_are_news'=>(bool)get_option('sia_ancf_all_posts_are_news')];
} elseif($mode==='reset'){
 delete_option('sia_ancf_news_root_category');
 delete_option('sia_ancf_article_root_category');
 delete_option('sia_ancf_all_posts_are_news');
 $result=['mode'=>$mode];
} else {echo "BAD_MODE\n";exit(4);}
echo json_encode($result,JSON_UNESCAPED_SLASHES);
