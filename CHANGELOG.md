# Changelog

## 1.0.0 (2026-10-30)

First release.

- Core: languages, varieties, language relationships, orthographies, lexemes, forms, senses, multilingual definitions, examples with translations, lexical relations, translation equivalents, sources, attestations, contributors.
- Unicode: originals stored byte-for-byte; NFC, case-folded, tone-insensitive and diacritic-insensitive keys; data-driven orthography rules (inventory, tone marks, equivalences, alphabet, transliteration, word joiners).
- Portable database search with ranking; tenant, visibility and status filtering.
- Tenancy through a `TenantResolver` contract; shared reference data, tenant-owned content.
- Commands: `lexicon:install`, `lexicon:doctor`, `lexicon:languages`, `lexicon:stats`, `lexicon:validate`, `lexicon:reindex`.
- Optional modules: editorial (submissions, reviews, revisions), exchange (JSON Lines, JSON, CSV import/export, `lexicon:import`, `lexicon:export`), REST API v1.
