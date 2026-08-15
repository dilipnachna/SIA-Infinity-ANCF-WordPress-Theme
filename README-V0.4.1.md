# SIA Infinity ANCF WordPress v0.4.1-alpha.1

This patch adds editorial homepage authority without changing search identity.

## Added

- post-level Homepage Eligible control;
- Lead / Top / Breaking / Section Featured placement;
- explicit Exclude from main newsroom control;
- category-level homepage exclusion in Tools → SIA ANCF;
- Homepage Authority card in Publisher Intelligence;
- homepage selection logic that prefers explicit placements and then fills from newest eligible stories;
- safety validation proving the feature does not take URL, robots, canonical, redirect or deletion authority.

## Important

Legacy posts without homepage metadata remain eligible by default. To keep a legacy silo searchable but out of the publication homepage, exclude its category in Tools → SIA ANCF.
