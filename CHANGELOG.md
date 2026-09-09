# Changelog

All notable changes to this project are documented here.

The project follows [Semantic Versioning](https://semver.org/). Release tags
use the `vMAJOR.MINOR.PATCH` form.

## [Unreleased]

- Continue the M3 Laravel integration milestone.

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
