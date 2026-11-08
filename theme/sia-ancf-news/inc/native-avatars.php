<?php
/** Read legacy One User Avatar attachment IDs when the plugin is absent. */
if (!defined('ABSPATH')) { exit; }
add_filter('pre_get_avatar_data', static function (array $args, $subject): array {
    if (class_exists('WP_User_Avatar_Functions') || !empty($args['force_default']) || !empty($args['url'])) { return $args; }
    $id = 0;
    if ($subject instanceof WP_User) { $id = $subject->ID; }
    elseif ($subject instanceof WP_Post) { $id = $subject->post_author; }
    elseif ($subject instanceof WP_Comment) { $id = $subject->user_id; }
    elseif (is_numeric($subject)) { $id = (int) $subject; }
    elseif (is_string($subject) && is_email($subject)) { $user = get_user_by('email', $subject); $id = $user ? $user->ID : 0; }
    if (!$id) { return $args; }
    global $wpdb;
    $attachment = (int) get_user_meta((int) $id, $wpdb->get_blog_prefix() . 'user_avatar', true);
    if (!$attachment || !wp_attachment_is_image($attachment)) { return $args; }
    $size = max(1, min(2048, (int) ($args['size'] ?? 96)));
    $url = wp_get_attachment_image_url($attachment, [$size, $size]);
    if (is_string($url) && $url !== '') { $args['url'] = $url; $args['found_avatar'] = true; }
    return $args;
}, 10, 2);

function sia_ancf_avatar_profile_field(WP_User $user): void {
    if (class_exists('WP_User_Avatar_Functions') || !current_user_can('edit_user',$user->ID)) { return; }
    global $wpdb;$key=$wpdb->get_blog_prefix().'user_avatar';$attachment=(int)get_user_meta($user->ID,$key,true);
    wp_nonce_field('sia_avatar_save_'.$user->ID,'sia_avatar_nonce');
    echo '<h2>'.esc_html__('Profile image','sia-ancf-news').'</h2><table class="form-table"><tr><th><label for="sia-avatar-id">'.esc_html__('Media attachment ID','sia-ancf-news').'</label></th><td>';
    if($attachment&&wp_attachment_is_image($attachment)){echo wp_get_attachment_image($attachment,[96,96],false,['style'=>'display:block;margin-bottom:8px']);}
    echo '<input type="number" min="0" id="sia-avatar-id" name="sia_avatar_id" value="'.($attachment?:'').'"><p class="description">'.esc_html__('Choose an image in Media Library and enter its attachment ID. Use 0 to remove the custom profile image.','sia-ancf-news').'</p></td></tr></table>';
}
add_action('show_user_profile','sia_ancf_avatar_profile_field');
add_action('edit_user_profile','sia_ancf_avatar_profile_field');
function sia_ancf_avatar_profile_save(int $user_id): void {
    if(class_exists('WP_User_Avatar_Functions')||!current_user_can('edit_user',$user_id)||!isset($_POST['sia_avatar_nonce'])||!is_string($_POST['sia_avatar_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sia_avatar_nonce'])),'sia_avatar_save_'.$user_id)){return;}
    $attachment=isset($_POST['sia_avatar_id'])?absint($_POST['sia_avatar_id']):0;global $wpdb;$key=$wpdb->get_blog_prefix().'user_avatar';
    if($attachment===0){delete_user_meta($user_id,$key);return;}if(wp_attachment_is_image($attachment)){update_user_meta($user_id,$key,$attachment);}
}
add_action('personal_options_update','sia_ancf_avatar_profile_save');
add_action('edit_user_profile_update','sia_ancf_avatar_profile_save');
