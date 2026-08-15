<?php
get_header();

$lead_ids = sia_ancf_news_query_ids([
    'posts_per_page' => 5,
]);

$lead_id = $lead_ids[0] ?? 0;
$top_ids = array_slice($lead_ids, 1, 4);
$latest_ids = sia_ancf_news_query_ids([
    'posts_per_page' => 8,
    'post__not_in'   => $lead_id ? [$lead_id] : [],
]);
$section_categories = sia_ancf_news_section_categories(4);
?>

<div class="sia-shell">
  <?php if ($lead_id) : ?>
    <section class="sia-latest-strip" aria-label="<?php esc_attr_e('Latest headlines', 'sia-ancf-news'); ?>">
      <strong><?php esc_html_e('Latest', 'sia-ancf-news'); ?></strong>
      <div class="sia-latest-strip__items">
        <?php foreach (array_slice($lead_ids, 0, 4) as $headline_id) : ?>
          <a href="<?php echo esc_url(get_permalink($headline_id)); ?>"><?php echo esc_html(get_the_title($headline_id)); ?></a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="sia-lead-layout" aria-label="<?php esc_attr_e('Top stories', 'sia-ancf-news'); ?>">
      <article class="sia-lead-story">
        <?php sia_ancf_news_thumbnail($lead_id, 'sia-news-hero', 'sia-lead-story__media'); ?>
        <div class="sia-lead-story__body">
          <?php $lead_category = sia_ancf_news_post_category($lead_id); ?>
          <?php if ($lead_category) : ?>
            <a class="sia-eyebrow" href="<?php echo esc_url(get_category_link($lead_category)); ?>"><?php echo esc_html($lead_category->name); ?></a>
          <?php endif; ?>
          <h1><a href="<?php echo esc_url(get_permalink($lead_id)); ?>"><?php echo esc_html(get_the_title($lead_id)); ?></a></h1>
          <?php $lead_excerpt = trim((string) get_the_excerpt($lead_id)); ?>
          <?php if ($lead_excerpt !== '') : ?>
            <p><?php echo esc_html(wp_trim_words($lead_excerpt, 34)); ?></p>
          <?php endif; ?>
          <div class="sia-meta"><?php echo wp_kses_post(sia_ancf_news_post_meta($lead_id)); ?></div>
        </div>
      </article>

      <div class="sia-top-stories">
        <div class="sia-section-heading sia-section-heading--compact">
          <h2><?php esc_html_e('Top Stories', 'sia-ancf-news'); ?></h2>
        </div>
        <?php foreach ($top_ids as $top_id) : ?>
          <?php sia_ancf_news_render_card($top_id, 'compact'); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else : ?>
    <section class="sia-empty-state">
      <h1><?php bloginfo('name'); ?></h1>
      <p><?php esc_html_e('Your newsroom is ready. Publish your first story to build the front page.', 'sia-ancf-news'); ?></p>
    </section>
  <?php endif; ?>

  <?php if ($latest_ids) : ?>
    <section id="latest-news" class="sia-home-section">
      <div class="sia-section-heading">
        <h2><?php esc_html_e('Latest News', 'sia-ancf-news'); ?></h2>
        <a href="<?php echo esc_url(get_post_type_archive_link('post') ?: home_url('/')); ?>"><?php esc_html_e('View all', 'sia-ancf-news'); ?></a>
      </div>
      <div class="sia-news-grid sia-news-grid--four">
        <?php foreach (array_slice($latest_ids, 0, 4) as $latest_id) : ?>
          <?php sia_ancf_news_render_card($latest_id); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php foreach ($section_categories as $section_category) : ?>
    <?php
    $section_ids = sia_ancf_news_query_ids([
        'posts_per_page' => 4,
        'cat'            => (int) $section_category->term_id,
    ]);
    if (!$section_ids) {
        continue;
    }
    ?>
    <section class="sia-home-section sia-category-section">
      <div class="sia-section-heading">
        <h2><?php echo esc_html($section_category->name); ?></h2>
        <a href="<?php echo esc_url(get_category_link($section_category)); ?>"><?php esc_html_e('More stories', 'sia-ancf-news'); ?></a>
      </div>
      <div class="sia-news-grid sia-news-grid--four">
        <?php foreach ($section_ids as $section_id) : ?>
          <?php sia_ancf_news_render_card($section_id); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if (count($latest_ids) > 4) : ?>
    <section class="sia-home-section sia-latest-stream">
      <div class="sia-section-heading">
        <h2><?php esc_html_e('More Latest', 'sia-ancf-news'); ?></h2>
      </div>
      <div class="sia-stream-list">
        <?php foreach (array_slice($latest_ids, 4) as $stream_id) : ?>
          <?php sia_ancf_news_render_card($stream_id, 'compact'); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
