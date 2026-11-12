# SIA ANCF — Universal WordPress News Theme

**Fast, flexible WordPress news theme by SIA-Infinity.**

A lightweight newsroom theme designed for **any WordPress news or magazine website**. Supports multilingual WordPress sites, responsive layouts and optional built-in publishing tools.

## Features

- Responsive homepage, article pages, archives, menus and author pages
- Works with ordinary WordPress posts and categories; dedicated news / article categories are **optional**
- Native SEO metadata, JSON-LD and XML/News sitemaps when no SEO plugin provides them
- Optional native AdSense loader, analytics, and local four-signal Related Stories (semantic similarity, context, headline intent, freshness)
- No required caching or SEO plugin
- Uncropped responsive news images: preserve text and graphics inside thumbnails across homepage, cards and article pages
- End-of-article **Topics :** links to existing WordPress tags (hidden on untagged posts)

## Install

1. Use the installable theme ZIP from a successful GitHub Actions artifact, or package the theme folder as a WordPress theme ZIP.
2. In WordPress: **Appearance → Themes → Add New → Upload Theme**.
3. Activate it; configure your menu, branding and **Appearance → Customize → Newsroom Categories** (optional).

If there are no news/article categories, the homepage uses normal posts automatically. For news-only publishers, choose **Treat all posts as news** to enable NewsArticle and a current-news sitemap across categories.

## Source and status

- Theme: **v0.5.0-rc.16** (release candidate — verify on staging before production)
- WordPress / PHP: **PHP 8.1+**
- Theme source: [theme/sia-ancf-news/](theme/sia-ancf-news/)
- Older standalone components: [plugins/](plugins/) (historical; not required to activate this theme)
- THEME_VERSION tracks the theme; VERSION tracks the older plugin suite.

No automatic production deployment is configured. Public code does not include private website content, credentials or configuration.

**License:** Not yet selected. Public source visibility alone does not grant redistribution rights.
