<?php
/** WordPress Customizer settings for portable newsroom categories. */
if (!defined('ABSPATH')) { exit; }

function sia_ancf_article_category_term(): ?WP_Term {
    $selected = absint(get_option('sia_ancf_article_root_category', 0));
    $term = $selected > 0 ? get_term($selected, 'category') : get_category_by_slug('article');
    return $term instanceof WP_Term && !is_wp_error($term) ? $term : null;
}

add_action('customize_register', static function ($customizer): void {
    if (!$customizer instanceof WP_Customize_Manager) {
        return;
    }
    $customizer->add_section('sia_ancf_newsroom', [
        'title' => __('Newsroom Categories', 'sia-ancf-news'),
        'description' => __('Optional category selection for sites with different taxonomies.', 'sia-ancf-news'),
        'priority' => 40,
    ]);
    $categories = get_categories(['hide_empty' => false, 'taxonomy' => 'category']);
    $choices = [0 => __('Auto-detect', 'sia-ancf-news')];
    if (is_array($categories)) {
        foreach ($categories as $category) {
            if ($category instanceof WP_Term) {
                $choices[(int) $category->term_id] = $category->name;
            }
        }
    }

    foreach ([
        'sia_ancf_news_root_category' => __('News category', 'sia-ancf-news'),
        'sia_ancf_article_root_category' => __('Article category', 'sia-ancf-news'),
    ] as $option => $label) {
        $customizer->add_setting($option, [
            'type' => 'option',
            'default' => 0,
            'sanitize_callback' => 'absint',
            'transport' => 'refresh',
        ]);
        $customizer->add_control($option, [
            'section' => 'sia_ancf_newsroom',
            'type' => 'select',
            'choices' => $choices,
            'label' => $label,
        ]);
    }

    $customizer->add_setting('sia_ancf_all_posts_are_news', [
        'type' => 'option',
        'default' => false,
        'sanitize_callback' => static fn($value): bool => (bool) $value,
        'transport' => 'refresh',
    ]);
    $customizer->add_control('sia_ancf_all_posts_are_news', [
        'section' => 'sia_ancf_newsroom',
        'type' => 'checkbox',
        'label' => __('News-only website: treat all posts as news', 'sia-ancf-news'),
        'description' => __('Only enable if all published posts are news. This also controls NewsArticle schema and the news sitemap.', 'sia-ancf-news'),
    ]);
});
