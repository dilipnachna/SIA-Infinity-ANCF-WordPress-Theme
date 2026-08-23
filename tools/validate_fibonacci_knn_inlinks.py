from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ENGINE = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fibonacci-knn-inlinks.php"
VECTOR = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-unicode-vector-provider.php"
TEMPORAL = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fknn-temporal-intent.php"
BRIDGE = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fknn-related-content-bridge.php"
BOOT = ROOT / "plugins" / "sia-semantic-intelligence" / "sia-semantic-intelligence.php"
SINGLE = ROOT / "theme" / "sia-ancf-news" / "single.php"

for path, label in [
    (ENGINE, "Fibonacci kNN engine"),
    (VECTOR, "Universal Unicode vector provider"),
    (TEMPORAL, "Temporal-intent compatibility layer"),
    (BRIDGE, "Surface-aware related-content bridge"),
    (SINGLE, "Theme single-story template"),
]:
    if not path.exists():
        raise SystemExit(f"{label} is missing")

text = ENGINE.read_text(encoding="utf-8")
vector = VECTOR.read_text(encoding="utf-8")
temporal = TEMPORAL.read_text(encoding="utf-8")
bridge = BRIDGE.read_text(encoding="utf-8")
boot = BOOT.read_text(encoding="utf-8")
single = SINGLE.read_text(encoding="utf-8")
lower = (text + "\n" + vector + "\n" + temporal + "\n" + bridge).lower()

required = [
    "final class SIA_Fibonacci_KNN_Inlinks",
    "'semantic' => 13",
    "'context'  => 8",
    "'intent'   => 5",
    "'entity'   => 3",
    "'value'    => 2",
    "sqrt((float) $candidate_count)",
    "sia_fknn_candidate_ids",
    "sia_fknn_vector",
    "sia_fknn_source_value",
    "sia_fknn_minimum_score",
    "already_links_to_target",
    "Missing provider evidence is omitted from the denominator",
]

for needle in required:
    if needle not in text:
        raise SystemExit(f"Missing Fibonacci kNN invariant: {needle}")

for needle in [
    "final class SIA_Unicode_Vector_Provider",
    "\\p{L}\\p{M}\\p{N}",
    "character trigrams",
    "is_array($existing) && $existing",
]:
    if needle not in vector:
        raise SystemExit(f"Missing Unicode vector invariant: {needle}")

for needle in [
    "final class SIA_FKNN_Temporal_Intent",
    "'temporal' => 3",
    "explicit_years",
    "temporal_compatibility",
    "sia_fknn_temporal_profile",
    "sia_fknn_temporal_signal",
    "no conflicting explicit time marker",
    "fresh target vs explicit historical source",
]:
    if needle not in temporal:
        raise SystemExit(f"Missing temporal-intent invariant: {needle}")

for needle in [
    "final class SIA_FKNN_Related_Content_Bridge",
    "SURFACE_POLICY_VERSION",
    "sia_ancf_news_related_ids",
    "sia_ancf_news_more_stories_ids",
    "sia_ancf_news_related_story_ids",
    "rank_more_stories",
    "rank_related_stories",
    "passes_surface_gate",
    "surface_score",
    "sia_fknn_more_surface_minimum",
    "sia_fknn_related_surface_minimum",
    "sia_fknn_related_surface_allow_fallback",
    "sia_fknn_surface_historical_multiplier",
    "sia_fibonacci_knn_recommendations",
    "sia_fknn_related_candidate_limit",
    "set_transient(",
]:
    if needle not in bridge:
        raise SystemExit(f"Missing surface-aware Related Stories invariant: {needle}")

for needle in [
    "apply_filters('sia_ancf_news_related_ids'",
    "apply_filters('sia_ancf_news_more_stories_ids'",
    "apply_filters('sia_ancf_news_related_story_ids'",
]:
    if needle not in single:
        raise SystemExit(f"Theme surface contract missing: {needle}")

# Related Stories must no longer be a blind second slice of one shared list.
if "array_slice($related_pool, 4, 3)" in single:
    raise SystemExit("Legacy shared-list slicing detected; surfaces must select independently")

for needle in [
    "SIA_Unicode_Vector_Provider::boot();",
    "SIA_Fibonacci_KNN_Inlinks::boot();",
    "SIA_FKNN_Temporal_Intent::boot();",
    "SIA_FKNN_Related_Content_Bridge::boot();",
]:
    if needle not in boot:
        raise SystemExit(f"Semantic Intelligence bootstrap invariant missing: {needle}")

# Core logic must remain tenant-, geography-, niche- and language-agnostic.
for forbidden in [
    "jaisalmer",
    "rajasthan",
    "satta",
    "matka",
    "hindi.jaisalmernews",
    "dilip",
]:
    if forbidden in lower:
        raise SystemExit(f"Tenant-specific term leaked into universal semantic engine: {forbidden}")

# Contextual inlink execution remains recommendation-only. Cache writes are allowed,
# but content/URL/SEO mutations are not.
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
        raise SystemExit(f"Mutation API found in read-only semantic engine: {forbidden_api}")

# A fixed k would defeat the adaptive Fibonacci neighborhood contract.
if re.search(r"\$k\s*=\s*(5|8|13|21)\s*;", text):
    raise SystemExit("Fixed k detected; k must adapt from candidate_count")

print("Fibonacci kNN + temporal intent + surface-aware recommendation invariants: OK")
