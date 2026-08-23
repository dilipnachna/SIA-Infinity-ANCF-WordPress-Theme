from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GUARD = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fknn-surface-guard.php"
FRESHNESS = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fknn-freshness-age-guard.php"
BOOT = ROOT / "plugins" / "sia-semantic-intelligence" / "sia-semantic-intelligence.php"

for path, label in [
    (GUARD, "Fibonacci surface guard"),
    (FRESHNESS, "Fibonacci freshness-age guard"),
]:
    if not path.exists():
        raise SystemExit(f"{label} is missing")

text = GUARD.read_text(encoding="utf-8")
freshness = FRESHNESS.read_text(encoding="utf-8")
boot = BOOT.read_text(encoding="utf-8")
lower = (text + "\n" + freshness).lower()

required = [
    "final class SIA_FKNN_Surface_Guard",
    "sia_ancf_news_more_stories_ids",
    "sia_ancf_news_related_story_ids",
    "Fallback Quality Gate",
    "sia_fknn_more_historical_cap",
    "sia_fknn_related_historical_cap",
    "sia_fknn_surface_guard_pass",
    "is_historical",
    "recommendation",
    "temporal_source",
]

for needle in required:
    if needle not in text:
        raise SystemExit(f"Missing surface-guard invariant: {needle}")

freshness_required = [
    "final class SIA_FKNN_Freshness_Age_Guard",
    "sia_ancf_news_more_stories_ids",
    "sia_fknn_fresh_target_days",
    "sia_fknn_more_age_mild_days",
    "sia_fknn_more_age_strong_days",
    "sia_fknn_more_age_exceptional_days",
    "sia_fknn_more_age_decay",
    "passes_age_gate",
    "freshness_surface_score",
    "modified_age_days",
    "120",
    "365",
    "730",
]

for needle in freshness_required:
    if needle not in freshness:
        raise SystemExit(f"Missing freshness-age invariant: {needle}")

for needle in [
    "require_once __DIR__ . '/includes/class-sia-fknn-surface-guard.php';",
    "SIA_FKNN_Surface_Guard::boot();",
    "require_once __DIR__ . '/includes/class-sia-fknn-freshness-age-guard.php';",
    "SIA_FKNN_Freshness_Age_Guard::boot();",
]:
    if needle not in boot:
        raise SystemExit(f"Surface/freshness guard bootstrap invariant missing: {needle}")

for forbidden in [
    "jaisalmer",
    "rajasthan",
    "satta",
    "matka",
    "hindi.jaisalmernews",
    "dilip",
]:
    if forbidden in lower:
        raise SystemExit(f"Tenant-specific term leaked into surface guards: {forbidden}")

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
        raise SystemExit(f"Mutation API found in read-only surface guards: {forbidden_api}")

# Final frontend permission must require graph evidence. A raw fallback-only
# path would reintroduce weak category-only recommendations.
if "$recommendation = $by_source[$source_id] ?? null;" not in text:
    raise SystemExit("Fallback candidates are not being checked against graph evidence")
if "if (!is_array($recommendation))" not in text:
    raise SystemExit("Missing rejection path for fallback without graph evidence")

# Freshness pressure applies only to current/recent targets; archive targets must
# retain the broader semantic surface policy.
if "if ($target_age_days > $fresh_target_days)" not in freshness:
    raise SystemExit("Freshness age guard is not scoped to fresh targets")
if ">730 days by default: exceptional evidence is required" not in freshness:
    raise SystemExit("Exceptional stale-source evidence gate is missing")

print("Fibonacci fallback quality + historical diversity + freshness-age invariants: OK")
