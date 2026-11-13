#!/usr/bin/env python3
"""Static contract checks for autonomous robots/LLM discovery files."""
from pathlib import Path

root = Path(__file__).resolve().parents[1]
theme = root / 'theme' / 'sia-ancf-news'
code = (theme / 'inc' / 'native-discovery.php').read_text(encoding='utf-8')
functions = (theme / 'functions.php').read_text(encoding='utf-8')
version = (theme / 'style.css').read_text(encoding='utf-8')
assert "require_once __DIR__ . '/inc/native-discovery.php';" in functions
assert "add_filter('robots_txt'" in code
assert 'User-agent: *' in code
assert 'Disallow: /wp-admin/' in code
assert 'Allow: /wp-admin/admin-ajax.php' in code
assert "if (!$public)" in code and 'Disallow: /' in code
assert "add_action('do_robots'" in code and "sia_ancf_discovery_robots_content" in code
assert "sia_ancf_discovery_external_seo_present()" in code
assert 'Sitemap: ' in code and 'sia_ancf_discovery_sitemap_url()' in code
assert "sia_ancf_discovery_external_seo_present()" in code
assert "add_action('parse_request'" in code
assert "in_array($filename, ['llms.txt', 'llms-full.txt'], true)" in code
assert "get_option('blog_public')" in code
assert "'post_status'         => 'publish'" in code
assert "'has_password'       => false" in code
assert 'sia_ancf_discovery_is_public_post' in code
assert 'sia_ancf_post_is_noindex' in code
assert "'GET', 'HEAD'" in code
assert "status_header(200);" in code
assert "nocache_headers();" in code
assert "get_page_by_path" in code
assert "get_the_title" in code and "get_permalink" in code
assert "sia_ancf_discovery_term_path" in code
assert "sia_ancf_discovery_full_markdown" in code
assert "sia_ancf_discovery_post_markdown" in code
assert "LIBXML_NONET" in code
assert "llms-full.txt" in code
assert "strip_shortcodes" in code
assert "wp_get_post_categories" in code
assert "'hide_empty' => false" in code
assert "'posts_per_page'     => $batch_size" in code
assert "'paged'              => $page_number" in code
assert "count($posts) === $batch_size" in code
assert "count($latest) >= 8" not in code
assert "Version: " + (root / 'THEME_VERSION').read_text(encoding='utf8').strip() in version
print('PASS: robots.txt and llms.txt virtual endpoints, public-only links, sitemap, privacy and SEO-provider safety')
