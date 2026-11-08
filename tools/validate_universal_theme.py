#!/usr/bin/env python3
"""Portable theme smoke checks (not a substitute for WordPress integration tests)."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
theme = root / "theme" / "sia-ancf-news"
front = (theme / "front-page.php").read_text(encoding="utf-8")
seo = (theme / "inc/native-seo.php").read_text(encoding="utf-8")
readme = (root / "README.md").read_text(encoding="utf-8")
assert "SIA ANCF" in readme
assert "theme/sia-ancf-news/" in readme
assert "$generic_mode =" in front and "'generic'" in front
assert "$generic_ids = $stream_ids('generic'" in front
assert "$section_groups" in front and "sia_ancf_news_section_categories" in front
assert "if ($term_id > 0)" in front
assert "post__not_in" in front and "array_unique" in front
assert "sia_ancf_news_category_id" in seo and "apply_filters" in seo
assert "sia_ancf_all_posts_are_news" in seo
assert "sia_ancf_news_root_category" in seo
settings = (theme / "inc/native-theme-settings.php").read_text(encoding="utf-8")
assert "customize_register" in settings and "sia_ancf_article_root_category" in settings
sitemap = (theme / "inc/native-sitemaps.php").read_text(encoding="utf-8")
assert "sia_ancf_all_posts_are_news" in sitemap
for file in theme.rglob("*"):
    if file.is_file() and file.suffix.lower() in {".php", ".js", ".css", ".po", ".md"}:
        data = file.read_text(encoding="utf-8")
        assert not re.search(r"(?i)jaisalmernews\.com|hindi\.jaisalmernews", data), file
print("Universal-theme portability smoke checks passed")
