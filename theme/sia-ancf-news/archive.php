<?php get_header(); ?>
<div class="sia-archive-shell">
  <header class="archive-header">
    <h1><?php the_archive_title(); ?></h1>
    <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
  </header>

  <?php if (have_posts()) : ?>
    <div class="sia-archive-grid">
      <?php while (have_posts()) : the_post(); ?>
        <?php sia_ancf_news_render_card(get_the_ID()); ?>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <section class="sia-empty-state"><p><?php esc_html_e('No stories found in this section yet.', 'sia-ancf-news'); ?></p></section>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
