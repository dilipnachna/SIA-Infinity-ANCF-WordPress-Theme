<?php
get_header();

$article_root = get_category_by_slug('article');
$news_root_id = function_exists('sia_ancf_news_category_id') ? sia_ancf_news_category_id() : 0;
$news_root = $news_root_id > 0 ? get_term($news_root_id, 'category') : null;

$stream_ids = static function (string $stream, int $limit, array $exclude = [], int $category_id = 0): array {
    if ($stream === 'news') {
        $root_id = function_exists('sia_ancf_news_category_id') ? sia_ancf_news_category_id() : 0;
    } else {
        $article = get_category_by_slug('article');
        $root_id = $article instanceof WP_Term ? (int) $article->term_id : 0;
    }
    $term_id = $category_id > 0 ? $category_id : $root_id;
    if ($term_id <= 0) {
        return [];
    }
    $candidates = sia_ancf_news_home_query_ids([
        'posts_per_page' => max(30, $limit * 8),
        'post__not_in' => array_values(array_unique(array_map('intval', $exclude))),
        'tax_query' => [[
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => [$term_id],
            'include_children' => true,
        ]],
    ]);
    $filtered = array_values(array_filter($candidates, static function (int $post_id) use ($stream): bool {
        $is_news = sia_ancf_post_is_news_category($post_id);
        return $stream === 'news' ? $is_news : !$is_news;
    }));
    return array_slice($filtered, 0, max(0, $limit));
};

$hero_article_ids = $stream_ids('article', 3);
$hero_news_ids = $stream_ids('news', 3);
$hero_ids = [];
for ($index = 0; $index < 3; $index++) {
    if (isset($hero_article_ids[$index])) { $hero_ids[] = ['id' => $hero_article_ids[$index], 'stream' => 'article']; }
    if (isset($hero_news_ids[$index])) { $hero_ids[] = ['id' => $hero_news_ids[$index], 'stream' => 'news']; }
}
$hero_used = array_column($hero_ids, 'id');
$latest_article_ids = $stream_ids('article', 5, $hero_used);
$latest_news_ids = $stream_ids('news', 5, array_merge($hero_used, $latest_article_ids));
$used_ids = array_values(array_unique(array_merge($hero_used, $latest_article_ids, $latest_news_ids)));

$render_feature = static function (int $post_id, string $stream): void {
    $category = sia_ancf_news_post_category($post_id);
    $excerpt = trim((string) get_the_excerpt($post_id));
    echo '<article class="sia-home-feature" data-content-stream="' . esc_attr($stream) . '">';
    sia_ancf_news_thumbnail($post_id, 'sia-news-hero', 'sia-home-feature__media');
    echo '<div class="sia-home-feature__body"><span class="sia-stream-label">' . esc_html($stream === 'news' ? __('Latest News', 'sia-ancf-news') : __('Latest Article', 'sia-ancf-news')) . '</span>';
    if ($category) {
        echo '<a class="sia-eyebrow" href="' . esc_url(get_category_link($category)) . '">' . esc_html($category->name) . '</a>';
    }
    echo '<h2><a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a></h2>';
    if ($excerpt !== '') {
        echo '<p>' . esc_html(wp_trim_words($excerpt, 30)) . '</p>';
    }
    echo '<div class="sia-meta">' . wp_kses_post(sia_ancf_news_post_meta($post_id)) . '</div></div></article>';
};

