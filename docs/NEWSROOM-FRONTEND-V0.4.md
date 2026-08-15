# SIA ANCF News v0.4 — Newsroom Frontend

## Goal

Turn the staging shell into a usable publication frontend without giving the theme SEO authority that belongs to Rank Smart or an existing production SEO plugin.

## Frontend contract

```text
Theme owns:
- presentation
- responsive layout
- navigation UI
- story/archive/page templates
- image presentation
- newsroom discovery surfaces

Theme does not own:
- canonical authority
- robots/noindex authority
- redirects
- schema authority
- XML / News sitemap authority
- destructive URL lifecycle actions
- AI truth or publication authority
```

## Front page

The front page is data-driven and uses existing WordPress posts/categories rather than site-specific category IDs.

```text
Latest headline strip
        ↓
Lead Story + Top Stories
        ↓
Latest News
        ↓
Top-level Category Sections
        ↓
More Latest
```

If no menu is assigned, the navigation falls back to Home plus populated top-level categories. If a story has no featured image, a publication/category placeholder is shown so legacy archives do not collapse visually.

## Story page

The single-story template renders:

- category context;
- headline;
- WordPress excerpt as deck when present;
- author and publication date;
- modified date when different;
- high-priority hero image;
- readable long-form body;
- author box;
- related stories based on the story's category;
- compact more-stories rail.

Related-story presentation is deterministic WordPress taxonomy retrieval in this release. It is not yet the future semantic/Fibonacci relationship engine.

## Page integrity

v0.4 adds a dedicated `page.php`. This is important because standalone pages such as About, Contact and editorial policies must render as pages instead of falling through the generic post index.

## Responsive behaviour

Desktop uses a wide newspaper-style lead layout and multi-column grids. Tablet collapses the lead and story rail progressively. Mobile uses a single-column story flow, compact cards and an explicit keyboard-accessible navigation toggle.

The theme does not force dark mode from the browser's color preference; publication colors remain stable.

## Accessibility

- skip-to-content link;
- semantic navigation landmarks;
- mobile menu `aria-expanded` state;
- Escape key closes the mobile navigation;
- visible keyboard focus through browser defaults and skip-link styling;
- reduced-motion preference respected;
- image links receive story-title labels.

## Performance

- lead image uses eager loading and high fetch priority;
- card images lazy-load;
- responsive WordPress image markup remains intact;
- one small dependency-free navigation script;
- no frontend framework or icon library;
- CSS/JS versioned from the WordPress theme header.

## Safety

v0.4 is a presentation milestone. Rank Smart remains read-only. Existing Rank Math/Yoast/AIOSEO output may remain active while the theme is tested.

Do not remove the previous production theme until the following have been checked on staging/live preview:

```text
Homepage
Single story
Static page
Category archive
Author archive
Search
404
Desktop
Mobile
Menu
Logo
Featured images
Rank Math metadata / canonical / schema
```
