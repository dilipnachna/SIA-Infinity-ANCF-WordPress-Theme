</main>
<footer class="site-footer">
  <div class="sia-shell sia-footer-grid">
    <section>
      <h2 class="sia-footer-title"><?php bloginfo('name'); ?></h2>
      <?php if (get_bloginfo('description')) : ?>
        <p><?php bloginfo('description'); ?></p>
      <?php endif; ?>
    </section>

    <section>
      <h2 class="sia-footer-heading"><?php esc_html_e('Sections', 'sia-ancf-news'); ?></h2>
      <ul class="sia-footer-links">
        <?php foreach (sia_ancf_news_section_categories(6) as $category) : ?>
          <li><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section>
      <h2 class="sia-footer-heading"><?php esc_html_e('Information', 'sia-ancf-news'); ?></h2>
      <?php if (has_nav_menu('footer')) : ?>
        <?php wp_nav_menu([
            'theme_location' => 'footer',
            'container'      => false,
            'menu_class'     => 'sia-footer-links',
            'depth'          => 1,
            'fallback_cb'    => false,
        ]); ?>
      <?php else : ?>
        <ul class="sia-footer-links">
          <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sia-ancf-news'); ?></a></li>
          <li><a href="<?php echo esc_url(get_feed_link()); ?>"><?php esc_html_e('RSS Feed', 'sia-ancf-news'); ?></a></li>
        </ul>
      <?php endif; ?>
    </section>
  </div>
  <div class="sia-shell sia-footer-bottom">
    <span>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span>
    <span><?php esc_html_e('Independent publishing powered by SIA Infinity ANCF.', 'sia-ancf-news'); ?></span>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
