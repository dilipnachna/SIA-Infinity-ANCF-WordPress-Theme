# SIA Fibonacci kNN Inlink Engine v0.5

## Goal

Recommend the strongest **source -> target** internal-link opportunities without hard-coding a site, language, niche, geography, category or tenant.

The first real-world test tenant may be any WordPress publication. The algorithm itself must remain universal.

## Safety boundary

```text
Observe -> compare -> recommend -> measure.
Do not insert, rewrite, delete or redirect automatically in v0.5.
```

The engine is recommendation-only for contextual in-content links. Editors or a future governed execution layer decide whether a suggested inlink is actually added.

## Adaptive Fibonacci k

Let `N` be the number of eligible published source documents in the candidate corpus.

```text
k = largest Fibonacci number <= sqrt(N)
```

The local fallback is capped at `k <= 21` by default and the cap is filterable.

Examples:

| Eligible candidates N | sqrt(N) | k |
|---:|---:|---:|
| 9 | 3.0 | 3 |
| 25 | 5.0 | 5 |
| 64 | 8.0 | 8 |
| 169 | 13.0 | 13 |
| 441+ | 21+ | 21 default cap |

This prevents one fixed neighbor count from being applied to both small and large sites.

## Fibonacci evidence score

For a possible source document `s` and target document `t`:

```text
R(s -> t) =
    (13 * semantic
   +  8 * context
   +  5 * intent
   +  3 * entity
   +  2 * value)
    / sum(weights whose evidence is actually available)
```

Signal meaning:

- **13 semantic** — document-level vector similarity. A provider can supply embeddings; otherwise a Unicode lexical vector is used.
- **8 context** — best paragraph-level similarity to the target intent, used to identify a likely insertion context.
- **5 intent** — title/excerpt alignment between source and target.
- **3 entity** — overlap of assigned taxonomies and Primary Semantic Silo metadata.
- **2 value** — optional source-value evidence supplied by future Search Console / Analytics / backlink providers.

### Missing evidence is not negative evidence

If source-value evidence is unavailable, its weight is removed from the denominator. The page is **not** punished with a zero merely because an external connector is not installed.

## Vector contract

Filter:

```text
sia_fknn_vector
```

A future embedding provider may return any consistent numeric vector. If no provider answers, the engine builds a Unicode-aware lexical vector from text. Languages that do not produce enough whitespace-separated tokens fall back to character trigrams.

## Candidate contract

Filter:

```text
sia_fknn_candidate_ids
```

Large SaaS tenants may provide candidates from a vector database or search service. The built-in WordPress fallback scans a bounded set of recently modified published posts/pages.

## Source-value contract

Filter:

```text
sia_fknn_source_value
```

Expected shape:

```php
[
    'available' => true,
    'score' => 0.0, // normalized 0..1
    'evidence' => [...],
]
```

This is where Rank Smart can later combine normalized Search Console, Analytics, backlink or revenue evidence.

## Existing-link gate

A source document that already links to the target URL is excluded from contextual inlink recommendations. This prevents the engine from recommending duplicate inlinks simply because a page is semantically close.

## Neighborhood confidence

The displayed neighborhood confidence is a Fibonacci-rank-weighted mean of up to the five strongest final recommendations. Higher-ranked neighbors contribute more strongly than lower-ranked neighbors.

## One semantic graph, multiple surfaces

The same Fibonacci-kNN recommendation graph can drive more than one presentation or editorial surface.

### Contextual in-content links

The post editor receives source -> target recommendations plus the strongest source paragraph. This remains approval-based in v0.5; no article body is rewritten automatically.

### Related Stories and sidebar

The theme exposes a generic filter:

```text
sia_ancf_news_related_ids
```

When SIA Semantic Intelligence is active, `SIA_FKNN_Related_Content_Bridge` uses the same `sia_fibonacci_knn_recommendations` graph to rank Related Stories / More Stories. Semantic results are placed first and the theme's normal category-based results remain as fallback when the semantic neighborhood is too small.

The bridge is deliberately separate from the theme:

```text
Theme = presentation contract
Semantic Intelligence = ranking intelligence
```

If Semantic Intelligence is inactive, the theme continues to work with its existing category-based related-story fallback.

### Frontend performance boundary

Frontend related-story ranking is cached. The built-in bridge uses a bounded candidate scan for cache misses, and both the candidate limit and cache TTL are filterable. Large SaaS deployments can replace the local candidate/vector providers with a vector database or search service without changing the theme.

## Universality invariant

Core engine code must not contain:

- a production/test domain;
- tenant name;
- geography name;
- language name;
- niche keyword;
- category ID;
- automatic content mutation authority.

`tools/validate_fibonacci_knn_inlinks.py` enforces the main invariants in CI, including the generic Related Stories bridge contract.

## WordPress UI

Published posts/pages receive a read-only meta box:

**SIA Fibonacci kNN — Inlink Recommendations**

It shows:

- adaptive `k` and candidate count;
- top source pages that could link to the current target;
- final score and component evidence;
- best source paragraph/context;
- source edit/view links;
- neighborhood confidence.

No contextual internal link is inserted automatically in v0.5.

## Measurement loop

A recommendation is not considered successful merely because it was generated.

The intended future outcome memory is:

```text
baseline -> approved inlink intervention -> observation window ->
impressions / clicks / CTR / position / query breadth / URL concentration -> outcome
```

This allows Rank Smart to learn whether internal linking actually produced measurable lift for a given site and situation instead of assuming that every inlink is beneficial.
