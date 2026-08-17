from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ENGINE = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fibonacci-knn-inlinks.php"
VECTOR = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-unicode-vector-provider.php"
BRIDGE = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fknn-related-content-bridge.php"
BOOT = ROOT / "plugins" / "sia-semantic-intelligence" / "sia-semantic-intelligence.php"
SINGLE = ROOT / "theme" / "sia-ancf-news" / "single.php"

for path, label in [
    (ENGINE, "Fibonacci kNN engine"),
    (VECTOR, "Universal Unicode vector provider"),
    (BRIDGE, "Related-content semantic bridge"),
    (SINGLE, "Theme single-story template"),
]:
    if not path.exists():
        raise SystemExit(f"{label} is missing")

text = ENGINE.read_text(encoding="utf-8")
vector = VECTOR.read_text(encoding="utf-8")
bridge = BRIDGE.read_text(encoding="utf-8")
boot = BOOT.read_text(encoding="utf-8")
single = SINGLE.read_text(encoding="utf-8")
lower = (text + "\n" + vector + "\n" + bridge).lower()

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
    "final class SIA_FKNN_Related_Content_Bridge",
    "sia_ancf_news_related_ids",
    "sia_fibonacci_knn_recommendations",
    "sia_fknn_related_candidate_limit",
    "set_transient(",
    "array_merge($semantic_ids, $fallback_ids)",
]:
    if needle not in bridge:
        raise SystemExit(f"Missing Related Stories bridge invariant: {needle}")

if "apply_filters('sia_ancf_news_related_ids'" not in single:
    raise SystemExit("Theme does not expose the generic related-content ranking filter")

for needle in [
    "SIA_Unicode_Vector_Provider::boot();",
    "SIA_Fibonacci_KNN_Inlinks::boot();",
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

print("Fibonacci kNN inlink + Related Stories universality invariants: OK")
