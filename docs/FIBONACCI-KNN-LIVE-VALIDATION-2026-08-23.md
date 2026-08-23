# Fibonacci kNN Live Validation — 2026-08-23

Status: **FRONTEND QUALITY ACCEPTED / CONTROLLED INLINK OUTCOME PENDING**

Branch: `v0.5-fibonacci-knn-inlinks`
PR: `#8`
Suite compatibility line: `v0.4.2-alpha.1`

## Purpose

Validate the universal Fibonacci-kNN semantic graph on a real WordPress tenant without leaking tenant, language, geography, category, or niche-specific logic into the core engine.

The live tenant is a test surface only. All production logic remains universal.

## Base model under test

Adaptive neighbourhood:

`k = largest Fibonacci number <= sqrt(N)` with a configurable safety cap.

Evidence score:

- semantic similarity: 13
- best paragraph/context: 8
- intent compatibility: 5
- entity/taxonomy relation: 3
- temporal compatibility: 3
- optional source value: 2

Missing provider evidence is omitted from the denominator and is never treated as zero.

## Live target observation

The editor test returned `k = 8 / 89 candidates` with a neighbourhood confidence of approximately `57.8%`.

Top visible contextual inlink candidates in the live test were:

1. current/undated closely related source — `60.0%`
2. explicit 2024 archive/reference source — `58.9%`
3. current/undated closely related source — `55.5%`
4. adjacent market/topic source — `52.9%`
5. older adjacent-topic source — `51.2%`

The editor output also surfaced the best insertion paragraph for each source. No article-body link was inserted automatically.

## What the live tests proved

### Candidate discovery — PASS

The engine isolated the correct semantic neighbourhood instead of returning unrelated site categories.

### Unicode / Hindi handling — PASS

Devanagari text remained usable for similarity and paragraph-context selection. Core tokenisation remains Unicode-oriented rather than language-hard-coded.

### Temporal intent — PASS

A clearly historical archive source initially outranked a fresher source because its paragraph-context score was high. The temporal layer correctly re-ranked the fresh/current-compatible source above it while preserving the archive source as a valid contextual inlink opportunity.

### One graph / multiple surfaces — PASS

`More Stories` and `Related Stories` now consume the same semantic graph through separate presentation contracts rather than blind list slicing.

### Fallback quality guard — PASS

A raw category fallback can no longer occupy a frontend recommendation slot unless graph evidence also exists.

### Historical diversity — PASS

Compact recommendation surfaces are prevented from being dominated by archive material. Historical content is not globally banned; strong archive/reference relationships may retain a limited slot.

### More Stories freshness-age authority — PASS

Fresh/current targets now prefer fresher continuity links. Older graph-backed candidates must meet progressively stronger evidence thresholds.

### Related Stories age diversity — PASS

An old adjacent-topic document that survived textual similarity was removed from Related Stories once actual source age was considered. The final live frontend preferred fewer stronger cards over filling all slots with weaker content.

## Final frontend acceptance state

The final live observation showed:

- `More Stories`: fresh/recent continuity set; no weak 2023 stale filler.
- `Related Stories`: two stronger cards rather than three forced cards.
- one useful archive/reference item was allowed within the diversity cap.
- sidebar and Related Stories did not duplicate each other.

**Frontend recommendation quality is accepted for this candidate.**

Do not continue tuning surface weights from isolated screenshots unless a new reproducible failure class appears. Further tuning now risks tenant overfitting.

## Next gate — controlled contextual inlink outcome

The PR must remain open until the system is tested as an SEO intervention, not only as a recommendation UI.

Experiment contract:

1. Capture a fresh target-URL baseline immediately before intervention:
   - clicks
   - impressions
   - CTR
   - average position
   - visible query set / query breadth
2. Select only 3 high-confidence contextual source pages from the editor recommendations.
3. Insert natural informational internal links manually at the recommended paragraph contexts.
4. Record the exact deployment timestamp.
5. Do not simultaneously change the target URL, title, article body, schema, canonical, publication date, or other major SEO variables.
6. Observe the target at approximately `+24h`, `+48h`, `+72h`, and `+7d`.
7. Classify the intervention outcome as:
   - POSITIVE
   - NEUTRAL
   - NEGATIVE
   - INSUFFICIENT DATA

Primary evidence to watch:

- impression growth
- query breadth expansion
- stable or improved average position
- click growth with CTR interpreted in context

A short-term click spike alone is not sufficient proof.

## Promotion gate

Promote PR #8 / suite v0.5 only after:

- frontend recommendation quality remains accepted;
- the controlled contextual-inlink experiment is completed;
- outcome is recorded without claiming causality beyond the available evidence;
- universality and read-only safety invariants continue to pass CI.

## Architecture invariant

`Universal engine -> site profile/evidence -> semantic graph -> surface policy/recommendation -> measured outcome`

The universal core must never hard-code the test tenant's domain, language, geography, category names, IDs, or niche vocabulary.
