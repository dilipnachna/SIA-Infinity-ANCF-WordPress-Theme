# SIA ANCF v0.4.1 — Editorial Homepage Control

## Principle

```text
Published
  != Homepage Eligible
  != Lead Story
  != Top Story
  != Breaking
```

Publication and search identity are separate from homepage presentation authority.

A post can remain published, indexed, linked, monetized and reachable at its existing URL while being excluded from the main newsroom homepage.

## Post-level controls

The **SIA ANCF Story** editor panel stores durable post metadata:

- `Eligible for homepage`
- `Placement: Normal`
- `Placement: Lead`
- `Placement: Top`
- `Placement: Breaking`
- `Placement: Section Featured`
- `Exclude from main newsroom`

Only one effective Lead is retained when an editor explicitly saves a new Lead story. Previous Lead metadata is demoted to Normal.

Legacy posts without `_sia_home_eligible` metadata remain homepage-eligible by default. This avoids silently removing historical editorial content during upgrade.

## Category-level exclusion

Go to:

```text
WordPress Admin -> Tools -> SIA ANCF -> Homepage Editorial Control
```

Editors with `manage_options` can exclude entire categories from the main newsroom. This is intended for result pages, utility content, legacy silos or categories that should remain searchable/directly accessible but should not define the publication homepage.

Category exclusion affects:

- Lead fallback
- Top Stories
- Breaking/Latest strip
- Latest News
- homepage category sections
- More Latest
- fallback navigation categories

It does **not** alter the category archive or direct post URLs.

## Homepage selection order

### Lead

```text
explicit Lead
    -> otherwise newest eligible story
```

### Top Stories

```text
explicit Top stories
    -> fill remaining slots with newest eligible stories
    -> never duplicate Lead
```

### Headline strip

```text
explicit Breaking stories
    -> fill remaining slots with newest eligible stories
```

### Category sections

```text
explicit Section Featured in category
    -> fill remaining slots with newest eligible stories from category
```

## Safety boundary

Homepage controls must never automatically:

- change a slug or permalink;
- redirect a URL;
- set `noindex` or robots directives;
- alter canonical output;
- remove a URL from XML/News sitemaps;
- delete, trash or 410 a post;
- change Rank Math / Yoast / AIOSEO authority;
- change Search Console state;
- disavow links;
- publish or unpublish content.

The feature is a **presentation authority layer**, not an SEO execution layer.

## Publisher Intelligence

The editor dashboard now exposes an additional **Homepage Authority** card showing:

- effective eligibility;
- placement;
- post-level eligibility/exclusion;
- category-level exclusion.

This makes the reason a story is or is not eligible for the homepage visible to the editor.
