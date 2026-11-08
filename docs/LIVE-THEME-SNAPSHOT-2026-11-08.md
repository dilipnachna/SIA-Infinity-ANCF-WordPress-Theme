# Live-theme source snapshot — 2026-11-08

## Provenance

- WordPress installation: `https://hindi.jaisalmernews.com/`.
- Theme slug: `sia-ancf-news`, source at `wp-content/themes/sia-ancf-news/`.
- Theme header version: `0.5.0-rc.10`.
- This repository's theme files were copied from the VPS on 8 November 2026. The production site was not modified as part of this repository sync. Repository source follows the production snapshot except for three trailing-whitespace-only line cleanups in single.php.
- Original transport bundle SHA-256 (compressed): `cf1a814e8c1558914b41989e1d58d460ced962da427cd9548a5b5f0db4469fc1`.
- Source file count: 54. No database, wp-config.php, private keys, or uploaded media included.

## Important distinctions

The public source is a **snapshot**, not a deployable release certified for every WordPress environment. Staging notes inside the theme are historical and may describe an older pre-release. GitHub plugin versions (legacy suite 0.4.2-alpha.1) differ from the deployed theme version (0.5.0-rc.10); `THEME_VERSION` and `VERSION` now track these separately.

## Observations from limited real mobile Lighthouse runs (8 November 2026)

- Hindi article: Performance 90, Accessibility 100, Best Practices 81, SEO 100.
- Hindi homepage: Performance 92, Accessibility 100, Best Practices 81, SEO 100.
- Theme ad loader starts on interaction, otherwise after an 8-second fallback. A separate Chrome DOM check observed AdSense script insertion and initialized ads after the fallback; these lab scores are not a guarantee of user experience or revenue.

## Verification before future deployments

1. Compare source files and record SHA-256 manifests.
2. Lint all PHP sources and run CI/tests.
3. Use isolated WordPress staging with real content **without private credentials copied into GitHub**.
4. Verify canonical, robots, NewsArticle/Article/Breadcrumb/Organization/author schema, XML and News Sitemap dates.
5. Test ad impressions, consent choices, navigation, accessibility, layout shifts and mobile Lighthouse with ads actually loaded.
6. Approve and record individual changes before production deployment; retain rollback archives.

No automatic GitHub-to-production deployment is configured.
