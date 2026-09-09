# Implementation plan

[English](IMPLEMENTATION_PLAN.md) · [Українська](uk/IMPLEMENTATION_PLAN.md)

## 1. Product definition

Implement a standalone address-resolution engine for Nova Poshta data. Input is
an address typed by a customer or manager. Output is a typed, serializable
resolution result containing a settlement, a warehouse, confidence, candidates,
diagnostics, and a status.

The engine must be useful in three situations:

1. an application already has a Nova Poshta SDK;
2. an application has its own HTTP/database client;
3. an application wants deterministic matching with optional AI assistance.

This is an address resolver, not a complete delivery SDK and not an order
management system.

## 2. Non-goals

Do not include the following in the core:

- order or customer models;
- database migrations or persistence;
- Laravel service providers, jobs, queues, cache, or Filament components;
- a hard dependency on one HTTP client or one AI vendor;
- shipment creation, tracking, tariffs, sender accounts, or payment logic;
- live API calls in tests;
- automatic mutation of caller data.

Those capabilities belong to optional adapters or to the host application.

## 3. Suggested repository layout

The initial development repository may be a monorepo, but the package boundaries
must be clean enough to publish separately later:

```text
src/                         # framework-free core while the API stabilizes
tests/
packages/
  openai/
    composer.json
    src/
    tests/
  laravel/
    composer.json
    src/
      NovaPoshtaAddressResolverServiceProvider.php
      AddressResolverManager.php
      Console/
      Jobs/
      Events/
    config/
    tests/
  adapters/
    ready-sdk/
    anthropic/
    gemini/
    bedrock/
    ollama/
examples/
docs/
```

The core package may be implemented first at repository root if that makes the
first release simpler, but no core class may import `Illuminate\*` or a vendor
SDK.

## 4. Core domain objects

Use immutable DTOs/value objects or readonly classes where compatible with the
supported PHP versions.

### `AddressInput`

Represents caller input. It should support both a raw message and already
separated fields:

```php
AddressInput::fromText(string $raw): self;

new AddressInput(
    raw: 'Київ, відділення №285',
    city: null,
    region: null,
    district: null,
    warehouse: null,
    warehouseNumber: null,
    settlementRef: null,
    warehouseRef: null,
);
```

The constructor may use a different shape, but the following information must
be representable: raw text, city, region, district, warehouse text/number,
postal code, and optional known provider references.

### `ParsedAddress`

Contains normalized values extracted by deterministic rules:

- normalized city text;
- normalized region/district text;
- warehouse type (`branch`, `postomat`, `pickup`, or `unknown`);
- warehouse number when present;
- street/address fragment;
- detected provider references;
- parser warnings.

### `Settlement`

Normalized provider-independent representation:

```text
ref, name, normalizedName, region, district, type, aliases, rawPayload?
```

`rawPayload` is optional diagnostics and must be excluded from logs by default.

### `Warehouse`

Normalized representation:

```text
ref, settlementRef, number, name, address, type, isActive, rawPayload?
```

`number` is nullable because some pickup points do not have a conventional
branch number.

### `ResolutionResult`

Must be serializable via `toArray()` and include:

```text
status
settlement|null
warehouse|null
confidence (0.0..1.0)
candidates[]
parsedAddress
diagnostics[]
providerName|null
```

Provide helpers such as `isResolved()`, `needsReview()`, and `toArray()`.

## 5. Core contracts

Keep interfaces small. Exact namespaces can be adjusted, but the responsibilities
must remain separate.

### Location provider

```php
interface LocationProvider
{
    /** @return list<Settlement> */
    public function searchSettlements(SettlementQuery $query): ProviderResult;

    /** @return list<Warehouse> */
    public function searchWarehouses(
        Settlement $settlement,
        WarehouseQuery $query,
    ): ProviderResult;

    public function healthCheck(): ProviderHealth;
}
```

