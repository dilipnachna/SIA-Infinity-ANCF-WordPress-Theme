<?php
if (!defined('ABSPATH') || post_password_required()) {
    return;
}
?>
<section id="comments" class="sia-comments" aria-label="<?php esc_attr_e('Comments', 'sia-ancf-news'); ?>">
    <?php if (have_comments()) : ?>
        <h2><?php esc_html_e('Comments', 'sia-ancf-news'); ?></h2>
        <ol class="comment-list"><?php wp_list_comments(['style' => 'ol', 'short_ping' => true]); ?></ol>
        <?php the_comments_pagination(); ?>
    <?php endif; ?>
    <?php if (comments_open()) : ?>
        <?php comment_form(); ?>
    <?php elseif (get_comments_number()) : ?>
        <p><?php esc_html_e('Comments are closed.', 'sia-ancf-news'); ?></p>
    <?php endif; ?>
</section>
