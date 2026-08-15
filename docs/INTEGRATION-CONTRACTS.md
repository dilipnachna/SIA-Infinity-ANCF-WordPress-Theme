# SIA WordPress Integration Contracts

The SIA ANCF theme is an interface, not the authority for SEO, AI, or monetization. Future modules integrate through stable WordPress hooks.

## Rank Smart

Rank Smart owns SEO output and search intelligence. It may enrich the Publisher Intelligence context with SEO score, canonical state, index state, Search Console, Analytics, AdSense, backlink, and URL-value signals.

It should not require theme-specific database tables.

## SIA AI

SIA AI owns paid Hindi → English translation, human-like writing, English draft creation, and AI-generated SEO suggestions. The theme may expose controls, but execution must come from the SIA AI client/service and require an explicit user action.

## Brand Studio

Brand Studio owns sponsored-content orders, guest contributions, pricing, payment state, disclosures, campaign reporting, and commercial-value calculations. ANCF Core only stores durable content classification.

## Extension hooks

```php
add_filter('sia_publisher_integration_status', function (array $integrations, WP_Post $post): array {
    $integrations['rank_smart'] = [
        'active' => true,
        'label'  => 'Connected',
    ];
    return $integrations;
}, 10, 2);
```

```php
add_filter('sia_publisher_intelligence_context', function (array $context, WP_Post $post): array {
    $context['rank_smart'] = [
        'seo_score' => 82,
        'index_state' => 'indexed',
    ];
    return $context;
}, 10, 2);
```

```php
add_action('sia_publisher_intelligence_after_sections', function (WP_Post $post, array $context): void {
    // Render an integration-owned editor control or report.
}, 10, 2);
```

Integration modules must sanitize output, check capabilities/nonces for writes, and avoid silently overriding another active authority.
