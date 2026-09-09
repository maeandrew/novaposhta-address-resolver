# Changelog

All notable changes to this project are documented here.

The project follows [Semantic Versioning](https://semver.org/). Release tags
use the `vMAJOR.MINOR.PATCH` form.

## [Unreleased]

- Continue M4 polish and release hardening.

## [0.3.1] - 2026-09-10

- Kept optional adapter constraints compatible with both core `0.2.x` and
  `0.3.x`.
- Fixed DDEV monorepo install scripts to use temporary core package mirrors,
  avoiding recursive path installs inside nested `vendor` directories.

## [0.3.0] - 2026-09-10

- Added the optional Laravel 13/PHP 8.3 bridge under `packages/laravel`.
- Added named driver configuration, service-container bindings, opt-in cache,
  resolution events, a queue job, and the `novaposhta:resolve` Artisan command.
- Added an explicit host-owned mapping callback that only runs for resolved
  results; ambiguous results remain available for review.
- Added offline Orchestra Testbench coverage and published the separate Laravel
  package as `maeandrew/novaposhta-address-resolver-laravel`.

## [0.2.0] - 2026-09-10

- Extended deterministic parsing for Ukrainian and Russian spellings,
  abbreviations, postomats, pickup points, and bare inputs such as `Київ 133`.
- Added an offline terminal demo and expanded synthetic fixture coverage.
- Added structured AI contracts, redacted prompts, fallback interpreters, and
  candidate validation that rejects unknown provider references.
- Added the optional PSR HTTP based OpenAI Responses API adapter under
  `packages/openai`, with offline adapter tests.
- Added Ukrainian documentation, Semantic Versioning rules, and GitHub release
  automation.

## Release rules

- `v0.1.0` — deterministic core: M0 and M1.
- `v0.2.0` — structured AI extension and an optional provider adapter: M2.
- `v0.3.0` — optional Laravel bridge: M3.
- `v1.0.0` — first stable API after compatibility review.

Every release tag must pass the GitHub Actions quality pipeline. The release
workflow creates a GitHub Release and generates its notes from merged changes.
