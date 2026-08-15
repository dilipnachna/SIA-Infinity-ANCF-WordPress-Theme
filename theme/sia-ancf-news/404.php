<?php get_header(); ?>
<section class="sia-404-shell">
  <p class="sia-eyebrow">404</p>
  <h1><?php esc_html_e('Story not found', 'sia-ancf-news'); ?></h1>
  <p><?php esc_html_e('The page may have moved, changed address, or no longer be available.', 'sia-ancf-news'); ?></p>
  <div style="max-width:520px;margin:24px auto 0"><?php get_search_form(); ?></div>
  <p><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Return to the newsroom', 'sia-ancf-news'); ?></a></p>
</section>
<?php get_footer(); ?>
