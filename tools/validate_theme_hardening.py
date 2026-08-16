#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FUNCTIONS = (ROOT / "theme" / "sia-ancf-news" / "functions.php").read_text(encoding="utf-8")
FRONT = (ROOT / "theme" / "sia-ancf-news" / "front-page.php").read_text(encoding="utf-8")
SINGLE = (ROOT / "theme" / "sia-ancf-news" / "single.php").read_text(encoding="utf-8")
PAGE = (ROOT / "theme" / "sia-ancf-news" / "page.php").read_text(encoding="utf-8")

checks = {
    "primary menu drives section ordering": "wp_get_nav_menu_items" in FUNCTIONS and "menu_item_parent" in FUNCTIONS,
    "posts index helper exists": "function sia_ancf_news_posts_index_url" in FUNCTIONS,
    "same-day modified detection uses timestamps": "get_post_modified_time('U'" in FUNCTIONS and "get_post_time('U'" in FUNCTIONS,
    "editor stylesheet enabled": "add_editor_style('style.css')" in FUNCTIONS,
    "homepage content surfaces deduplicate": "used_content_ids" in FRONT and "stream_candidates" in FRONT,
    "latest view-all uses posts index helper": "sia_ancf_news_posts_index_url()" in FRONT,
    "single related surfaces are split": "related_pool" in SINGLE and "sidebar_ids" in SINGLE,
    "single hero uses newsroom image size": "the_post_thumbnail('sia-news-hero'" in SINGLE,
    "single supports paginated content": "wp_link_pages();" in SINGLE,
    "page hero uses newsroom image size": "the_post_thumbnail('sia-news-hero'" in PAGE,
    "page supports paginated content": "wp_link_pages();" in PAGE,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    raise SystemExit("Theme hardening validation failed:\n- " + "\n- ".join(failed))

# Theme-only hardening must not silently acquire SEO authority.
for forbidden in [
    "rel=\"canonical\"",
    "wp_robots",
    "template_redirect",
    "wp_redirect(",
    "wp_safe_redirect(",
    "register_post_meta('rank_math",
]:
    if forbidden in FUNCTIONS or forbidden in FRONT or forbidden in SINGLE or forbidden in PAGE:
        raise SystemExit(f"Theme hardening introduced forbidden SEO authority token: {forbidden}")

print("Theme hardening validation passed")
