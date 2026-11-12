#!/usr/bin/env python3
"""Regression: newsroom thumbnails must not crop embedded text/graphics."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
theme = ROOT / "theme" / "sia-ancf-news"
php = (theme / "functions.php").read_text(encoding="utf-8")
css = (theme / "style.css").read_text(encoding="utf-8")
single = (theme / "single.php").read_text(encoding="utf-8")
page = (theme / "page.php").read_text(encoding="utf-8")
assert "add_image_size('sia-news-hero', 1200, 675, false)" in php
assert "add_image_size('sia-news-card', 720, 405, false)" in php
assert "add_image_size('sia-news-thumb', 360, 203, false)" in php
assert "'sia-news-hero', 'sia-news-card' => 'large'" in php
assert "'sia-news-thumb' => 'medium_large'" in php
assert "get_the_post_thumbnail($post_id, $display_size" in php
assert "the_post_thumbnail('large'" in single
assert "the_post_thumbnail('large'" in page
for selector in (
    ".sia-lead-story__media .sia-media-placeholder",
    ".sia-home-feature__media .sia-media-placeholder",
    ".sia-story-card--standard .sia-media-placeholder",
    ".sia-story-card--compact .sia-media-placeholder",
):
    assert selector in css, selector
    declaration = css.split(selector, 1)[1].split("}", 1)[0]
    assert "object-fit: contain" in declaration, selector
assert ".sia-media:hover img { transform:" not in css
assert "Version: " + (ROOT / "THEME_VERSION").read_text(encoding="utf-8").strip() in css
print("PASS: image edges/text preserved on hero, homepage, cards, related stories and single pages")
