<?php get_header(); ?>
<div class="sia-single-shell">
<?php while (have_posts()) : the_post(); ?>
  <?php
  $post_id = get_the_ID();
  $category = sia_ancf_news_post_category($post_id);
  $related_pool = sia_ancf_news_related_ids($post_id, 8);
  $related_pool = apply_filters('sia_ancf_news_related_ids', $related_pool, $post_id, 8);
  $related_pool = is_array($related_pool) ? array_values(array_unique(array_filter(array_map('absint', $related_pool), static function (int $candidate_id) use ($post_id): bool {
      return $candidate_id > 0 && $candidate_id !== (int) $post_id;
  }))) : [];
  $related_pool = array_slice($related_pool, 0, 8);
  $sidebar_ids = array_slice($related_pool, 0, 4);
  $related_ids = array_slice($related_pool, 4, 3);
  $excerpt = trim((string) get_the_excerpt($post_id));
  $published = get_the_date('', $post_id);
  $modified_label = sia_ancf_news_modified_label($post_id);
  ?>
  <div class="sia-single-layout<?php echo $sidebar_ids ? '' : ' sia-single-layout--solo'; ?>">
    <article <?php post_class('sia-single-main'); ?>>
      <header class="entry-header">
        <?php if ($category) : ?>
          <a class="sia-eyebrow" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a>
        <?php endif; ?>
        <h1><?php the_title(); ?></h1>
        <?php if ($excerpt !== '') : ?>
          <p class="sia-dek"><?php echo esc_html($excerpt); ?></p>
        <?php endif; ?>
        <div class="sia-byline">
          <span><?php esc_html_e('By', 'sia-ancf-news'); ?> <?php the_author_posts_link(); ?></span>
          <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html($published); ?></time>
          <?php if ($modified_label !== '') : ?>
            <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html($modified_label); ?></time>
          <?php endif; ?>
        </div>
      </header>

      <?php if (has_post_thumbnail()) : ?>
        <figure class="sia-hero"><?php the_post_thumbnail('sia-news-hero', ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?></figure>
      <?php endif; ?>

      <div class="entry-content">
        <?php the_content(); ?>
        <?php wp_link_pages(); ?>
      </div>

      <aside class="author-box">
        <strong><?php the_author_posts_link(); ?></strong>
        <?php if (get_the_author_meta('description')) : ?>
          <p><?php echo esc_html(get_the_author_meta('description')); ?></p>
        <?php endif; ?>
      </aside>

      <?php if ($related_ids) : ?>
        <section class="sia-related">
          <div class="sia-section-heading"><h2><?php esc_html_e('Related Stories', 'sia-ancf-news'); ?></h2></div>
          <div class="sia-news-grid">
            <?php foreach ($related_ids as $related_id) : ?>
              <?php sia_ancf_news_render_card($related_id); ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </article>

    <?php if ($sidebar_ids) : ?>
      <aside class="sia-single-sidebar" aria-label="<?php esc_attr_e('More stories', 'sia-ancf-news'); ?>">
        <h2 class="sia-sidebar-label"><?php esc_html_e('More Stories', 'sia-ancf-news'); ?></h2>
        <div class="sia-sidebar-list">
          <?php foreach ($sidebar_ids as $sidebar_id) : ?>
            <?php sia_ancf_news_render_card($sidebar_id, 'compact'); ?>
          <?php endforeach; ?>
        </div>
      </aside>
    <?php endif; ?>
  </div>
<?php endwhile; ?>
</div>
<?php get_footer(); ?>
