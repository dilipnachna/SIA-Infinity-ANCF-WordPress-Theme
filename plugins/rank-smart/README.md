# Rank Smart

**Version:** 0.4.1-alpha.1  
**Mode:** read-only

Rank Smart is the SEO intelligence layer for SIA Infinity ANCF. In v0.4.1 its SEO authority boundary is unchanged: it audits posts/pages, local URL history and provider-ready evidence without taking over production SEO output.

## What Rank Smart currently does

- shows a read-only SEO audit on posts and pages;
- observes the current permalink and WordPress canonical candidate;
- reports public/index candidate state without setting robots directives;
- reports title/slug length, Latin slug state, excerpt, featured-image width, author and Primary Semantic Silo;
- reads SIA URL Memory observations and slug-change history;
- detects an existing SEO authority such as Rank Math, Yoast or AIOSEO;
- exposes provider contracts for Search Console, Analytics, AdSense and backlinks;
- calculates an evidence-based URL **change-risk** score;
- integrates with the SIA Publisher Intelligence console when the SIA theme is active.

## What it does not do

It does not output SEO metadata or execute SEO changes. It does not change titles, meta, canonicals, robots, schema, sitemaps, redirects, URLs or content.

Homepage editorial placement introduced in ANCF v0.4.1 is a separate presentation authority. Rank Smart does not treat homepage exclusion as an instruction to noindex, redirect, delete or devalue a URL.

## Change risk is not SEO score

A higher change-risk score means more evidence exists that the URL should be handled carefully before a destructive or identity-changing action. It does **not** mean Google will rank the page higher.

A low score is not permission to delete because external provider data may still be missing.

## Provider hooks

```php
add_filter('rank_smart_provider_state', function (array $state, string $provider): array {
    return $state;
}, 10, 2);

add_filter('rank_smart_provider_evidence', function (array $evidence, string $provider, WP_Post $post): array {
    return $evidence;
}, 10, 3);
```

Reserved provider identifiers: `search_console`, `analytics`, `adsense`, `backlinks`.
