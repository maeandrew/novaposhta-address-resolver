# Nova Poshta Address Resolver

[English](README.md) · [Українська](README.uk.md)

Standalone PHP tooling for turning a free-form Nova Poshta address into a
validated settlement and warehouse selection.

The project is intentionally split into a framework-free resolver core and
optional adapters. A user may connect a ready Nova Poshta SDK, write a custom
HTTP client, use deterministic matching only, or add one of several AI
providers.

> Unofficial community project. It is not affiliated with Nova Poshta.

## What it solves

Real order messages often contain variants such as:

```text
Київ, відділення номер 285
м. Київ НП 285
Львів поштомат 12345
```

The resolver parses and normalizes the text, queries a location provider,
scores candidates, and returns a result that is safe to persist:

```text
resolved       exact settlement and warehouse selected
ambiguous      more than one plausible candidate; manual choice required
not_found      no candidate matched the requested address
provider_error external data source failed or returned invalid data
invalid_input  the input cannot be meaningfully parsed
```

The resolver never writes to an order or silently chooses a branch when the
evidence is insufficient.

## Installation

The framework-free core is installed with Composer:

```bash
composer require maeandrew/novaposhta-address-resolver
```

The core requires PHP 8.2 or newer and `ext-mbstring`. It has no Laravel,
HTTP-client, Nova Poshta SDK, or AI SDK dependency.

## Core usage

```php
use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;

$resolver = new AddressResolver($locationProvider);

$result = $resolver->resolve(
    AddressInput::fromText('Київ, відділення №285')
);

if ($result->isResolved()) {
    $settlementRef = $result->settlement?->ref;
    $warehouseRef = $result->warehouse?->ref;
} elseif ($result->needsReview()) {
    foreach ($result->candidates as $candidate) {
        // Show candidates to a person and persist only after an explicit choice.
    }
}

$safePayload = $result->toArray();
```

`toArray()` returns the status, normalized records, confidence, candidates,
parsed fields, diagnostics, and provider name. Raw provider payloads are omitted
unless `toArray(includeRawPayload: true)` is explicitly requested.

## Provider extension

The application supplies the source of truth by implementing three small
methods. A provider translates its SDK, HTTP client, or local fixture data into
the core DTOs:

```php
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderHealth;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;

final class MyLocationProvider implements LocationProvider
{
    public function searchSettlements(SettlementQuery $query): ProviderResult
    {
        // Query the chosen source and return list<Settlement>.
        return new ProviderResult([]);
    }

    public function searchWarehouses(
        Settlement $settlement,
        WarehouseQuery $query,
    ): ProviderResult {
        // Query only warehouses belonging to $settlement->ref.
        return new ProviderResult([]);
    }

    public function healthCheck(): ProviderHealth
    {
        return ProviderHealth::healthy('my-provider');
    }
}
```

Provider failures should throw `ProviderException` or another clear exception;
the resolver exposes them as `provider_error` instead of treating an outage as
`not_found`. The provider must return normalized `Settlement` and `Warehouse`
objects. It must not trust a reference supplied by an AI or by an unvalidated
caller.

The built-in parser recognizes Ukrainian forms such as `м.`, `відд.`,
`відділення`, `поштомат`, `пункт`, `№`, and `НП`. Matching is configurable:

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionPolicy;
use MaeAndrew\NovaPoshtaAddressResolver\Matching\StrictMatchingStrategy;

$resolver = new AddressResolver(
    $locationProvider,
    matchingStrategy: new StrictMatchingStrategy(),
    policy: new ResolutionPolicy(
        autoResolveThreshold: 0.90,
        ambiguityMargin: 0.08,
    ),
);
```

`BalancedMatchingStrategy` is the default. `StrictMatchingStrategy` uses exact
references, names, types, numbers, and address fields. `SuggestMatchingStrategy`
returns ranked candidates while keeping the result reviewable.

## AI is optional

The deterministic pipeline works without an AI key. The core AI contracts and a
fake provider are included in the core package. An AI suggestion may only rank
provider candidates; it can never create or persist a settlement or warehouse
reference.

The optional OpenAI adapter uses the Responses API and structured JSON output:

```bash
# after publishing the adapter package
composer require maeandrew/novaposhta-address-resolver-openai
```

During monorepo development, install its local dependencies with
`ddev exec bash scripts/install-openai.sh`. The adapter is kept in a separate
package so the core's Composer install remains small.

It accepts PSR HTTP client and factory implementations from the host
application, so the core and adapter remain independent of a particular HTTP
client. The adapter is wired through `StructuredAddressAiInterpreter`:

```php
use MaeAndrew\NovaPoshtaAddressResolver\AI\StructuredAddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\OpenAI\OpenAiStructuredAiProvider;

$structuredProvider = new OpenAiStructuredAiProvider(
    $httpClient,
    $requestFactory,
    $streamFactory,
    $_ENV['OPENAI_API_KEY'],
);
$resolver = new AddressResolver(
    $locationProvider,
    aiInterpreter: new StructuredAddressAiInterpreter($structuredProvider),
);
```

The adapter is optional and its tests use a fake HTTP client; CI never calls an
AI endpoint.

## Security and limitations

- The core never writes to orders, customers, databases, or caller-owned data.
- No live API calls are made by the test suite or CI; tests use synthetic
  `fixture-*` records.
- Raw input and raw provider payloads are not included in result diagnostics by
  default. Applications should redact personal data before optional AI use.
- Fuzzy matching is a suggestion mechanism. A result stays `ambiguous` when
  the top candidates are too close or the configured threshold is not met.
- Provider data freshness, authentication, retries, rate limits, and caching
  remain responsibilities of the adapter or host application.

## Development

For consistent local development, this repository includes a DDEV configuration
with PHP and Composer:

```bash
ddev start
ddev composer install
ddev composer quality
```

The quality command runs PHP CS Fixer, PHPStan, and the complete PHPUnit suite.
CI runs the same checks on PHP 8.2 and 8.3.

## Documentation

- `AGENTS.md` — handoff rules for implementation agents.
- `docs/IMPLEMENTATION_PLAN.md` — detailed scope, contracts, algorithm,
  milestones, and acceptance criteria.
- `docs/AI_PROVIDERS.md` — provider abstraction, adapters, fallback, and privacy.
- `examples/` — synthetic provider, AI, and address fixtures; no customer data.

## License

Use MIT unless the project owner selects another permissive license before the
first public release.