`ProviderResult` should carry normalized records and optional diagnostics. The
provider adapter is responsible for translating a ready SDK or custom HTTP
response into these records.

### Parser

```php
interface AddressParser
{
    public function parse(AddressInput $input): ParsedAddress;
}
```

Ship a deterministic Ukrainian parser first. It should recognize common
abbreviations (`м.`, `місто`, `відд.`, `відділення`, `поштомат`, `пункт`),
number formats, punctuation, and mixed case without relying on an AI call.

### Normalization rules

```php
interface NormalizationRule
{
    public function apply(string $value): string;
}
```

Rules should be composable and configurable. Include built-in rules for Unicode
whitespace, punctuation, case folding, common Ukrainian/Russian abbreviations,
and safe number extraction. Do not silently translate a city into a different
city; aliases must be explicit or provider-supplied.

### Matching strategy

```php
interface MatchingStrategy
{
    public function match(
        AddressQuery $query,
        iterable $candidates,
    ): MatchResult;
}
```

Provide at least:

- `strict`: exact refs, normalized names, and exact warehouse number;
- `balanced`: token similarity plus region/district and type signals;
- `suggest`: returns candidates but never auto-resolves below threshold.

Thresholds must be options, not magic numbers hidden in the algorithm.

## 6. Resolution algorithm

Implement the following order of operations:

1. Validate the input and reject empty/meaningless text.
2. Parse deterministic fields and apply normalization rules.
3. If both provider references are present, validate them through the provider
   or mark the result as invalid; never trust them blindly.
4. Search settlements using the strongest available query: reference, exact
   normalized name, then token/fuzzy query.
5. Apply region and district hints as disambiguation signals.
6. If there is no unique settlement, return `ambiguous` or `not_found`.
7. Search warehouses for the selected settlement.
8. Match exact branch/postomat number when available; otherwise score name and
   address text.
9. Apply the resolution policy:
   - top score above `auto_resolve_threshold` and sufficiently ahead of the
     second candidate -> `resolved`;
   - plausible candidates too close together -> `ambiguous`;
   - no candidates -> `not_found`.
10. If configured, call AI only for unresolved/ambiguous input, then validate
    its suggestions against the already fetched provider candidates and run the
    policy again.
11. Return diagnostics and candidates without changing caller state.

A provider timeout, malformed response, or authentication error becomes
`provider_error` with a typed exception/diagnostic. Do not convert an external
failure into `not_found`.

## 7. AI abstraction

AI is an optional interpreter/ranker, never the source of truth. See
`docs/AI_PROVIDERS.md` for the full design.

At minimum, support these tasks:

- parse free-form text into structured address hints;
- rank known settlement/warehouse candidates.

The AI layer must return structured data, not arbitrary text. Every ref returned
by AI must be checked against the provider candidate set before it can influence
the final result.

## 8. Provider adapters

### Ready SDK adapter

Implement one adapter around a commonly used Nova Poshta SDK as an optional
package. The adapter must only translate SDK requests/responses and must not
leak SDK types into the core.

### Custom HTTP adapter

Provide a small reference adapter based on PSR HTTP interfaces, or document how
to implement one. The core itself must not require an HTTP client.

### Custom application provider

Any user must be able to implement `LocationProvider` with a few methods and
pass it directly to `AddressResolver`. This is a first-class use case, not a
workaround.

## 9. Laravel integration package

The Laravel bridge is optional and must depend on the core package, not the
other way around.

It may provide:

- a service provider and config file;
- `AddressResolverManager` with named drivers;
- bindings for a ready SDK adapter or a custom provider closure;
- Laravel cache integration with configurable TTL;
- queueable address-resolution job;
- `AddressResolved` and `AddressNeedsReview` events;
- Artisan command for manual resolution with `--json` and `--dry-run`;
- a small integration contract/callback for mapping results to host models;
- optional Filament UI in a separate package or optional dependency.

Do not add an `Order` model, database schema, or assumptions about column names
to this package. Host applications decide how to persist `settlement_ref`,
`warehouse_ref`, status, and review data.

