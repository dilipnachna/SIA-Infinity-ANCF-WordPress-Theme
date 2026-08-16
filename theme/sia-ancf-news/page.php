<?php get_header(); ?>
<div class="sia-page-shell">
<?php while (have_posts()) : the_post(); ?>
  <article <?php post_class('sia-page-article'); ?>>
    <header class="entry-header">
      <h1><?php the_title(); ?></h1>
    </header>
    <?php if (has_post_thumbnail()) : ?>
      <figure class="sia-hero"><?php the_post_thumbnail('sia-news-hero', ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?></figure>
    <?php endif; ?>
    <div class="entry-content">
      <?php the_content(); ?>
      <?php wp_link_pages(); ?>
    </div>
  </article>
<?php endwhile; ?>
</div>
<?php get_footer(); ?>
