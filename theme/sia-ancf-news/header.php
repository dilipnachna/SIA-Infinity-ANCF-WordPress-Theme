<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary-content"><?php esc_html_e('Skip to content', 'sia-ancf-news'); ?></a>

<header class="site-header">
  <div class="sia-topbar">
    <div class="sia-shell sia-topbar__inner">
      <span><?php echo esc_html(wp_date(get_option('date_format'))); ?></span>
      <?php if (has_nav_menu('utility')) : ?>
        <?php wp_nav_menu([
            'theme_location' => 'utility',
            'container'      => 'nav',
            'container_class'=> 'utility-navigation',
            'menu_class'     => 'utility-menu',
            'depth'          => 1,
            'fallback_cb'    => false,
        ]); ?>
      <?php else : ?>
        <a href="<?php echo esc_url(home_url('/#latest-news')); ?>"><?php esc_html_e('Latest News', 'sia-ancf-news'); ?></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="sia-brandbar">
    <div class="sia-shell sia-brandbar__inner">
      <div class="sia-brand">
        <?php if (has_custom_logo()) : ?>
          <?php the_custom_logo(); ?>
        <?php else : ?>
          <a class="site-title" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
        <?php endif; ?>
        <?php if (get_bloginfo('description')) : ?>
          <p class="site-description"><?php bloginfo('description'); ?></p>
        <?php endif; ?>
      </div>

      <div class="sia-header-actions">
        <details class="sia-search">
          <summary aria-label="<?php esc_attr_e('Open search', 'sia-ancf-news'); ?>"><?php esc_html_e('Search', 'sia-ancf-news'); ?></summary>
          <div class="sia-search__panel"><?php get_search_form(); ?></div>
        </details>
        <button class="sia-menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
          <span class="sia-menu-toggle__icon" aria-hidden="true"></span>
          <span><?php esc_html_e('Menu', 'sia-ancf-news'); ?></span>
        </button>
      </div>
    </div>
  </div>

  <nav id="site-navigation" class="primary-navigation" aria-label="<?php esc_attr_e('Primary navigation', 'sia-ancf-news'); ?>">
    <div class="sia-shell">
      <?php wp_nav_menu([
          'theme_location' => 'primary',
          'container'      => false,
          'menu_class'     => 'primary-menu',
          'fallback_cb'    => 'sia_ancf_news_fallback_menu',
          'depth'          => 2,
      ]); ?>
    </div>
  </nav>
</header>

<main id="primary-content" class="sia-main">
