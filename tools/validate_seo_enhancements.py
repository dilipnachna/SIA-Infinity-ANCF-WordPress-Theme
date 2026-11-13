from pathlib import Path
root=Path(__file__).resolve().parents[1]
theme=root/'theme/sia-ancf-news'
f=(theme/'functions.php').read_text('utf8')
p=(theme/'inc/native-seo-enhancements.php').read_text('utf8')
n=(theme/'inc/native-seo.php').read_text('utf8')
assert "require_once __DIR__ . '/inc/native-seo-enhancements.php';" in f
assert "add_filter('sia_publisher_schema_organization'" in p
assert "get_theme_mod('custom_logo'" in p
assert 'wp_attachment_is_image' in p
assert 'wp_get_attachment_metadata' in p
assert "add_filter('sia_publisher_schema_graph'" in p
assert "'@type' => 'BreadcrumbList'" in p and "'@type' => 'ListItem'" in p
assert "get_ancestors(" in p and 'get_post_ancestors' in p
assert "sia_ancf_news_post_category" in p
assert "current_user_can('edit_term'" in p
assert 'wp_verify_nonce' in p and 'sanitize_text_field' in p
assert "'edited_' . $taxonomy" in p
assert "get_term_meta($object->term_id, '_sia_term_seo_description'" in n
assert "get_term_meta($term->term_id, '_sia_term_seo_title'" in p
assert "sia_ancf_native_seo_enabled()" in p
assert ('Version: '+(root/'THEME_VERSION').read_text('utf8').strip()) in (theme/'style.css').read_text('utf8')
print('PASS: Publisher logo, BreadcrumbList, no-plugin category/tag SEO title and descriptions')
