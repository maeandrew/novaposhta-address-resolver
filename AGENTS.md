# Instructions for the implementation agent

This repository is a standalone open-source project. It must remain independent
of any private shop, CRM, production database, customer data, or private
infrastructure.

Before writing code, read these files in order:

1. `README.md`
2. `docs/IMPLEMENTATION_PLAN.md`
3. `docs/AI_PROVIDERS.md`
4. `examples/README.md` and all fixtures under `examples/fixtures/`

## Objective

Build a provider-agnostic Nova Poshta address resolver with:

- a pure PHP domain core;
- an extension point for any Nova Poshta data client (ready SDK or custom HTTP
  implementation);
- an optional extension point for multiple AI providers;
- a separate Laravel integration layer;
- deterministic, testable, review-friendly resolution results.

## Hard constraints

- Do not add references to private applications, domains, suppliers, customers,
  production credentials, real database dumps, or private Telegram data.
- The core must not depend on Laravel, Eloquent, Guzzle, Filament, a specific
  AI SDK, or a specific Nova Poshta SDK.
- Do not make live API calls in the test suite or CI.
- Never let an AI response invent or directly persist a settlement/warehouse
  reference. AI may suggest candidates; the resolver must validate them against
  provider data.
- Preserve an `ambiguous`/`needs_review` result instead of silently guessing.
- Keep all external integrations behind interfaces and optional adapters.
- Use strict types, immutable DTOs/value objects where practical, and clear
  exceptions for provider failures.

## Working style

- Implement the pure core first and keep its tests independent of Laravel.
- Add fake providers and deterministic fixtures before adding real adapters.
- Keep public APIs small and documented with runnable examples.
- Do not rewrite the specification merely to make implementation easier; record
  any necessary deviation in `docs/DECISIONS.md`.
- Run formatting, static analysis, and the complete test suite before declaring
  a milestone complete.

## Definition of done

The first implementation milestone is complete only when:

- the core resolves all positive and negative fixture cases;
- custom `LocationProvider` implementations work without framework classes;
- a no-AI mode works;
- at least one AI adapter and a fake AI provider are covered by tests;
- the Laravel bridge is optional and does not leak framework types into core;
- the README contains installation, extension, security, and limitations
  documentation;
- CI runs offline and checks tests, formatting, and static analysis.
