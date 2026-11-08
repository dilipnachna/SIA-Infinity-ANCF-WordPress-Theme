# SEO / AEO / GEO / AI-search audit — 2026-11-08

Read-only public-site observations; search indexing and AI citations **cannot** be inferred from HTML alone.

## Verified technical SEO

- Canonical links, `hi-IN` language, responsive layout and meta descriptions on sampled pages.
- Native XML sitemap index exposing post, page, category, news and video sitemaps.
- Sampled NewsArticle JSON-LD contains headline, dates, publisher, author and image; publisher graph and breadcrumbs present.
- Recent news sitemap showed current articles only.
- Largest-featured images of newer posts commonly 1200×675.
- Hindi homepage's main heading still says **Latest News & Articles** in English; localization is a straightforward improvement.
- No Rank Math or page-cache plugin needed for the basic public output observed.

## AEO / content gaps (59 public articles, sampled 8 November 2026)

- 49 article bodies lacked links.
- 51 article bodies lacked *external* citation links.
- Only 2 had internal in-body hyperlinks.
- 31 article bodies had no H2 (this is not inherently wrong for short news).
- The new articles give the key answer and local detail early; dated factual information should cite primary sources such as PIB, IMD or source publications where relevant.

## GEO / entity and trust

- `NewsMediaOrganization`, author and breadcrumb graph available.
- News Desk is a team but its schema node currently appeared as `Person`. Editor choice of organization exists in `inc/native-entity-author.php` but does not currently drive the output; implement a tested schema bridge without falsely identifying a person.
- About, Contact, Privacy pages exist; the Privacy page contains template English language and editorial/corrections policies were not found at the probed paths. Any public identity, credentials, and editorial claims must be verified before publication.

## AI search

- Public test requests with several crawler User-Agent strings returned HTTP 200. PerplexityBot/Perplexity-User and ClaudeBot strings returned HTTP 403 via Cloudflare while origin returned 200.
- User-Agent spoofed requests do **not** prove how verified bot IPs are treated. Inspect Cloudflare Security Events and bot verification before creating narrow allow rules. Do not open a blanket user-agent bypass.
- `llms.txt` was 404; optional, not an officially required Google AI Overview mechanism.
- No site can guarantee AI citations, AI Overview inclusion or increased traffic via these changes.

## Proposed next tested patches

1. Homepage H1 translation to editorially approved Hindi; regression checks for accessibility.
2. News Desk Organization-vs-Person author schema, add real journalist identity only when verified.
3. Structured source references/editing support and conservative internal links; preserve editorial control.
4. Accurate publisher/contact/corrections policy content, supplied by newsroom.
5. Cloudflare verified-bot access review, logging and narrow changes if needed.
6. Search Console indexing, Discover and AI-visibility measurements once authorized read-only data is available.

These are **proposed enhancements**; the snapshot itself does not assert that they were shipped.
