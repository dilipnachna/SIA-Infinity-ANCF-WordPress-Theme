#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONTRACTS = ROOT / "contracts"
required = {
    "story.schema.json": {"$schema", "$id", "title", "type", "properties"},
    "entity.schema.json": {"$schema", "$id", "title", "type", "properties"},
    "url-memory.schema.json": {"$schema", "$id", "title", "type", "properties"},
    "editorial-state.schema.json": {"$schema", "$id", "title", "type"},
    "publisher-intelligence.schema.json": {"$schema", "$id", "title", "type", "properties"},
    "rank-smart-audit.schema.json": {"$schema", "$id", "title", "type", "properties"},
}

errors = []
for name, keys in required.items():
    path = CONTRACTS / name
    if not path.exists():
        errors.append(f"missing {name}")
        continue
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        errors.append(f"{name}: invalid JSON: {exc}")
        continue
    missing = keys - set(data)
    if missing:
        errors.append(f"{name}: missing keys {sorted(missing)}")

if errors:
    raise SystemExit("\n".join(errors))
print(f"Validated {len(required)} ANCF contracts")
