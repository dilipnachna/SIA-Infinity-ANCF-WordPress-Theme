#!/usr/bin/env python3
import json
import re
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


def extract_header_version(text: str):
    match = re.search(r"^\s*\*?\s*Version:\s*([^\r\n]+)", text, flags=re.MULTILINE)
    return match.group(1).strip() if match else None


for slug in plugins:
    source = ROOT / "plugins" / slug / f"{slug}.php"
    if not source.exists():
        errors.append(f"missing plugin bootstrap: {source.relative_to(ROOT)}")
    else:
        header_version = extract_header_version(source.read_text(encoding="utf-8"))
        if header_version != VERSION:
            errors.append(
                f"{source.relative_to(ROOT)}: Version header {header_version!r} != release VERSION {VERSION!r}"
            )

    path = DIST / f"{slug}-{VERSION}.zip"
    if not path.exists():
        errors.append(f"missing plugin ZIP: {path.name}")
        continue
    with ZipFile(path) as zf:
        names = zf.namelist()
        if not any(name.startswith(f"{slug}/") for name in names):
            errors.append(f"{path.name}: missing top-level {slug}/ folder")
        bootstrap = f"{slug}/{slug}.php"
        if bootstrap not in names:
            errors.append(f"{path.name}: missing {bootstrap}")
        else:
            packaged_version = extract_header_version(zf.read(bootstrap).decode("utf-8"))
            if packaged_version != VERSION:
                errors.append(
                    f"{path.name}: packaged Version header {packaged_version!r} != release VERSION {VERSION!r}"
                )

source = ROOT / "theme" / theme / "style.css"
if not source.exists():
    errors.append(f"missing theme stylesheet: {source.relative_to(ROOT)}")
else:
    header_version = extract_header_version(source.read_text(encoding="utf-8"))
    if header_version != VERSION:
        errors.append(
            f"{source.relative_to(ROOT)}: Version header {header_version!r} != release VERSION {VERSION!r}"
        )

path = DIST / f"{theme}-{VERSION}.zip"
if not path.exists():
    errors.append(f"missing theme ZIP: {path.name}")
else:
    with ZipFile(path) as zf:
        names = zf.namelist()
        stylesheet = f"{theme}/style.css"
        if stylesheet not in names:
            errors.append(f"{path.name}: missing {stylesheet}")
        else:
            packaged_version = extract_header_version(zf.read(stylesheet).decode("utf-8"))
            if packaged_version != VERSION:
                errors.append(
                    f"{path.name}: packaged Version header {packaged_version!r} != release VERSION {VERSION!r}"
                )

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

print(f"Installable staging release validated with synchronized component version {VERSION}")
