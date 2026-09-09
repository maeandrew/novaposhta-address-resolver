# Changelog

All notable changes to the Laravel bridge are documented here.

## [Unreleased]

- Continue compatibility and documentation review.

## [0.4.1] - 2026-09-10

- Clarified standalone installation through Composer and marked the
  monorepo-only `scripts/install-laravel.sh` helper explicitly.

## [0.4.0] - 2026-09-10

- Added Laravel 11, 12, and 13 support on PHP 8.2+ with a PHP 8.2/8.3 CI
  matrix.
- Cache only `resolved` and `ambiguous` results by default; configurable cache
  statuses remain available for host freshness policies.
- Added optional event dispatching for cache hits.
- Returned nonzero Artisan exit codes for review, not-found, invalid-input,
  and provider-error results.
- Removed the unused `SerializesModels` trait from the DTO-only queue job.

## [0.3.1] - 2026-09-10

- Allowed installation with both the current core `0.3.x` line and the earlier
  compatible `0.2.x` line.

## [0.3.0] - 2026-09-10

- Added the Laravel 13/PHP 8.3 service provider and named driver manager.
- Added opt-in cache, resolution events, queue job, Artisan command, and an
  explicit host-owned result mapper.
- Added offline Orchestra Testbench coverage and independent Composer quality
  checks.
