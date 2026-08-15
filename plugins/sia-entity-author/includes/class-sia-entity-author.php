<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SIA_Entity_Author {
    public static function boot(): void {
        add_action('show_user_profile', [self::class, 'render_fields']);
        add_action('edit_user_profile', [self::class, 'render_fields']);
        add_action('personal_options_update', [self::class, 'save_fields']);
        add_action('edit_user_profile_update', [self::class, 'save_fields']);
    }

    public static function render_fields(WP_User $user): void {
        $type = get_user_meta($user->ID, '_sia_entity_type', true) ?: 'person';
        $role = get_user_meta($user->ID, '_sia_role_label', true);
        $expertise = get_user_meta($user->ID, '_sia_expertise', true);
        $same_as = get_user_meta($user->ID, '_sia_same_as', true);
        wp_nonce_field('sia_entity_author_save', 'sia_entity_author_nonce');
        ?>
        <h2>SIA ANCF Author / Entity</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="sia_entity_type">Entity type</label></th>
                <td><select name="sia_entity_type" id="sia_entity_type">
                    <option value="person" <?php selected($type, 'person'); ?>>Person</option>
                    <option value="organization" <?php selected($type, 'organization'); ?>>Organization / News Desk</option>
                </select></td>
            </tr>
            <tr>
                <th><label for="sia_role_label">Role</label></th>
                <td><input class="regular-text" name="sia_role_label" id="sia_role_label" value="<?php echo esc_attr($role); ?>" placeholder="Reporter, Editor-in-Chief, News Desk"></td>
            </tr>
            <tr>
                <th><label for="sia_expertise">Expertise</label></th>
                <td><input class="regular-text" name="sia_expertise" id="sia_expertise" value="<?php echo esc_attr($expertise); ?>" placeholder="Jaisalmer, Rajasthan, AI, Travel"><p class="description">Comma-separated descriptive expertise only; no ranking claims.</p></td>
            </tr>
            <tr>
                <th><label for="sia_same_as">Identity URLs</label></th>
                <td><textarea class="large-text" rows="3" name="sia_same_as" id="sia_same_as" placeholder="One HTTPS URL per line"><?php echo esc_textarea($same_as); ?></textarea></td>
            </tr>
        </table>
        <p><em>v0.1 stores identity metadata only. Existing author-page/schema output remains untouched.</em></p>
        <?php
    }

    public static function save_fields(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['sia_entity_author_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sia_entity_author_nonce'])), 'sia_entity_author_save')) {
            return;
        }

        $type = isset($_POST['sia_entity_type']) ? sanitize_key(wp_unslash($_POST['sia_entity_type'])) : 'person';
        if (!in_array($type, ['person', 'organization'], true)) {
            $type = 'person';
        }
        update_user_meta($user_id, '_sia_entity_type', $type);
        update_user_meta($user_id, '_sia_role_label', isset($_POST['sia_role_label']) ? sanitize_text_field(wp_unslash($_POST['sia_role_label'])) : '');
        update_user_meta($user_id, '_sia_expertise', isset($_POST['sia_expertise']) ? sanitize_text_field(wp_unslash($_POST['sia_expertise'])) : '');

        $urls = isset($_POST['sia_same_as']) ? preg_split('/\R/', wp_unslash($_POST['sia_same_as'])) : [];
        $urls = array_values(array_filter(array_map(static function ($url) {
            $url = esc_url_raw(trim((string) $url), ['https']);
            return $url ?: null;
        }, $urls ?: [])));
        update_user_meta($user_id, '_sia_same_as', implode("\n", $urls));
    }
}
