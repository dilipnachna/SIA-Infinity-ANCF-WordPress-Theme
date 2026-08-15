<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
  <div class="sia-shell">
    <?php if (has_custom_logo()) { the_custom_logo(); } ?>
    <a class="site-title" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
    <?php if (has_nav_menu('primary')) { wp_nav_menu(['theme_location' => 'primary', 'container' => 'nav']); } ?>
  </div>
</header>
<main class="sia-main sia-shell">
