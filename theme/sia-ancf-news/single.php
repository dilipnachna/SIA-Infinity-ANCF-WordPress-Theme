<?php get_header(); ?>
<div class="sia-single-shell">
<?php while (have_posts()) : the_post(); ?>
  <?php
  $post_id = get_the_ID();
  $category = sia_ancf_news_post_category($post_id);

  // The local four-signal ranker finds three topical matches. Sidebar output
  // cannot claim or duplicate those higher-priority Related Stories.
  $related_pool = sia_ancf_news_related_ids($post_id, 13);
  $related_pool = is_array($related_pool) ? array_values(array_unique(array_filter(array_map('absint', $related_pool), static function (int $candidate_id) use ($post_id): bool {
      return $candidate_id > 0 && $candidate_id !== (int) $post_id && get_post_type($candidate_id) === 'post' && get_post_status($candidate_id) === 'publish' && get_post_field('post_password', $candidate_id) === '';
  }))) : [];
  $related_pool = array_slice($related_pool, 0, 13);

  // Reserve the most relevant articles for the main Related Stories block
  // BEFORE the sidebar claims any IDs. The sidebar is a secondary surface.
  $related_fallback = array_slice($related_pool, 0, 3);
  $related_ids = apply_filters('sia_ancf_news_related_story_ids', $related_fallback, $post_id, 3, []);
  $related_ids = is_array($related_ids) ? array_values(array_unique(array_filter(array_map('absint', $related_ids), static function (int $candidate_id) use ($post_id): bool {
      return $candidate_id > 0 && $candidate_id !== (int) $post_id && get_post_type($candidate_id) === 'post' && get_post_status($candidate_id) === 'publish' && get_post_field('post_password', $candidate_id) === '';
  }))) : [];
  $related_ids = array_slice($related_ids, 0, 3);

  // More Stories may use a broader/fresher pool, but it must not duplicate
  // the higher-priority Related Stories recommendations.
  $sidebar_fallback = array_values(array_filter($related_pool, static function (int $candidate_id) use ($related_ids): bool {
      return !in_array($candidate_id, $related_ids, true);
  }));
  $sidebar_fallback = array_slice($sidebar_fallback, 0, 4);
  $sidebar_ids = apply_filters('sia_ancf_news_more_stories_ids', $sidebar_fallback, $post_id, 4, $related_ids);
  $sidebar_ids = is_array($sidebar_ids) ? array_values(array_unique(array_filter(array_map('absint', $sidebar_ids), static function (int $candidate_id) use ($post_id, $related_ids): bool {
      return $candidate_id > 0 && $candidate_id !== (int) $post_id && !in_array($candidate_id, $related_ids, true) && get_post_type($candidate_id) === 'post' && get_post_status($candidate_id) === 'publish' && get_post_field('post_password', $candidate_id) === '';
  }))) : [];
  $sidebar_ids = array_slice($sidebar_ids, 0, 4);

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
        <h1 style="font-size:clamp(1.75rem,2.35vw,2.6rem);line-height:1.18;letter-spacing:0;text-wrap:balance"><?php echo esc_html(get_the_title()); ?></h1>
        <?php do_action('sia_ancf_after_article_title'); ?>
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
        <figure class="sia-hero"><?php the_post_thumbnail('large', ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?></figure>
      <?php endif; ?>

      <div class="entry-content">
        <?php the_content(); ?>
        <?php wp_link_pages(); ?>
      </div>
      <?php do_action('sia_ancf_after_article_content'); ?>

      <?php
      $topics = get_the_tags($post_id);
      if (!empty($topics) && !is_wp_error($topics)) :
      ?>
        <nav class="sia-post-topics" aria-label="<?php esc_attr_e('Post topics', 'sia-ancf-news'); ?>">
          <span class="sia-post-topics__label"><?php esc_html_e('Topics', 'sia-ancf-news'); ?> :</span>
          <ul class="sia-post-topics__list">
            <?php foreach ($topics as $topic) : ?>
              <li><a href="<?php echo esc_url(get_tag_link($topic->term_id)); ?>"><?php echo esc_html($topic->name); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if (comments_open() || get_comments_number()) : ?>
        <?php comments_template(); ?>
      <?php endif; ?>

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
              <article class="sia-story-card sia-story-card--standard sia-story-card--related">
                <?php sia_ancf_news_thumbnail($related_id, 'sia-news-card'); ?>
                <div class="sia-story-card__body">
                  <h3 class="sia-story-card__title"><a href="<?php echo esc_url(get_permalink($related_id)); ?>"><?php echo esc_html(get_the_title($related_id)); ?></a></h3>
                </div>
              </article>
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
