<?php get_header(); ?>
<?php $author = get_queried_object(); ?>
<header class="archive-header">
  <h1><?php echo esc_html($author->display_name ?? ''); ?></h1>
  <?php if (!empty($author->description)) : ?><div class="archive-description"><p><?php echo esc_html($author->description); ?></p></div><?php endif; ?>
</header>
<div class="sia-grid">
<?php while (have_posts()) : the_post(); ?>
<article <?php post_class('sia-card'); ?>>
  <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
  <div class="sia-meta"><?php echo esc_html(get_the_date()); ?></div>
  <?php the_excerpt(); ?>
</article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination(); ?>
<?php get_footer(); ?>
