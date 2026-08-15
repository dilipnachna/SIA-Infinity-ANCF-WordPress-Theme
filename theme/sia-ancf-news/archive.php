<?php get_header(); ?>
<header class="archive-header">
  <h1><?php the_archive_title(); ?></h1>
  <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
</header>
<div class="sia-grid">
<?php while (have_posts()) : the_post(); ?>
<article <?php post_class('sia-card'); ?>>
  <?php if (has_post_thumbnail()) : ?><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium_large', ['loading' => 'lazy']); ?></a><?php endif; ?>
  <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
  <div class="sia-meta"><?php echo esc_html(get_the_date()); ?> · <?php the_author_posts_link(); ?></div>
  <?php the_excerpt(); ?>
</article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination(); ?>
<?php get_footer(); ?>
