# SIA Infinity ANCF for WordPress

**Status:** v0.2.0-alpha.1 Publisher Intelligence / staging-first

SIA Infinity ANCF for WordPress is the WordPress implementation of the SIA Infinity publishing-intelligence architecture. v0.2 keeps the original safety-first foundation while turning the SIA ANCF News theme into a newsroom integration shell for future Rank Smart, SIA AI, Discover/News, analytics, revenue, and Brand Studio modules.

## Safety invariant

> Existing production SEO output remains authoritative until explicitly migrated.

The current modules do **not** automatically:

- change post URLs or permalink settings;
- emit redirects;
- alter canonical tags;
- change robots/indexing directives;
- replace Rank Math/Yoast schema;
- modify XML or News sitemaps;
- delete, merge, or noindex content;
- translate or overwrite article content;
- create English drafts without explicit user action;
- add paid/sponsored links;
- publish AI-generated or sponsored content.

## Architecture

```text
SIA ANCF News Theme = newsroom interface
SIA ANCF Core       = shared publishing metadata / governance
Rank Smart          = future SEO authority
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

theme/
  sia-ancf-news/
    inc/class-sia-publisher-intelligence.php

contracts/
  story.schema.json
  entity.schema.json
  url-memory.schema.json
  editorial-state.schema.json
  publisher-intelligence.schema.json

docs/
tools/
.github/workflows/
```

## v0.2 modules

### SIA ANCF Core
Stores shared editorial metadata. v0.2 adds a durable commercial-content classification for editorial, sponsored article, guest contribution, brand story, and existing-content sponsorship.

### SIA URL Memory
Maintains append-only local WordPress URL observations and records slug/status events. It remains observe-only and does not create redirects or block actions.

### SIA Entity & Author
Stores structured author/newsroom metadata without taking over public schema.

### SIA Semantic Intelligence
Stores an explicit **Primary Semantic Silo** from an already-assigned WordPress category.

### SIA ANCF News Theme
A staging-first newsroom theme. v0.2 adds the **SIA Publisher Intelligence** editor console with six sections:

- Content
- URL Memory
- Rank Smart
- English / SIA AI
- Discover Readiness
- Monetization

The console reads existing ANCF state and exposes integration hooks for future modules.

## Publisher Intelligence extension points

```php
apply_filters('sia_publisher_intelligence_context', $context, $post);
apply_filters('sia_publisher_integration_status', $integrations, $post);
do_action('sia_publisher_intelligence_after_sections', $post, $context);
```

Rank Smart, SIA AI, and Brand Studio should integrate through contracts/hooks instead of hard-coding their logic into the theme.

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

## URL Memory principle

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
= safe KEEP / UPDATE / MERGE / 301 / NOINDEX / 410 decision
```

v0.2 still records local WordPress URL history only; external data connectors come later.

## Installation for development/staging

1. Install and activate `sia-ancf-core`.
2. Install `sia-url-memory`, `sia-entity-author`, and `sia-semantic-intelligence`.
3. Keep the existing production SEO plugin active.
4. Activate the SIA ANCF News theme on staging first.
5. Verify the Publisher Intelligence console reads metadata without changing public SEO output.

## Build release ZIPs

```bash
python3 tools/package_release.py
```

Builds installable plugin/theme ZIPs under `dist/`.

## Validation

```bash
python3 tools/validate_contracts.py
find plugins theme -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Roadmap

- v0.1: observe-first foundation, URL memory, semantic silo, author/entity metadata.
- **v0.2: Publisher Intelligence UI + integration contracts + commercial-content classification.**
- v0.3: Rank Smart read-only SEO intelligence and historical URL risk scoring.
- v0.4: semantic relationship graph, contextual internal links, News/Discover intelligence.
- v0.5: SIA AI Hindi → English publishing SaaS integration.
- v0.6: Search Console + Analytics + AdSense intelligence.
- v0.7: backlink / URL value intelligence.
- v0.8: Brand Studio / guest-post marketplace.
- v1.0: integrated SIA Publisher OS.

See `docs/V0.2-PUBLISHER-INTELLIGENCE.md` for the v0.2 contract and safety boundaries.

## License

License intentionally remains **TBD** while the open-source/commercial boundary is being finalized.
