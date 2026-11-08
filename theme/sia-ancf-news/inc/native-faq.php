<?php
/** Conservative FAQ schema: only complete, visible content with a current fingerprint. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_faq_plain_text(string $text): string {
    $text=html_entity_decode(wp_strip_all_tags($text,true),ENT_QUOTES|ENT_HTML5,'UTF-8');
    return trim((string)preg_replace('/\s+/u',' ',$text));
}
function sia_ancf_faq_validate(WP_Post $post, array $items): bool {
    if ($post->post_status!=='publish' || $post->post_password!=='' || !$items || count($items)>50 || strlen($post->post_content)>2*1024*1024
        || preg_match('/<\s*(script|style|template|noscript|iframe)\b|\b(?:hidden|aria-hidden)\s*(?:=|>|\/)|display\s*:\s*none|visibility\s*:\s*hidden|\[\/?[a-z_]+(?:\s|\])/i',$post->post_content)) { return false; }
    $plain=sia_ancf_faq_plain_text($post->post_content);
    if ($plain==='') { return false; }
    foreach ($items as $item) {
        if (!is_array($item)||!is_string($item['question']??null)||!is_string($item['answer']??null)
            || strlen($item['question'])>2000||strlen($item['answer'])>10000) { return false; }
        $question=sia_ancf_faq_plain_text($item['question']);$answer=sia_ancf_faq_plain_text($item['answer']);
        if ($question===''||$answer===''||strpos($plain,$question)===false||strpos($plain,$answer)===false) { return false; }
    }
    return true;
}
function sia_ancf_faq_schema(WP_Post $post, string $url): ?array {
    $saved=get_post_meta($post->ID,'_sia_faq_schema',true);
    if (!is_array($saved)||!is_array($saved['items']??null)||!is_string($saved['content_sha256']??null)
        || !hash_equals(hash('sha256',$post->post_content),$saved['content_sha256'])
        || sia_ancf_post_is_noindex($post->ID) || !sia_ancf_faq_validate($post,$saved['items'])) { return null; }
    $questions=[];
    foreach ($saved['items'] as $item) {
        $questions[]=['@type'=>'Question','name'=>sia_ancf_faq_plain_text($item['question']),
            'acceptedAnswer'=>['@type'=>'Answer','text'=>sia_ancf_faq_plain_text($item['answer'])]];
    }
    return ['@type'=>'FAQPage','@id'=>$url.'#faq','mainEntity'=>$questions];
}
