<?php
/** Use existing WebP/AVIF alternates for WordPress-generated image HTML only. */
if (!defined('ABSPATH')) { exit; }
function sia_ancf_webp_rewrite_url(string $url, array $replacements, string $base_url): string {
    foreach ($replacements as $old => $new) {
        $old_urls=[$base_url . $old, $base_url . rawurlencode($old)];
        if (in_array($url,$old_urls,true)) { return $base_url . rawurlencode($new); }
    }
    return $url;
}
add_filter('wp_get_attachment_image', static function ($html, $attachment_id, $size, $icon, $attr) {
    $format=get_option('perflab_modern_image_format');
    if (!is_string($html) || $html==='' || $icon || is_admin() || is_feed() || defined('REST_REQUEST') || defined('XMLRPC_REQUEST')
        || !did_action('template_redirect') || doing_action('wp_head') || function_exists('webp_uploads_img_tag_update_mime_type')
        || !in_array($format,['webp','avif'],true)
        || !(bool)get_option('perflab_generate_webp_and_jpeg') || !class_exists('WP_HTML_Tag_Processor')) { return $html; }
    $metadata=wp_get_attachment_metadata((int)$attachment_id);
    if (!is_array($metadata) || !is_string($metadata['file']??null)) { return $html; }
    $relative=$metadata['file'];
    if ($relative==='' || str_starts_with($relative,'/') || str_contains($relative,"\\") || preg_match('~(?:^|/)\\.\\.(?:/|$)~',$relative)) { return $html; }
    $upload=wp_get_upload_dir();
    $root=realpath($upload['basedir']??'');
    $directory=dirname($relative);
    $dir=realpath(($upload['basedir']??'') . '/' . ($directory==='.'?'':$directory));
    if (!$root || !$dir || ($dir!==$root && !str_starts_with($dir,$root.DIRECTORY_SEPARATOR))) { return $html; }
    $base_url=trailingslashit($upload['baseurl']) . ($directory==='.'?'':trailingslashit($directory));
    $pairs=[];
    $modern_mime='image/'.$format;
    $target_mime = get_post_mime_type((int)$attachment_id) === $modern_mime ? 'image/jpeg' : $modern_mime;
    $sources=[['file'=>wp_basename($relative),'sources'=>$metadata['sources']??[]]];
    $sizes=is_array($metadata['sizes']??null) ? $metadata['sizes'] : [];
    foreach ($sizes as $row) { if (is_array($row)) { $sources[]=$row; } }
    foreach ($sources as $row) {
        $old=$row['file']??null;$new=$row['sources'][$target_mime]['file']??null;
        if (!is_string($old)||!is_string($new)||$old===''||$new===''||wp_basename($old)!==$old||wp_basename($new)!==$new||str_contains($old,"\\")||str_contains($new,"\\")) { continue; }
        $resolved=realpath($dir . DIRECTORY_SEPARATOR . $new);
        if ($resolved && str_starts_with($resolved,$dir.DIRECTORY_SEPARATOR) && is_file($resolved)) { $pairs[$old]=$new; }
    }
    if (!$pairs) { return $html; }
    $processor=new WP_HTML_Tag_Processor($html);
    if (!$processor->next_tag('img')) { return $html; }
    $src=$processor->get_attribute('src');
    if (is_string($src)) { $processor->set_attribute('src',sia_ancf_webp_rewrite_url($src,$pairs,$base_url)); }
    $srcset=$processor->get_attribute('srcset');
    if (is_string($srcset) && $srcset!=='') {
        $items=explode(',',$srcset);$newset=[];
        foreach ($items as $item) {
            $parts=preg_split('/\\s+/',trim($item),2);
            $url=sia_ancf_webp_rewrite_url($parts[0],$pairs,$base_url);
            $newset[]=$url . (isset($parts[1])?' '.$parts[1]:'');
        }
        $processor->set_attribute('srcset',implode(', ',$newset));
    }
    return $processor->get_updated_html();
}, 10, 5);

