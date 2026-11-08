# SIA Infinity ANCF News — WordPress

**Theme source:** `0.5.0-rc.10` · **Source snapshot:** 8 November 2026

This repository now includes the **SIA ANCF News theme source deployed on the Hindi Jaisalmer News WordPress installation** at the snapshot date. It supersedes the previous 14-file theme source from v0.4.2-alpha.1. Existing Git history, contracts, legacy plugins, and earlier development documentation are retained.

## What is included

- `theme/sia-ancf-news/` — 54-file source snapshot of SIA ANCF News v0.5.0-rc.10, including built-in newsroom, responsive templates, native SEO, sitemaps, NewsArticle/Article JSON-LD, author profiles, related-content modules, WebSub/IndexNow integration points and ad/analytics loaders.
- `plugins/` and `contracts/` — **historical v0.4.2-alpha.1 suite components**, preserved for backward reference. Do not assume they are the same versions or active components running on the site.
- `tools/` — build/validation tooling. `VERSION` tracks the **legacy plugin suite**; `THEME_VERSION` tracks the **theme**.
- `docs/LIVE-THEME-SNAPSHOT-2026-11-08.md` — deployment source, provenance, security boundaries and audit.
- `docs/SEO-AEO-GEO-AIO-AUDIT-2026-11-08.md` — observed strengths, gaps and next steps.

## Safety / deployment contract

This repository is **public source code**, not a copy of WordPress content, the database or server credentials. It does not include `wp-config.php`, secrets, private keys, uploaded media, or production environment files.

**Do not automatically deploy** these files or migrate plugins to a live site. Back up database and theme first; validate on an isolated WordPress staging copy, check PHP warnings, canonical URLs, XML/News sitemaps, Article/NewsArticle schema, ad impressions, mobile UI, redirects, cache behaviour and Core Web Vitals before any promotion.

### Versioning

| Component | Version/status |
| --- | --- |
| SIA ANCF News theme | `0.5.0-rc.10` — source snapshot of live Hindi theme |
| Legacy plugin suite in `plugins/` | `0.4.2-alpha.1` — historical source; not claimed production parity |
| Existing docs/contracts | Retained historical development material |

## Current theme features

The theme implements native functions for:
- WordPress SEO metadata and JSON-LD graph with extension filters;
- standard and news/video sitemap routes;
- publisher and author metadata; optional entity fields;
- native ad placement and interaction/fallback loading;
- article rendering, related stories, category sections, mobile navigation and article TOC;
- internal semantic modules, URL memory, IndexNow, WebSub and analytics integration points.

Not all features have undergone independent end-to-end testing. `inc/native-entity-author.php` currently stores editor-supplied person/organization identity metadata; live author output is not automatically updated by those fields. See the audit for outstanding work.

## Build and checks

```bash
python3 tools/validate_contracts.py
python3 tools/validate_rank_smart_safety.py
python3 tools/validate_homepage_editorial_safety.py
python3 tools/validate_theme_hardening.py
python3 tools/package_release.py
python3 tools/validate_staging_release.py
```

PHP lint requires PHP 8.1+ and can be run with:

```bash
find plugins theme -name '*.php' -print0 | xargs -0 -n1 php -l
```

The build produces staging artifacts for both versions. The outer kit is **not** a WordPress-installable ZIP.

## Licenses and attribution

A definitive open-source or commercial license has **not** been selected. Do not assume that a public repository grants permission to redistribute or commercially relicense it. The historical codebase is retained with its existing copyright and attribution notices.

Previous development notes: [v0.4.2 archive](docs/README-v0.4.2-historical.md).
