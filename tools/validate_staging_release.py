#!/usr/bin/env python3
import json
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"
VERSION = (ROOT / "VERSION").read_text(encoding="utf-8").strip()

plugins = [
    "sia-ancf-core",
    "sia-url-memory",
    "sia-entity-author",
    "sia-semantic-intelligence",
    "rank-smart",
]
theme = "sia-ancf-news"

errors = []

for slug in plugins:
    path = DIST / f"{slug}-{VERSION}.zip"
    if not path.exists():
        errors.append(f"missing plugin ZIP: {path.name}")
        continue
    with ZipFile(path) as zf:
        names = zf.namelist()
        if not any(name.startswith(f"{slug}/") for name in names):
            errors.append(f"{path.name}: missing top-level {slug}/ folder")

path = DIST / f"{theme}-{VERSION}.zip"
if not path.exists():
    errors.append(f"missing theme ZIP: {path.name}")
else:
    with ZipFile(path) as zf:
        names = zf.namelist()
        if f"{theme}/style.css" not in names:
            errors.append(f"{path.name}: missing {theme}/style.css")

kit = DIST / f"SIA-Infinity-ANCF-WordPress-Staging-Kit-v{VERSION}.zip"
if not kit.exists():
    errors.append(f"missing staging kit: {kit.name}")
else:
    with ZipFile(kit) as zf:
        names = set(zf.namelist())
        required = {
            "INSTALL-STAGING.md",
            "staging-manifest.json",
            f"theme/{theme}-{VERSION}.zip",
            *{f"plugins/{slug}-{VERSION}.zip" for slug in plugins},
        }
        missing = sorted(required - names)
        if missing:
            errors.append(f"{kit.name}: missing entries {missing}")
        manifest = json.loads(zf.read("staging-manifest.json").decode("utf-8"))
        if manifest.get("version") != VERSION:
            errors.append("staging manifest version mismatch")
        if manifest.get("outer_zip_installable_in_wordpress") is not False:
            errors.append("staging manifest must mark outer kit as non-installable")

if errors:
    raise SystemExit("\n".join(errors))

print("Installable staging release validated")
