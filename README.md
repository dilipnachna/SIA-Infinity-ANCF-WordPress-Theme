# SIA Infinity ANCF for WordPress

**Status:** v0.1.0-alpha.1 foundation / observe-first

SIA Infinity ANCF for WordPress is the first WordPress implementation of the SIA Infinity publishing-intelligence architecture. It is intentionally designed as a **safe production bridge**: the first release observes, records, and models editorial/SEO state without taking over existing canonical, redirect, robots, sitemap, or schema output.

## v0.1 safety invariant

> Existing production SEO output remains authoritative until explicitly migrated.

The v0.1 plugins do **not** automatically:

- change post URLs or permalink settings;
- emit redirects;
- alter canonical tags;
- change robots/indexing directives;
- replace Rank Math/Yoast schema;
- modify XML or News sitemaps;
- delete, merge, or noindex content;
- publish AI-generated content.

## Repository structure

```text
plugins/
  sia-ancf-core/
  sia-url-memory/
  sia-entity-author/
  sia-semantic-intelligence/

theme/
  sia-ancf-news/

contracts/
  story.schema.json
  entity.schema.json
  url-memory.schema.json
  editorial-state.schema.json

docs/
tools/
.github/workflows/
```

## v0.1 modules

### SIA ANCF Core
Provides the shared version, observe-only runtime mode, story metadata vocabulary, and a small admin status screen.

### SIA URL Memory
Creates an append-only WordPress table that records observed public URLs before/after edits, trashing, and deletion. It does not create redirects or block actions in v0.1.

### SIA Entity & Author
Adds structured author/newsroom metadata to WordPress users without changing public author-page markup or schema.

### SIA Semantic Intelligence
Adds an explicit **Primary Semantic Silo** selection using an already-assigned WordPress category. It does not alter category URLs or render related posts in v0.1.

### SIA ANCF News Theme
A lightweight standalone classic theme intended for staging. The theme is presentation-only; SEO authority remains outside the theme.

## Primary Silo principle

The WordPress equivalent of the SIA Blogger Primary Silo is an explicitly selected primary category:

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

A URL is treated as a historical publishing asset, not a disposable string. The long-term design will combine:

```text
URL history
+ Search Console history
+ backlink/referring-domain evidence
+ internal-link evidence
+ index state
+ replacement relationship
= safe KEEP / UPDATE / MERGE / 301 / NOINDEX / 410 decision
```

v0.1 starts with local WordPress URL history only.

## Relationship to SIA Infinity AI Blogger

The Blogger implementation remains a separate platform adapter. ANCF WordPress will gradually extract shared platform-independent contracts for:

- primary semantic silos;
- entity identity;
- semantic relationship graphs;
- URL memory;
- author/newsroom identity;
- editorial state;
- Discover/News readiness;
- safe publishing governance.

## Installation for development/staging

1. Install and activate `sia-ancf-core`.
2. Install `sia-url-memory`, `sia-entity-author`, and `sia-semantic-intelligence`.
3. Keep the existing production SEO plugin active.
4. Do **not** activate the SIA ANCF News theme on production before staging validation.
5. Verify URL Memory records snapshots without changing public output.

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
- v0.2: read-only SEO audit engine and historical URL risk scoring.
- v0.3: semantic relationship graph and contextual internal-link preview.
- v0.4: News/Discover readiness audits and schema comparison mode.
- v0.5: controlled SEO authority migration with explicit feature flags.
- later: GSC memory, backlink evidence, editorial agents, Brand Studio, native ANCF CMS.

## License

License intentionally left **TBD** for the initial architecture skeleton. Choose the final open-source/commercial licensing model before public release.
