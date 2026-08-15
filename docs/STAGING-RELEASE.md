# SIA Infinity ANCF WordPress — Staging Release

The CI workflow now publishes a downloadable staging artifact after validation succeeds.

## Artifact contents

The artifact contains:

- `SIA-Infinity-ANCF-WordPress-Staging-Kit-v<version>.zip`
- installable plugin ZIPs for ANCF Core, URL Memory, Entity/Author, Semantic Intelligence and Rank Smart
- installable `sia-ancf-news` theme ZIP
- `INSTALL-STAGING.md`
- `staging-manifest.json`

The outer **Staging Kit** ZIP is a transport bundle. Do not upload it directly to WordPress. Extract it first, then upload each plugin ZIP and the theme ZIP.

## Install order

1. `sia-ancf-core`
2. `sia-url-memory`
3. `sia-entity-author`
4. `sia-semantic-intelligence`
5. `rank-smart`
6. `sia-ancf-news` theme

Keep the current production SEO plugin active during v0.3 validation because Rank Smart remains read-only.

## CI guarantees

Before the artifact is uploaded, CI validates:

- ANCF JSON contracts;
- Rank Smart read-only safety invariant;
- PHP syntax;
- WordPress ZIP root structure;
- required staging-kit contents.

Artifacts are retained for 30 days by the workflow.
