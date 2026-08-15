<?php get_header(); ?>
<header class="archive-header"><h1><?php printf(esc_html__('Search results for: %s', 'sia-ancf-news'), esc_html(get_search_query())); ?></h1></header>
<div class="sia-grid">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
<article <?php post_class('sia-card'); ?>>
  <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
  <?php the_excerpt(); ?>
</article>
<?php endwhile; else : ?><p><?php esc_html_e('No results found.', 'sia-ancf-news'); ?></p><?php endif; ?>
</div>
<?php the_posts_pagination(); ?>
<?php get_footer(); ?>
