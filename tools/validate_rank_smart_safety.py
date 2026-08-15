#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugins" / "rank-smart"

forbidden = {
    "add_action('wp_head'": "must not emit front-end SEO tags",
    'add_action("wp_head"': "must not emit front-end SEO tags",
    "add_action('template_redirect'": "must not redirect requests",
    'add_action("template_redirect"': "must not redirect requests",
    "add_filter('wp_robots'": "must not take robots authority",
    'add_filter("wp_robots"': "must not take robots authority",
    "add_filter('redirect_canonical'": "must not take canonical redirect authority",
    'add_filter("redirect_canonical"': "must not take canonical redirect authority",
    "add_filter('pre_get_document_title'": "must not take title authority",
    'add_filter("pre_get_document_title"': "must not take title authority",
    "wp_safe_redirect(": "must not redirect requests",
    "wp_redirect(": "must not redirect requests",
    "wp_update_post(": "must not rewrite posts in read-only mode",
    "wp_delete_post(": "must not delete posts in read-only mode",
    "wp_insert_post(": "must not create posts in read-only mode",
}

errors = []
for path in sorted(PLUGIN.rglob("*.php")):
    text = path.read_text(encoding="utf-8")
    for needle, reason in forbidden.items():
        if needle in text:
            errors.append(f"{path.relative_to(ROOT)}: found {needle!r} — {reason}")

if errors:
    raise SystemExit("\n".join(errors))

print("Rank Smart read-only safety invariant validated")
