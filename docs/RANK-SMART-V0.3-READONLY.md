# Rank Smart v0.3 — Read-only SEO Intelligence

## Purpose

Rank Smart v0.3 is the first SEO-intelligence layer in SIA Infinity ANCF. It observes evidence and estimates the risk of changing or removing a URL. It is **not yet SEO output authority**.

## Safety invariant

> Evidence may inform an SEO decision. Evidence does not silently create execution authority.

v0.3 does not automatically:

- rewrite titles or meta descriptions;
- change slugs or permalink settings;
- emit or replace canonical tags;
- add robots/noindex directives;
- create redirects;
- emit schema;
- replace XML or News sitemaps;
- delete, merge, restore, noindex or 410 content;
- connect Google Search Console, Analytics or AdSense without a future explicit connector;
- disavow backlinks;
- publish AI-generated changes.

Existing Rank Math, Yoast, AIOSEO or WordPress output remains authoritative until a later explicit migration phase.

## Local read-only audit

For posts and pages Rank Smart records or derives:

- current permalink;
- WordPress canonical candidate;
- public/index candidate state;
- title and slug length as factual signals, not ranking guarantees;
- whether the slug is Latin/ASCII;
- excerpt presence;
- featured-image width;
- ANCF Primary Semantic Silo;
- author identity;
- SIA URL Memory observation count;
- recorded slug-change count;
- observed SEO-plugin authority.

## URL change-risk score

The v0.3 score estimates the **risk of changing/removing the URL**, not its probability of ranking.

Evidence can raise change risk when the URL is already published, has historical URL observations, has previous slug changes, or future connected providers show positive search, audience, revenue or backlink evidence.

```text
LOW     = little evidence currently available
MEDIUM  = meaningful history/evidence; review before changing
HIGH    = multiple value/history signals; do not change casually
```

A LOW score is never permission to delete. Missing external data remains missing evidence, not proof of no value.

## Provider contracts

v0.3 defines read-only provider extension points before adding OAuth/API implementations:

```php
apply_filters('rank_smart_provider_state', $state, $provider);
apply_filters('rank_smart_provider_evidence', $evidence, $provider, $post);
apply_filters('rank_smart_post_audit', $audit, $post);
```

Provider names reserved by v0.3:

```text
search_console
analytics
adsense
backlinks
```

This lets future connectors supply evidence without coupling Google/API credentials to the theme or the core audit engine.

## Publisher Intelligence integration

When the SIA ANCF News theme is active, Rank Smart marks itself as `Read-only connected`, adds its audit to the Publisher Intelligence context, and shows change-risk evidence below the existing newsroom cards.

The same Rank Smart plugin can operate on another WordPress theme through its own post/page meta box and Tools → Rank Smart screen.

## Architecture boundary

```text
SIA ANCF Theme     = newsroom interface
ANCF Core          = publishing metadata / governance
SIA URL Memory     = local URL history
Rank Smart v0.3    = read-only SEO evidence / risk intelligence
future connectors  = Search Console / Analytics / AdSense / backlinks
future Rank Smart  = controlled SEO authority after explicit migration
```

## Next connector stage

The next data stage should persist source, observation time and metric provenance. Search Console, Analytics, AdSense and backlink data must remain distinguishable rather than being collapsed into one unexplained score.
