#!/usr/bin/env python3
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import shutil

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"
DIST.mkdir(exist_ok=True)

def pack(source: Path, out: Path):
    if out.exists():
        out.unlink()
    with ZipFile(out, "w", ZIP_DEFLATED) as zf:
        for path in sorted(source.rglob("*")):
            if path.is_file():
                zf.write(path, path.relative_to(source.parent))
    print(out)

for plugin in sorted((ROOT / "plugins").iterdir()):
    if plugin.is_dir():
        pack(plugin, DIST / f"{plugin.name}-0.1.0-alpha.1.zip")

pack(ROOT / "theme" / "sia-ancf-news", DIST / "sia-ancf-news-0.1.0-alpha.1.zip")

release = DIST / "SIA-Infinity-ANCF-WordPress-v0.1.0-alpha.1.zip"
if release.exists():
    release.unlink()
with ZipFile(release, "w", ZIP_DEFLATED) as zf:
    for path in sorted(ROOT.rglob("*")):
        if path.is_file() and DIST not in path.parents:
            zf.write(path, path.relative_to(ROOT))
print(release)