/** Detect animated PNG before considering a still-image conversion. */
function sia_ancf_is_animated_png(string $path): bool {
    $f=@fopen($path,'rb');
    if (!$f) { return true; }
    try {
        if (fread($f,8)!=="\x89PNG\r\n\x1a\n") { return true; }
        for ($i=0;$i<10000;$i++) {
            $header=fread($f,8);
            if (strlen($header)!==8) { return true; }
            $parts=unpack('Nlength/a4type',$header);$length=(int)$parts['length'];$type=$parts['type'];
            if ($type==='acTL') { return true; }
            if ($type==='IDAT') { return false; }
            if ($type==='IEND'||$length>32*1024*1024||fseek($f,$length+4,SEEK_CUR)!==0) { return true; }
        }
        return true;
    } finally { fclose($f); }
}
/** Generate a WebP alternate for supported JPEG/PNG uploads while retaining originals. */
function sia_ancf_make_webp_alternate(string $dir, string $filename): ?array {
    if (!function_exists('link') || $filename==='' || wp_basename($filename)!==$filename || str_contains($filename,"\\") || !preg_match('/\.(jpe?g|png)\z/i',$filename,$match)) { return null; }
    $root=realpath($dir);$source=realpath($dir . DIRECTORY_SEPARATOR . $filename);
    if (!$root || !$source || !str_starts_with($source,$root.DIRECTORY_SEPARATOR) || !is_file($source)) { return null; }
    $bytes=filesize($source);$dimensions=@getimagesize($source);
    $expected=strtolower($match[1])==='png'?'image/png':'image/jpeg';
    if (!$bytes || $bytes>8*1024*1024 || !is_array($dimensions) || ($dimensions[0]*$dimensions[1])>32000000 || ($dimensions['mime']??'')!==$expected) { return null; }
    if ($expected==='image/png' && sia_ancf_is_animated_png($source)) { return null; }
    $new=preg_replace('/\.(jpe?g|png)\z/i','-'.strtolower($match[1]).'.webp',$filename);
    if (!is_string($new) || $new===$filename || file_exists($root.DIRECTORY_SEPARATOR.$new)) { return null; }
    $tmp=$root.DIRECTORY_SEPARATOR.'.sia-'.wp_generate_uuid4().'.webp';
    try {
        $editor=wp_get_image_editor($source);
        if (is_wp_error($editor)) { return null; }
        $saved=$editor->save($tmp,'image/webp');
        if (is_wp_error($saved) || !is_file($tmp) || !filesize($tmp) || filesize($tmp)>=$bytes) { return null; }
        // Hard-link installation fails safely if the destination already exists.
        if (!@link($tmp,$root.DIRECTORY_SEPARATOR.$new)) { return null; }
        return ['file'=>$new,'filesize'=>(int)filesize($root.DIRECTORY_SEPARATOR.$new)];
    } finally {
        if (is_file($tmp)) { @unlink($tmp); }
    }
}
add_filter('wp_generate_attachment_metadata',static function ($metadata, int $id) {
    if (!is_array($metadata) || function_exists('webp_uploads_create_sources_property')
        || get_option('perflab_modern_image_format')!=='webp' || !(bool)get_option('perflab_generate_webp_and_jpeg')
        || !in_array(get_post_mime_type($id),['image/jpeg','image/png'],true) || !wp_image_editor_supports(['mime_type'=>'image/webp'])) { return $metadata; }
    $file=$metadata['file']??null;$upload=wp_get_upload_dir();$base=realpath($upload['basedir']??'');
    if (!is_string($file)||$file===''||str_starts_with($file,'/')||str_contains($file,"\\")||preg_match('~(?:^|/)\.\.(?:/|$)~',$file)||!$base) { return $metadata; }
    $relative_dir=dirname($file);$dir=realpath($base.DIRECTORY_SEPARATOR.($relative_dir==='.'?'':$relative_dir));
    if (!$dir||($dir!==$base&&!str_starts_with($dir,$base.DIRECTORY_SEPARATOR))) { return $metadata; }
    $created=[];$generated_by_source=[];
    if (empty($metadata['sources']['image/webp'])) {
        $alt=sia_ancf_make_webp_alternate($dir,wp_basename($file));
        if ($alt) {
            $original=$dir.DIRECTORY_SEPARATOR.wp_basename($file);
            $metadata['sources'][get_post_mime_type($id)]=['file'=>wp_basename($file),'filesize'=>(int)filesize($original)];
            $metadata['sources']['image/webp']=$alt;
            $generated_by_source[wp_basename($file)]=$alt;
            $created[]=$alt['file'];
        }
    }
    if (is_array($metadata['sizes']??null)) {
        foreach ($metadata['sizes'] as &$row) {
            if (!is_array($row)||!is_string($row['file']??null)||!empty($row['sources']['image/webp'])) { continue; }
            $alt=$generated_by_source[$row['file']] ?? sia_ancf_make_webp_alternate($dir,$row['file']);
            if ($alt) { $row['sources']['image/webp']=$alt; $generated_by_source[$row['file']]=$alt; $created[]=$alt['file']; }
        }
        unset($row);
    }
    if ($created) {
        $owned=get_post_meta($id, '_sia_native_webp_generated', true);
        $owned=is_array($owned) ? $owned : [];
        foreach ($created as $name) {
            $absolute=$dir.DIRECTORY_SEPARATOR.$name;
            $hash=is_file($absolute) ? hash_file('sha256',$absolute) : false;
            if (is_string($hash)) { $owned[($relative_dir==='.'?'':trailingslashit($relative_dir)).$name]=$hash; }
        }
        update_post_meta($id,'_sia_native_webp_generated',$owned);
    }
    return $metadata;
}, 20, 2);
add_action('delete_attachment',static function (int $id): void {
    $owned=get_post_meta($id,'_sia_native_webp_generated',true);
    if (!is_array($owned)) { return; }
    $upload=wp_get_upload_dir();$root=realpath($upload['basedir']??'');
    if (!$root) { return; }
    foreach ($owned as $relative=>$expected) {
        if (!is_string($relative)||!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/',$expected)
            || str_starts_with($relative,'/')||str_contains($relative,"\\")||preg_match('~(?:^|/)\.\.(?:/|$)~',$relative)
            || !str_ends_with(strtolower($relative),'.webp')) { continue; }
        $candidate=$root.DIRECTORY_SEPARATOR.$relative;
        if (is_link($candidate)) { continue; }
        $path=realpath($candidate);
        if (!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)) { continue; }
        $actual=hash_file('sha256',$path);
        if (is_string($actual)&&hash_equals($expected,$actual)) { wp_delete_file($path); }
    }
});

