<?php get_header(); ?>
<?php $author = get_queried_object(); ?>
<div class="sia-archive-shell">
  <header class="archive-header">
    <p class="sia-eyebrow"><?php esc_html_e('Author', 'sia-ancf-news'); ?></p>
    <h1><?php echo esc_html($author->display_name ?? ''); ?></h1>
    <?php if (!empty($author->description)) : ?>
      <div class="archive-description"><p><?php echo esc_html($author->description); ?></p></div>
    <?php endif; ?>
  </header>

  <?php if (have_posts()) : ?>
    <div class="sia-archive-grid">
      <?php while (have_posts()) : the_post(); ?>
        <?php sia_ancf_news_render_card(get_the_ID()); ?>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <section class="sia-empty-state"><p><?php esc_html_e('No published stories found for this author.', 'sia-ancf-news'); ?></p></section>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
