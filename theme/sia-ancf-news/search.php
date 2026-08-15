<?php get_header(); ?>
<div class="sia-archive-shell">
  <header class="archive-header">
    <p class="sia-eyebrow"><?php esc_html_e('Search', 'sia-ancf-news'); ?></p>
    <h1><?php printf(esc_html__('Results for “%s”', 'sia-ancf-news'), esc_html(get_search_query())); ?></h1>
  </header>

  <?php if (have_posts()) : ?>
    <div class="sia-archive-grid">
      <?php while (have_posts()) : the_post(); ?>
        <?php sia_ancf_news_render_card(get_the_ID()); ?>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <section class="sia-empty-state">
      <p><?php esc_html_e('No matching stories found. Try another search.', 'sia-ancf-news'); ?></p>
      <div style="max-width:520px;margin:18px auto 0"><?php get_search_form(); ?></div>
    </section>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
