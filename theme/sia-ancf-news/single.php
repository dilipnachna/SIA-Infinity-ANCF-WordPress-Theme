<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
<article <?php post_class(); ?>>
  <header class="entry-header">
    <h1><?php the_title(); ?></h1>
    <div class="sia-meta">By <?php the_author_posts_link(); ?> · <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date()); ?></time></div>
  </header>
  <?php if (has_post_thumbnail()) : ?><figure class="sia-hero"><?php the_post_thumbnail('full', ['loading' => 'eager', 'fetchpriority' => 'high']); ?></figure><?php endif; ?>
  <div class="entry-content"><?php the_content(); ?></div>
  <aside class="author-box">
    <strong><?php the_author_posts_link(); ?></strong>
    <?php if (get_the_author_meta('description')) : ?><p><?php echo esc_html(get_the_author_meta('description')); ?></p><?php endif; ?>
  </aside>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