/** Generate an AVIF alternate while retaining the JPEG/PNG fallback. */
function sia_ancf_make_avif_alternate(string $dir, string $filename): ?array {
    if (!function_exists('link') || $filename==='' || wp_basename($filename)!==$filename || str_contains($filename,"\\") || !preg_match('/\.(jpe?g|png)\z/i',$filename,$match)) { return null; }
    $root=realpath($dir);$source=realpath($dir.DIRECTORY_SEPARATOR.$filename);
    if (!$root || !$source || !str_starts_with($source,$root.DIRECTORY_SEPARATOR) || !is_file($source)) { return null; }
    $bytes=filesize($source);$dimensions=@getimagesize($source);$expected=strtolower($match[1])==='png'?'image/png':'image/jpeg';
    if (!$bytes || $bytes>8*1024*1024 || !is_array($dimensions) || ($dimensions[0]*$dimensions[1])>32000000 || ($dimensions['mime']??'')!==$expected) { return null; }
    if ($expected==='image/png' && sia_ancf_is_animated_png($source)) { return null; }
    $new=preg_replace('/\.(jpe?g|png)\z/i','-'.strtolower($match[1]).'.avif',$filename);
    if (!is_string($new) || $new===$filename || file_exists($root.DIRECTORY_SEPARATOR.$new)) { return null; }
    $tmp=$root.DIRECTORY_SEPARATOR.'.sia-'.wp_generate_uuid4().'.avif';
    try {
        $editor=wp_get_image_editor($source);
        if (is_wp_error($editor)) { return null; }
        $saved=$editor->save($tmp,'image/avif');
        if (is_wp_error($saved) || !is_file($tmp) || !filesize($tmp) || filesize($tmp)>=$bytes) { return null; }
        if (!@link($tmp,$root.DIRECTORY_SEPARATOR.$new)) { return null; }
        return ['file'=>$new,'filesize'=>(int)filesize($root.DIRECTORY_SEPARATOR.$new)];
    } finally { if (is_file($tmp)) { @unlink($tmp); } }
}
add_filter('wp_generate_attachment_metadata',static function ($metadata,int $id) {
    if (!is_array($metadata) || function_exists('webp_uploads_create_sources_property')
        || get_option('perflab_modern_image_format')!=='avif' || !(bool)get_option('perflab_generate_webp_and_jpeg')
        || !in_array(get_post_mime_type($id),['image/jpeg','image/png'],true) || !wp_image_editor_supports(['mime_type'=>'image/avif'])) { return $metadata; }
    $file=$metadata['file']??null;$upload=wp_get_upload_dir();$base=realpath($upload['basedir']??'');
    if (!is_string($file)||$file===''||str_starts_with($file,'/')||str_contains($file,"\\")||preg_match('~(?:^|/)\.\.(?:/|$)~',$file)||!$base) { return $metadata; }
    $relative_dir=dirname($file);$dir=realpath($base.DIRECTORY_SEPARATOR.($relative_dir==='.'?'':$relative_dir));
    if (!$dir||($dir!==$base&&!str_starts_with($dir,$base.DIRECTORY_SEPARATOR))) { return $metadata; }
    $created=[];$generated=[];
    if (empty($metadata['sources']['image/avif'])) {
        $alt=sia_ancf_make_avif_alternate($dir,wp_basename($file));
        if ($alt) {
            $original=$dir.DIRECTORY_SEPARATOR.wp_basename($file);
            $metadata['sources'][get_post_mime_type($id)]=['file'=>wp_basename($file),'filesize'=>(int)filesize($original)];
            $metadata['sources']['image/avif']=$alt;$generated[wp_basename($file)]=$alt;$created[]=$alt['file'];
        }
    }
    if (is_array($metadata['sizes']??null)) {
        foreach ($metadata['sizes'] as &$row) {
            if (!is_array($row)||!is_string($row['file']??null)||!empty($row['sources']['image/avif'])) { continue; }
            $alt=$generated[$row['file']]??sia_ancf_make_avif_alternate($dir,$row['file']);
            if ($alt) { $row['sources']['image/avif']=$alt;$generated[$row['file']]=$alt;$created[]=$alt['file']; }
        }
        unset($row);
    }
    if ($created) {
        $owned=get_post_meta($id,'_sia_native_avif_generated',true);$owned=is_array($owned)?$owned:[];
        foreach ($created as $name) {
            $absolute=$dir.DIRECTORY_SEPARATOR.$name;$hash=is_file($absolute)?hash_file('sha256',$absolute):false;
            if (is_string($hash)) { $owned[($relative_dir==='.'?'':trailingslashit($relative_dir)).$name]=$hash; }
        }
        update_post_meta($id,'_sia_native_avif_generated',$owned);
    }
    return $metadata;
},21,2);
add_action('delete_attachment',static function (int $id): void {
    $owned=get_post_meta($id,'_sia_native_avif_generated',true);
    if (!is_array($owned)) { return; }
    $upload=wp_get_upload_dir();$root=realpath($upload['basedir']??'');
    if (!$root) { return; }
    foreach ($owned as $relative=>$expected) {
        if (!is_string($relative)||!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/',$expected)
            || str_starts_with($relative,'/')||str_contains($relative,"\\")||preg_match('~(?:^|/)\.\.(?:/|$)~',$relative)
            || !str_ends_with(strtolower($relative),'.avif')) { continue; }
        $candidate=$root.DIRECTORY_SEPARATOR.$relative;
        if (is_link($candidate)) { continue; }
        $path=realpath($candidate);
        if (!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)) { continue; }
        $actual=hash_file('sha256',$path);
        if (is_string($actual)&&hash_equals($expected,$actual)) { wp_delete_file($path); }
    }
});