Example target configuration:

```php
'provider' => 'custom',
'strategy' => 'balanced',
'ai' => [
    'driver' => 'none',
    'fallback' => [],
],
'thresholds' => [
    'auto_resolve' => 0.90,
    'ambiguity_margin' => 0.08,
],
'cache' => [
    'enabled' => true,
    'ttl' => 86400,
],
```

## 10. Testing plan

### Core tests

Use only fake providers, fake AI, and fixtures. Cover:

- exact city and branch number;
- abbreviations and punctuation;
- postomat and pickup-point types;
- region disambiguation;
- ambiguous city without a warehouse;
- unknown branch number;
- no settlement found;
- provider timeout/malformed response;
- one-based and alternate number formats if relevant;
- custom normalization rules;
- strict/balanced/suggest policies;
- AI suggestion accepted only when it matches provider candidates;
- AI hallucinated reference rejected;
- deterministic no-AI mode;
- stable `toArray()` serialization.

### Adapter contract tests

Every `LocationProvider` adapter must pass the same provider contract suite
against fake SDK responses. No test may call a live Nova Poshta endpoint.

### Laravel tests

Use Orchestra Testbench (or the supported equivalent) for service provider,
config, cache, queue, events, and Artisan behavior. Keep these tests in the
Laravel package so the core remains framework-free.

## 11. Privacy and reliability

- Never log raw customer messages by default.
- Redact names, phone numbers, order identifiers, and postal details before an
  AI request unless an explicit opt-in says otherwise.
- Do not send the full customer record to an AI provider; send only the minimum
  address text and candidate labels required for the task.
- Make timeout, retry, rate limit, cache, and AI budget configurable.
- Include a correlation/request ID in diagnostics without storing raw PII.
- Treat stale provider data as a provider concern; expose freshness metadata and
  never silently claim current availability.

## 12. Documentation/demo requirements

Before the first public release, provide:

- a quick-start README for core-only usage;
- a Laravel integration example;
- a custom provider example;
- an AI adapter example and a no-AI example;
- a small terminal demo using the synthetic fixtures;
- an architecture diagram;
- limitations and failure-mode documentation;
- `CONTRIBUTING.md`, `SECURITY.md`, and a permissive license.

The demo must work offline with fixture data. No credentials or real customer
addresses may be required.

## 13. Milestones

### M0 — package skeleton

- Composer manifests and PSR-4 namespaces;
- coding standards and CI;
- empty interfaces/DTOs only where tests guide the design.

### M1 — pure deterministic core

- parser, normalization rules, provider contract, matching strategies,
  resolution policy, result serialization;
- all core fixture tests passing;
- no Laravel dependency.

### M2 — AI extension point

- structured AI contract;
- fake AI provider;
- one real provider adapter (`packages/openai`, PSR HTTP based);
- fallback and validation tests;
- PII redaction.

### M3 — Laravel bridge

- manager/drivers, config, cache, queue, events, Artisan command;
- Testbench coverage;
- custom host-model mapping example.

### M4 — polish and release

- optional ready SDK adapter;
- docs/demo/GIF;
- static analysis and mutation/property tests where valuable;
- security review and versioned release.

## 14. Acceptance checklist

- [ ] Core installs with PHP only and has no Laravel/vendor SDK dependency.
- [ ] A custom provider can be implemented and injected directly.
- [ ] Deterministic resolution handles every fixture case.
- [ ] Ambiguity is explicit and includes candidates.
- [ ] Provider errors are distinguishable from no matches.
- [ ] AI providers are optional and swappable by driver.
- [ ] AI output is schema-validated and candidate-validated.
- [ ] At least one ready SDK and one custom HTTP/provider example exist.
- [ ] Laravel integration is a separate optional package.
- [ ] No live network calls occur in tests.
- [ ] No private or customer data exists in the repository.
- [ ] README and security guidance are complete.
