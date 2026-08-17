from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ENGINE = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-fibonacci-knn-inlinks.php"
VECTOR = ROOT / "plugins" / "sia-semantic-intelligence" / "includes" / "class-sia-unicode-vector-provider.php"
BOOT = ROOT / "plugins" / "sia-semantic-intelligence" / "sia-semantic-intelligence.php"

if not ENGINE.exists():
    raise SystemExit("Fibonacci kNN engine file is missing")
if not VECTOR.exists():
    raise SystemExit("Universal Unicode vector provider is missing")

text = ENGINE.read_text(encoding="utf-8")
vector = VECTOR.read_text(encoding="utf-8")
boot = BOOT.read_text(encoding="utf-8")
lower = (text + "\n" + vector).lower()

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

if "SIA_Unicode_Vector_Provider::boot();" not in boot:
    raise SystemExit("Semantic Intelligence does not boot Unicode vector provider")
if "SIA_Fibonacci_KNN_Inlinks::boot();" not in boot:
    raise SystemExit("Semantic Intelligence does not boot Fibonacci kNN engine")

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

# v0.5 is recommendation-only. No content/URL mutation authority is allowed here.
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
        raise SystemExit(f"Mutation API found in read-only inlink engine: {forbidden_api}")

# A fixed k would defeat the adaptive Fibonacci neighborhood contract.
if re.search(r"\$k\s*=\s*(5|8|13|21)\s*;", text):
    raise SystemExit("Fixed k detected; k must adapt from candidate_count")

print("Fibonacci kNN inlink universality invariants: OK")
