# SIA Infinity ANCF for WordPress

**Status:** v0.4.2-alpha.1 Theme Hardening / Rank Smart read-only / staging-first

SIA Infinity ANCF for WordPress combines a newsroom-first WordPress theme with durable publishing metadata, URL memory and read-only SEO intelligence.

## Safety invariant

> Presentation may change. Existing production SEO authority does not change unless explicitly migrated.

The current release line does **not** automatically change URLs, redirects, canonicals, robots, schema, XML/News sitemaps, SEO titles/meta, publication state or article truth. Rank Math/Yoast/AIOSEO may remain active while Rank Smart observes evidence in read-only mode.

## Architecture

```text
SIA ANCF News Theme = newsroom presentation + Publisher Intelligence UI
SIA ANCF Core       = shared publishing metadata / governance
SIA URL Memory      = append-only local URL history
Entity & Author     = structured newsroom identity metadata
Semantic Intelligence = Primary Semantic Silo metadata
Rank Smart          = read-only SEO evidence + URL change-risk intelligence
SIA AI              = future Hindi → English / human-writing SaaS
Brand Studio        = future monetization / sponsored-content workflow
```

## v0.4 Newsroom Frontend

The theme is no longer a bare staging shell. v0.4 adds a responsive publication interface designed for a real news site:

- publication top bar, brand row, search and accessible mobile navigation;
- dynamic primary navigation with category fallback;
- newsroom front page with latest-headline strip;
- lead story + Top Stories layout;
- Latest News grid;
- dynamic category sections generated from existing top-level categories;
- compact latest-news stream;
- professional archive, search and author pages;
- dedicated standalone page template;
- story template with category, headline, deck, byline, update date, hero image, author box and related stories;
- responsive footer and section discovery;
- image placeholders when older posts have no featured image;
- mobile, tablet and desktop layouts;
- reduced-motion and keyboard-navigation support.

The frontend does not hard-code one site's category IDs. It adapts to the site's existing WordPress categories and menus.

## v0.4.1 Editorial Homepage Control

v0.4.1 separates search/publication identity from homepage presentation authority:

- post-level Homepage Eligible control;
- Lead / Top / Breaking / Section Featured placements;
- explicit Exclude from main newsroom control;
- category-level homepage exclusion;
- Homepage Authority card in Publisher Intelligence;
- safe fallback behaviour for legacy posts without new metadata.

A legacy URL can remain published, indexed and directly accessible while being excluded from the main newsroom homepage.

## v0.4.2 Theme Hardening

v0.4.2 finishes presentation-level production gaps without giving the theme SEO authority:

- deduplicates Lead, Top Stories, Latest News, category sections and More Latest;
- uses the assigned Primary Menu's top-level category order as homepage section priority;
- resolves the Latest News `View all` destination through the configured Posts page or a published `/news/` fallback;
- detects same-day meaningful article updates by timestamp, not date-only comparison;
- uses the 1200×675 newsroom hero size on stories and standalone pages;
- separates sidebar and bottom Related Stories so the same recommendations are not repeated twice;
- enables paginated post/page content;
- adds responsive article-table rendering for mobile;
- loads the text domain and editor stylesheet;
- adds a dedicated CI hardening validator.

See `docs/THEME-HARDENING-V0.4.2.md`.

## Rank Smart

Rank Smart remains read-only in this release line. It audits:

- permalink and WordPress canonical candidate;
- public/index candidate state;
- title/slug facts;
- featured-image width;
- author and Primary Semantic Silo;
- URL Memory observations and recorded slug changes;
- existing SEO authority;
- provider-ready slots for Search Console, Analytics, AdSense and backlinks;
- evidence-based URL change risk.

A LOW URL change-risk score is never permission to delete. Missing external data remains missing evidence.

## Installation / staging

1. Install and activate `sia-ancf-core`.
2. Install and activate `sia-url-memory`.
3. Install and activate `sia-entity-author`.
4. Install and activate `sia-semantic-intelligence`.
5. Install and activate `rank-smart`.
6. Keep the existing production SEO plugin active.
7. Install and activate `sia-ancf-news`.
8. Assign the Primary Menu under WordPress menus if desired; its top-level category order also becomes the preferred homepage section order.
9. Verify homepage, story, page, category, author, search and mobile rendering before removing the previous theme.

## Build release ZIPs

```bash
python3 tools/package_release.py
python3 tools/validate_staging_release.py
```

CI validates contracts, Rank Smart read-only safety, homepage editorial safety, theme hardening, PHP syntax, WordPress component-version parity, installable ZIPs and the downloadable staging kit.

## Roadmap

- v0.1: observe-first foundation, URL memory, semantic silo, author/entity metadata.
- v0.2: Publisher Intelligence UI + integration contracts + commercial-content classification.
- v0.3: Rank Smart read-only SEO intelligence + URL change-risk scoring.
- v0.4: professional newsroom frontend + responsive publication templates.
- v0.4.1: editorial homepage authority controls without changing search identity.
- **v0.4.2: production theme hardening and newsroom deduplication.**
- v0.5: semantic relationship graph + contextual internal links + News/Discover intelligence.
- v0.6: Search Console connector and persistent search evidence.
- v0.7: Analytics + AdSense + backlink / URL value intelligence.
- v0.8: SIA AI Hindi → English publishing SaaS integration.
- v0.9: Brand Studio / guest-post marketplace.
- v1.0: integrated SIA Publisher OS.

See `docs/NEWSROOM-FRONTEND-V0.4.md`, `docs/EDITORIAL-HOMEPAGE-CONTROL-V0.4.1.md`, `docs/THEME-HARDENING-V0.4.2.md` and `docs/RANK-SMART-V0.3-READONLY.md` for current boundaries.

## License

License intentionally remains **TBD** while the open-source/commercial boundary is being finalized.
