from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GUARD = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fknn-related-age-diversity-guard.php"
BOOT = ROOT / "plugins" / "sia-semantic-intelligence" / "sia-semantic-intelligence.php"

if not GUARD.exists():
    raise SystemExit("Related Stories age diversity guard is missing")

text = GUARD.read_text(encoding="utf-8")
boot = BOOT.read_text(encoding="utf-8")
lower = text.lower()

required = [
    "final class SIA_FKNN_Related_Age_Diversity_Guard",
    "sia_ancf_news_related_story_ids",
    "sia_fknn_related_stale_cap",
    "sia_fknn_related_age_normal_days",
    "sia_fknn_related_age_exceptional_days",
    "sia_fknn_related_age_exceptional_semantic_floor",
    "sia_fknn_related_age_exceptional_context_floor",
    "sia_fknn_related_age_gate_pass",
    "Semantic AND context must both be strong",
    "730",
]

for needle in required:
    if needle not in text:
        raise SystemExit(f"Missing Related Stories age-diversity invariant: {needle}")

for needle in [
    "require_once __DIR__ . '/includes/class-sia-fknn-related-age-diversity-guard.php';",
    "SIA_FKNN_Related_Age_Diversity_Guard::boot();",
]:
    if needle not in boot:
        raise SystemExit(f"Related age-diversity bootstrap invariant missing: {needle}")

# The exceptional-age path must require semantic AND context evidence, not OR.
if "$semantic !== null && $semantic >= $semantic_floor" not in text:
    raise SystemExit("Exceptional-age semantic floor is missing")
if "$context !== null && $context >= $context_floor" not in text:
    raise SystemExit("Exceptional-age context floor is missing")

# This guard has no authority over More Stories.
if "add_filter('sia_ancf_news_more_stories_ids'" in text:
    raise SystemExit("Related age-diversity guard leaked into More Stories authority")

for forbidden in [
    "jaisalmer",
    "rajasthan",
    "satta",
    "matka",
    "hindi.jaisalmernews",
    "dilip",
]:
    if forbidden in lower:
        raise SystemExit(f"Tenant-specific term leaked into Related age-diversity guard: {forbidden}")

for forbidden_api in [
    "wp_update_post(",
    "wp_insert_post(",
    "update_post_meta(",
    "delete_post_meta(",
    "wp_delete_post(",
    "$wpdb->update(",
    "$wpdb->delete(",
]:
    if forbidden_api in lower:
        raise SystemExit(f"Mutation API found in read-only Related age-diversity guard: {forbidden_api}")

print("Fibonacci Related Stories age-diversity invariants: OK")
