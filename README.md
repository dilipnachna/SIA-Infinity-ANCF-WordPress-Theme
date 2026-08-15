# SIA Infinity ANCF for WordPress

**Status:** v0.3.0-alpha.1 Rank Smart read-only SEO intelligence / staging-first

SIA Infinity ANCF for WordPress is the WordPress implementation of the SIA Infinity publishing-intelligence architecture. v0.3 adds the first **Rank Smart** module: a read-only SEO evidence engine that audits current WordPress state, URL history and future provider evidence without taking over production SEO output.

## Safety invariant

> Existing production SEO output remains authoritative until explicitly migrated.

The current modules do **not** automatically:

- change post URLs or permalink settings;
- emit redirects;
- alter canonical tags;
- change robots/indexing directives;
- replace Rank Math/Yoast/AIOSEO schema or metadata;
- modify XML or News sitemaps;
- delete, merge, restore, noindex or 410 content;
- translate or overwrite article content;
- create English drafts without explicit user action;
- add paid/sponsored links;
- publish AI-generated or sponsored content;
- connect Google Search Console, Analytics, AdSense or backlink APIs without an explicit future connector.

## Architecture

```text
SIA ANCF News Theme = newsroom interface
SIA ANCF Core       = shared publishing metadata / governance
SIA URL Memory      = append-only local URL history
Rank Smart          = read-only SEO evidence + URL change-risk intelligence
SIA AI              = future Hindi → English / human-writing SaaS
Brand Studio        = future monetization / sponsored-content workflow
```

The theme displays integration state but does not silently become SEO, AI, or monetization authority.

## Repository structure

```text
plugins/
  sia-ancf-core/
  sia-url-memory/
  sia-entity-author/
  sia-semantic-intelligence/
  rank-smart/
    rank-smart.php
    includes/
      class-rank-smart.php
      class-rank-smart-publisher-bridge.php

theme/
  sia-ancf-news/
    inc/class-sia-publisher-intelligence.php

contracts/
  story.schema.json
  entity.schema.json
  url-memory.schema.json
  editorial-state.schema.json
  publisher-intelligence.schema.json
  rank-smart-audit.schema.json

docs/
tools/
.github/workflows/
```

## v0.3 modules

### SIA ANCF Core
Stores shared editorial metadata and commercial-content classification for editorial, sponsored article, guest contribution, brand story and existing-content sponsorship.

### SIA URL Memory
Maintains append-only local WordPress URL observations and records slug/status events. It remains observe-only and does not create redirects or block actions.

### SIA Entity & Author
Stores structured author/newsroom metadata without taking over public schema.

### SIA Semantic Intelligence
Stores an explicit **Primary Semantic Silo** from an already-assigned WordPress category.

### SIA ANCF News Theme
A staging-first newsroom theme with the **SIA Publisher Intelligence** editor console:

- Content
- URL Memory
- Rank Smart
- English / SIA AI
- Discover Readiness
- Monetization

### Rank Smart v0.3
Rank Smart is now a real plugin instead of only a future integration placeholder. It provides a read-only audit for posts and pages:

- current permalink and WordPress canonical candidate;
- public/index candidate state;
- title and slug character counts as factual signals;
- Latin/ASCII slug observation;
- excerpt presence;
- featured-image width;
- Primary Semantic Silo;
- author identity;
- SIA URL Memory observations and slug-change history;
- observed existing SEO authority such as Rank Math, Yoast or AIOSEO;
- provider-ready evidence slots for Search Console, Analytics, AdSense and backlinks;
- an evidence-based **URL change-risk score**.

The change-risk score is **not a ranking score**. It estimates how risky it may be to change/remove a URL based on currently available evidence. Missing external evidence never means a URL has no value.

## Rank Smart provider extension points

```php
apply_filters('rank_smart_provider_state', $state, $provider);
apply_filters('rank_smart_provider_evidence', $evidence, $provider, $post);
apply_filters('rank_smart_post_audit', $audit, $post);
```

Reserved provider names:

```text
search_console
analytics
adsense
backlinks
```

OAuth/API implementations come later; v0.3 establishes the evidence contract first.

## Publisher Intelligence extension points

```php
apply_filters('sia_publisher_intelligence_context', $context, $post);
apply_filters('sia_publisher_integration_status', $integrations, $post);
do_action('sia_publisher_intelligence_after_sections', $post, $context);
```

Rank Smart marks itself as **Read-only connected**, supplies its audit to the Publisher Intelligence context and renders URL change-risk evidence without changing SEO output.

## Primary Silo principle

```text
Stable permalink
      +
Primary Semantic Silo
      +
Entities / topics / location
      +
Contextual relationships
      +
Editorial identity
```

A post may have many categories/tags/entities, but only one Primary Semantic Silo.

## URL Memory / Smart Value principle

A URL is treated as a historical publishing asset, not a disposable string. The long-term design combines:

```text
URL history
+ Search Console history
+ Analytics behaviour
+ AdSense/revenue evidence
+ backlink/referring-domain evidence
+ internal-link evidence
+ index state
+ replacement relationship
= safe KEEP / UPDATE / MERGE / 301 / NOINDEX / 410 decision support
```

v0.3 starts the decision layer using local URL history and explicit provider contracts. It does not yet perform those actions automatically.

## Installation for development/staging

1. Install and activate `sia-ancf-core`.
2. Install `sia-url-memory`, `sia-entity-author`, and `sia-semantic-intelligence`.
3. Install and activate `rank-smart`.
4. Keep the existing production SEO plugin active.
5. Activate the SIA ANCF News theme on staging first.
6. Verify Rank Smart reports evidence without changing public SEO output.

Rank Smart can also operate on another WordPress theme through its own post/page meta box and **Tools → Rank Smart** screen.

## Build release ZIPs

```bash
python3 tools/package_release.py
```

Builds installable plugin/theme ZIPs under `dist/` using the project `VERSION`.

## Validation

```bash
python3 tools/validate_contracts.py
python3 tools/validate_rank_smart_safety.py
find plugins theme -name '*.php' -print0 | xargs -0 -n1 php -l
```

CI also packages the release ZIPs.

## Roadmap

- v0.1: observe-first foundation, URL memory, semantic silo, author/entity metadata.
- v0.2: Publisher Intelligence UI + integration contracts + commercial-content classification.
- **v0.3: Rank Smart read-only SEO intelligence + historical URL change-risk scoring.**
- v0.4: semantic relationship graph, contextual internal links, News/Discover intelligence.
- v0.5: SIA AI Hindi → English publishing SaaS integration.
- v0.6: Search Console + Analytics + AdSense intelligence connectors.
- v0.7: backlink / URL value intelligence.
- v0.8: Brand Studio / guest-post marketplace.
- v1.0: integrated SIA Publisher OS.

See `docs/RANK-SMART-V0.3-READONLY.md` for the v0.3 contract and safety boundaries.

## License

License intentionally remains **TBD** while the open-source/commercial boundary is being finalized.
