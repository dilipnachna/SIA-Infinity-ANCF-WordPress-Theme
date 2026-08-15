<?php get_header(); ?>
<div class="sia-archive-shell">
  <header class="archive-header">
    <h1><?php echo is_home() ? esc_html__('Latest News', 'sia-ancf-news') : esc_html(get_bloginfo('name')); ?></h1>
  </header>

  <?php if (have_posts()) : ?>
    <div class="sia-archive-grid">
      <?php while (have_posts()) : the_post(); ?>
        <?php sia_ancf_news_render_card(get_the_ID()); ?>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <section class="sia-empty-state"><p><?php esc_html_e('No stories found.', 'sia-ancf-news'); ?></p></section>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
