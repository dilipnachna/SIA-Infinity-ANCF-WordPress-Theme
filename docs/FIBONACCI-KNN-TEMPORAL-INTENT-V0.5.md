# SIA Fibonacci kNN Temporal Intent v0.5

## Purpose

Add a universal temporal-compatibility signal to the existing Fibonacci-kNN semantic graph so that a semantically related archive document does not outrank a temporally compatible document merely because it contains a highly similar paragraph.

This module remains read-only. It does not insert links, rewrite content, change URLs, alter SEO metadata or perform redirects.

## Updated evidence score

```text
R(s -> t) =
    (13 * semantic
   +  8 * context
   +  5 * intent
   +  3 * entity
   +  3 * temporal
   +  2 * value)
    / sum(weights whose evidence is actually available)
```

Missing evidence is omitted from the denominator.

## Structural temporal evidence

The built-in classifier does not use tenant names, niche terms or language-specific keyword dictionaries. It uses:

- explicit four-digit years found in title/excerpt;
- post modified age;
- post published age;
- the current reference year.

Profiles are intentionally coarse:

```text
CURRENT
RECENT
HISTORICAL
FUTURE
EVERGREEN
UNDATED
```

## Compatibility principle

A fresh target and an explicitly historical source receive a low temporal score. A fresh target and an undated/evergreen source are treated as compatible-but-uncertain rather than automatically stale. This lets semantic/context evidence decide among documents that do not contain a conflicting explicit time marker.

Historical targets prefer the same explicit archive year and progressively discount larger year gaps.

## Provider hooks

```text
sia_fknn_temporal_profile
sia_fknn_temporal_signal
sia_fknn_temporal_current_days
sia_fknn_temporal_recent_days
```

These hooks let larger deployments replace or enrich the structural classifier without changing the theme or the base semantic engine.

## Shared graph behavior

The temporal reranker is attached to `sia_fibonacci_knn_recommendations`. Therefore the same reranked graph powers:

- editor inlink recommendations;
- Related Stories;
- More Stories/sidebar recommendations.

The editor meta box is replaced by the temporal-aware view and exposes the target/source temporal profiles plus the temporal evidence score for debugging.

## Safety invariant

The temporal layer must remain:

- tenant agnostic;
- topic agnostic;
- language-dictionary agnostic;
- read-only;
- optional/provider-replaceable.

The CI universality validator checks these invariants.
