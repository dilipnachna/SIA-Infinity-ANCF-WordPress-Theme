# SIA ANCF News v0.4.2 — Theme Hardening

## Goal

Finish the newsroom theme as a production presentation layer before moving more intelligence into Rank Smart or future SaaS modules.

The safety boundary remains unchanged:

```text
Theme owns presentation, navigation, editorial placement and frontend performance.
Theme does not own canonical, robots, redirects, schema, XML/News sitemaps or destructive URL lifecycle.
```

## Hardening changes

### Homepage

- Prevent repeated stories across Lead, Top Stories, Latest News, category sections and More Latest.
- Breaking/Latest headline strip remains a separate utility surface and may intentionally repeat a major story.
- Use the assigned Primary Menu's top-level category order as the preferred homepage section order.
- Fill any remaining section slots from populated top-level categories.
- Respect category-level homepage exclusions and post-level eligibility/placement controls.
- Resolve the Latest News `View all` destination through the configured WordPress Posts page, with a published `/news/` page fallback.

### Story pages

- Detect meaningful updates by published/modified timestamps rather than date-only comparison, so same-day newsroom edits can show `Updated` correctly.
- Use the 1200×675 `sia-news-hero` image size for the single-story hero instead of requesting the unrestricted full upload.
- Keep sidebar and bottom Related Stories as distinct result sets instead of repeating the same stories twice.
- Support paginated article content with `wp_link_pages()`.

### Standalone pages

- Use the newsroom 1200×675 hero image size.
- Support paginated page content with `wp_link_pages()`.

### Editor / internationalization

- Load the theme text domain from `/languages`.
- Apply the public stylesheet to the block editor via `add_editor_style()` so editor presentation more closely matches the frontend.

## CI

`tools/validate_theme_hardening.py` protects these invariants and rejects accidental acquisition of SEO authority by the theme.

## Not included

The following remain separate future/plugin work:

- semantic relationship graph;
- automated contextual internal-link decisions;
- Search Console ingestion;
- Discover/Search outcome memory;
- canonical/robots/schema/sitemap authority;
- redirect or destructive URL execution;
- Analytics, AdSense or backlink connectors;
- AI content generation/translation;
- sponsored-content marketplace.
