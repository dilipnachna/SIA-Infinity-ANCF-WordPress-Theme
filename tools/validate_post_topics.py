#!/usr/bin/env python3
"""Regression checks for article-end WordPress tag links."""
from pathlib import Path
root = Path(__file__).resolve().parents[1]
single = (root / "theme/sia-ancf-news/single.php").read_text(encoding="utf-8")
style = (root / "theme/sia-ancf-news/style.css").read_text(encoding="utf-8")
assert "$topics = get_the_tags($post_id);" in single
assert "if (!empty($topics) && !is_wp_error($topics))" in single
assert "foreach ($topics as $topic)" in single
assert "esc_url(get_tag_link($topic->term_id))" in single
assert "esc_html($topic->name)" in single
assert "esc_html_e('Topics', 'sia-ancf-news'); ?> :" in single
assert "aria-label=\"<?php esc_attr_e('Post topics'" in single
assert single.index("<?php the_content(); ?>") < single.index("class=\"sia-post-topics\"") < single.index("<?php comments_template(); ?>")
assert ".sia-post-topics__list" in style
assert "flex-wrap: wrap" in style.split(".sia-post-topics__list {")[1].split("}",1)[0]
assert "Version: " + (root / "THEME_VERSION").read_text(encoding="utf-8").strip() in style
print("PASS: tagged articles display linked Topics after content; untagged articles hide section; safe responsive markup")