$direct_sections = static function ($root): array {
    if (!$root instanceof WP_Term) {
        return [];
    }
    $terms = get_terms([
        'taxonomy' => 'category',
        'parent' => (int) $root->term_id,
        'hide_empty' => false,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);
    if (is_wp_error($terms)) {
        return [];
    }
    return array_values(array_filter($terms, static fn($term): bool => $term instanceof WP_Term && sia_ancf_term_has_indexable_posts($term)));
};
?>

<div class="sia-shell sia-homepage">
  <?php if ($hero_ids) : ?>
    <section class="sia-home-hero" aria-labelledby="sia-home-hero-title">
      <div class="sia-section-heading sia-home-hero__heading">
        <h1 id="sia-home-hero-title"><?php esc_html_e('Latest News & Articles', 'sia-ancf-news'); ?></h1>
      </div>
      <div class="sia-home-hero__leads">
        <?php if (isset($hero_ids[0])) { $render_feature((int) $hero_ids[0]['id'], (string) $hero_ids[0]['stream']); } ?>
        <?php if (isset($hero_ids[1])) { $render_feature((int) $hero_ids[1]['id'], (string) $hero_ids[1]['stream']); } ?>
      </div>
      <?php if (count($hero_ids) > 2) : ?>
        <div class="sia-home-hero__more">
          <?php foreach (array_slice($hero_ids, 2) as $story) : ?>
            <div data-content-stream="<?php echo esc_attr((string) $story['stream']); ?>">
              <?php sia_ancf_news_render_card((int) $story['id'], 'compact'); ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php else : ?>
    <section class="sia-empty-state"><h1><?php echo esc_html(get_bloginfo('name')); ?></h1><p><?php esc_html_e('Publish an Article or News post to build the homepage.', 'sia-ancf-news'); ?></p></section>
  <?php endif; ?>

  <div class="sia-home-latest-columns">
    <section class="sia-home-latest-column" data-content-stream="article" aria-labelledby="sia-latest-article-title">
      <div class="sia-section-heading">
        <h2 id="sia-latest-article-title"><?php esc_html_e('Latest Articles', 'sia-ancf-news'); ?></h2>
        <?php if ($article_root instanceof WP_Term) : ?><a href="<?php echo esc_url(get_term_link($article_root)); ?>"><?php esc_html_e('View all', 'sia-ancf-news'); ?></a><?php endif; ?>
      </div>
      <div class="sia-home-latest-list">
        <?php foreach ($latest_article_ids as $post_id) : sia_ancf_news_render_card((int) $post_id, 'compact'); endforeach; ?>
      </div>
    </section>

    <section class="sia-home-latest-column" data-content-stream="news" aria-labelledby="sia-latest-news-title">
      <div class="sia-section-heading">
        <h2 id="sia-latest-news-title"><?php esc_html_e('Latest News', 'sia-ancf-news'); ?></h2>
        <?php if ($news_root instanceof WP_Term) : ?><a href="<?php echo esc_url(get_term_link($news_root)); ?>"><?php esc_html_e('View all', 'sia-ancf-news'); ?></a><?php endif; ?>
      </div>
      <div class="sia-home-latest-list">
        <?php foreach ($latest_news_ids as $post_id) : sia_ancf_news_render_card((int) $post_id, 'compact'); endforeach; ?>
      </div>
    </section>
  </div>

  <?php foreach ([['root' => $article_root, 'stream' => 'article'], ['root' => $news_root, 'stream' => 'news']] as $group) : ?>
    <?php foreach ($direct_sections($group['root']) as $section_category) : ?>
      <?php
      $fresh_ids = $stream_ids((string) $group['stream'], 3, $used_ids, (int) $section_category->term_id);
      $all_ids = $stream_ids((string) $group['stream'], 3, [], (int) $section_category->term_id);
      $section_ids = sia_ancf_news_fill_ids($fresh_ids, $all_ids, 3);
      if (!$section_ids) { continue; }
      $used_ids = array_values(array_unique(array_merge($used_ids, $section_ids)));
      ?>
      <section class="sia-home-section sia-home-subcategory" data-content-stream="<?php echo esc_attr((string) $group['stream']); ?>" data-category-id="<?php echo (int) $section_category->term_id; ?>">
        <div class="sia-section-heading">
          <h2><?php echo esc_html($section_category->name); ?></h2>
          <a href="<?php echo esc_url(get_term_link($section_category)); ?>"><?php esc_html_e('View all', 'sia-ancf-news'); ?></a>
        </div>
        <div class="sia-news-grid sia-news-grid--three">
          <?php foreach ($section_ids as $post_id) : sia_ancf_news_render_card((int) $post_id); endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>

<?php get_footer(); ?>
