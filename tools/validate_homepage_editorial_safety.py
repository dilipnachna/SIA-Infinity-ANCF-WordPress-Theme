#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "plugins" / "sia-ancf-core" / "includes" / "class-sia-ancf-core.php"
THEME_FUNCTIONS = ROOT / "theme" / "sia-ancf-news" / "functions.php"
FRONT_PAGE = ROOT / "theme" / "sia-ancf-news" / "front-page.php"

errors = []

core = CORE.read_text(encoding="utf-8")
functions = THEME_FUNCTIONS.read_text(encoding="utf-8")
front_page = FRONT_PAGE.read_text(encoding="utf-8")

required_core = [
    "_sia_home_eligible",
    "_sia_home_exclude",
    "_sia_home_placement",
    "sia_ancf_home_excluded_categories",
    "section_featured",
    "breaking",
    "lead",
    "top",
]
for token in required_core:
    if token not in core:
        errors.append(f"ANCF Core missing homepage contract token: {token}")

required_theme = [
    "sia_ancf_news_home_excluded_category_ids",
    "sia_ancf_news_home_meta_query",
    "sia_ancf_news_home_query_ids",
    "sia_ancf_news_fill_ids",
]
for token in required_theme:
    if token not in functions:
        errors.append(f"Theme functions missing homepage query token: {token}")

required_front_page = [
    "'lead'",
    "'top'",
    "'breaking'",
    "'section_featured'",
    "sia_ancf_news_home_query_ids",
]
for token in required_front_page:
    if token not in front_page:
        errors.append(f"Front page missing editorial selection token: {token}")

forbidden = {
    "wp_delete_post(": "homepage control must not delete content",
    "wp_trash_post(": "homepage control must not trash content",
    "wp_update_post(": "homepage control must not change publication state/content",
    "wp_redirect(": "homepage control must not redirect URLs",
    "wp_safe_redirect(": "homepage post controls must not redirect public URLs",
    "add_filter('wp_robots'": "homepage control must not alter robots",
    'add_filter("wp_robots"': "homepage control must not alter robots",
    "add_filter('redirect_canonical'": "homepage control must not alter canonical behavior",
    'add_filter("redirect_canonical"': "homepage control must not alter canonical behavior",
    "add_action('template_redirect'": "homepage control must not take redirect authority",
    'add_action("template_redirect"': "homepage control must not take redirect authority",
}

# Admin settings legitimately redirect back to their own Tools screen after saving.
core_public_safety = core.replace("wp_safe_redirect(add_query_arg([", "admin_settings_redirect(add_query_arg([")
for path, text in [(CORE, core_public_safety), (THEME_FUNCTIONS, functions), (FRONT_PAGE, front_page)]:
    for needle, reason in forbidden.items():
        if needle in text:
            errors.append(f"{path.relative_to(ROOT)}: found {needle!r} — {reason}")

if errors:
    raise SystemExit("\n".join(errors))

print("Homepage editorial authority safety invariant validated")
