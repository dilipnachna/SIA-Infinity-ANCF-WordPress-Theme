# Architecture

## Design rule

Theme renders. Plugins model and govern. Platform-independent contracts define meaning.

```text
WordPress
   |
   +-- SIA ANCF Core
   |      +-- runtime mode
   |      +-- story vocabulary
   |      +-- shared APIs
   |
   +-- URL Memory
   |      +-- append-only observations
   |
   +-- Entity & Author
   |      +-- Person / Organization metadata
   |
   +-- Semantic Intelligence
          +-- Primary Semantic Silo
          +-- future relationship graph

Public rendering
   +-- current production theme (initially)
   +-- SIA ANCF News theme (staging)
```

## Observe-first mode

`SIA_ANCF_RUNTIME_MODE` defaults to `observe`. Modules may record metadata and display admin diagnostics, but must not seize control of existing SEO output.

## Future authority migration

Each SEO capability will move through:

```text
OBSERVE -> COMPARE -> SHADOW -> EXPLICIT AUTHORITY
```

Never switch canonical/schema/robots/redirect ownership as a side effect of a plugin update.
