#!/usr/bin/env python3
import json
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"
DIST.mkdir(exist_ok=True)
VERSION = (ROOT / "VERSION").read_text(encoding="utf-8").strip()

PLUGIN_ORDER = [
    "sia-ancf-core",
    "sia-url-memory",
    "sia-entity-author",
    "sia-semantic-intelligence",
    "rank-smart",
]
THEME_SLUG = "sia-ancf-news"


def pack(source: Path, out: Path):
    if out.exists():
        out.unlink()
    with ZipFile(out, "w", ZIP_DEFLATED) as zf:
        for path in sorted(source.rglob("*")):
            if path.is_file():
                zf.write(path, path.relative_to(source.parent))
    print(out)


plugin_zips = []
for plugin_name in PLUGIN_ORDER:
    plugin = ROOT / "plugins" / plugin_name
    if not plugin.is_dir():
        raise SystemExit(f"Missing plugin directory: {plugin}")
    out = DIST / f"{plugin.name}-{VERSION}.zip"
    pack(plugin, out)
    plugin_zips.append(out)

theme_zip = DIST / f"{THEME_SLUG}-{VERSION}.zip"
pack(ROOT / "theme" / THEME_SLUG, theme_zip)

source_release = DIST / f"SIA-Infinity-ANCF-WordPress-v{VERSION}-source.zip"
if source_release.exists():
    source_release.unlink()
with ZipFile(source_release, "w", ZIP_DEFLATED) as zf:
    for path in sorted(ROOT.rglob("*")):
        if path.is_file() and DIST not in path.parents:
            zf.write(path, path.relative_to(ROOT))
print(source_release)

install_text = f"""# SIA Infinity ANCF WordPress — Staging Install Kit

Version: {VERSION}

This package is for staging/testing. Do not upload this outer ZIP directly as a WordPress theme or plugin.

## Install plugins in this order

1. sia-ancf-core-{VERSION}.zip
2. sia-url-memory-{VERSION}.zip
3. sia-entity-author-{VERSION}.zip
4. sia-semantic-intelligence-{VERSION}.zip
5. rank-smart-{VERSION}.zip

WordPress: Plugins → Add New → Upload Plugin → Install Now → Activate.

## Then install the theme

Upload:

sia-ancf-news-{VERSION}.zip

WordPress: Appearance → Themes → Add New → Upload Theme → Install Now → Activate.

## Safety

Keep the existing production SEO plugin active during v0.3 staging validation. Rank Smart is read-only and does not replace canonical, robots, schema, sitemap, redirect, title, meta or URL authority.

## Smoke test

Open several old and new posts and verify:

- SIA Publisher Intelligence panel is visible.
- Rank Smart shows Read-only connected.
- URL Memory observations load without errors.
- Existing SEO plugin output remains unchanged.
- No PHP/admin warnings appear.
"""

install_file = DIST / "INSTALL-STAGING.md"
install_file.write_text(install_text, encoding="utf-8")

manifest = {
    "suite": "SIA Infinity ANCF WordPress",
    "version": VERSION,
    "mode": "staging",
    "plugins_install_order": [path.name for path in plugin_zips],
    "theme": theme_zip.name,
    "source_archive": source_release.name,
    "outer_zip_installable_in_wordpress": False,
}
manifest_file = DIST / "staging-manifest.json"
manifest_file.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")

staging_kit = DIST / f"SIA-Infinity-ANCF-WordPress-Staging-Kit-v{VERSION}.zip"
if staging_kit.exists():
    staging_kit.unlink()
with ZipFile(staging_kit, "w", ZIP_DEFLATED) as zf:
    zf.write(install_file, install_file.name)
    zf.write(manifest_file, manifest_file.name)
    for path in plugin_zips:
        zf.write(path, f"plugins/{path.name}")
    zf.write(theme_zip, f"theme/{theme_zip.name}")
print(staging_kit)
