# Architecture decisions

[English](DECISIONS.md) · [Українська](uk/DECISIONS.md)

This file records decisions that should remain stable unless a later change is
documented with its motivation and migration impact.

## D001 — Framework-free core

The resolver domain is a pure PHP package. Laravel, Eloquent, Filament, HTTP
clients, AI SDKs, and Nova Poshta SDKs are integrations, not core dependencies.

**Reason:** the package must be usable from Laravel, Symfony, a CLI script, or
a custom application without forcing a framework or vendor client.

## D002 — Provider is the source of truth

AI and fuzzy matching can suggest a candidate, but only a normalized record
returned by `LocationProvider` can be resolved or persisted.

**Reason:** a model must never invent a city or warehouse reference.

## D003 — Ambiguity is a first-class result

The resolver returns `ambiguous` with candidates and diagnostics when evidence is
insufficient. It does not silently pick the first API result.

**Reason:** a wrong delivery branch is more expensive than a short manual review.

## D004 — AI is recommended and pluggable

AI is the recommended quality layer for production free-form input, especially
when messages contain typos, mixed languages, or missing labels. The
deterministic pipeline remains a required fallback for outages, privacy limits,
offline work, and tests. Structured AI providers are registered through an
interface and may be chained with explicit fallback rules.

**Reason:** installations differ in provider availability, cost, privacy policy,
and model preference.

## D005 — Persistence belongs to the host application

The core returns DTOs and never writes orders, addresses, or audit rows. The
Laravel package may provide mapping callbacks, jobs, events, and cache, but it
must not assume a host schema.

**Reason:** order systems use different models and field names.

## D006 — Synthetic fixtures only

Tests and demos use synthetic `fixture-*` references and public-looking example
text. No real customer or production data is committed.

**Reason:** examples must be safe to publish and deterministic in offline CI.

## D007 — Monorepo during development, split packages at release

Core, Laravel, and optional adapters may be developed together under
`packages/`, each with its own Composer manifest. They must not import across
boundaries in the wrong direction. If Packagist publishing is easier with
separate repositories, split them without changing the public core contracts.

**Reason:** shared development is convenient, while independent installation is
the public goal.

## D008 — Public package namespace

The development package is published as `maeandrew/novaposhta-address-resolver`
and uses the `MaeAndrew\\NovaPoshtaAddressResolver` PSR-4 namespace.

**Reason:** the package identity should be attributable to its public maintainer
while keeping Nova Poshta integration concepts inside the package namespace.

## D009 — PSR HTTP for the first AI adapter

The first real AI adapter is an optional OpenAI Responses API package under
`packages/openai`. It depends on PSR HTTP interfaces and accepts the host
application's client and factories instead of requiring a vendor SDK.

**Reason:** the adapter remains small, testable offline, and compatible with
different HTTP clients while the core keeps zero network dependencies. The
package can later add a separate SDK adapter without changing core contracts.

## D010 — Laravel integration is a separate package

Laravel support lives in `packages/laravel` and supports Laravel 11, 12, and 13
on PHP 8.2+. It binds the core resolver through the container, while cache,
events, queue jobs, and Artisan commands remain optional integration services.
Host applications own persistence and model mapping. The published package is
`maeandrew/novaposhta-address-resolver-laravel`.

**Reason:** framework upgrades and application schemas should not change the
core package. The bridge is published separately without adding Laravel
dependencies to core consumers.
