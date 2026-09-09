# AI provider architecture

[English](AI_PROVIDERS.md) · [Українська](uk/AI_PROVIDERS.md)

## Principle

AI is the recommended quality layer for production free-form address input. It
helps with typos, mixed Ukrainian/Russian text, missing labels, and irregular
messages. The resolver must still have a deterministic fallback for provider
outages, privacy restrictions, offline processing, and tests, so the framework-
free core remains usable without an AI SDK or API key. The Nova Poshta provider
remains the source of truth for all settlement and warehouse references.

## Two interfaces, two responsibilities

Keep vendor-neutral structured generation separate from address interpretation.

```php
interface StructuredAiProvider
{
    public function generate(StructuredPrompt $prompt): StructuredAiResponse;
}

interface AddressAiInterpreter
{
    public function parse(AddressInput $input): AiAddressHints;

    /** @param list<Candidate> $candidates */
    public function rank(AddressInput $input, array $candidates): AiRanking;
}
```

`AddressAiInterpreter` builds the task-specific schema and uses a
`StructuredAiProvider`. This keeps the core domain independent from provider
SDKs while allowing the same provider abstraction to be reused by more than one
address task.

## Optional adapters

Each adapter is installed only when needed:

```text
maeandrew/novaposhta-address-resolver-openai
novaposhta-resolver-anthropic
novaposhta-resolver-gemini
novaposhta-resolver-bedrock
novaposhta-resolver-ollama
```

The repository includes an optional OpenAI Responses API adapter. Install it
from Packagist after its separate package publication with:

```bash
composer require maeandrew/novaposhta-address-resolver-openai
```

For monorepo development, run `ddev exec bash scripts/install-openai.sh`.

`OpenAiStructuredAiProvider` depends only on PSR HTTP interfaces. The host
application supplies the HTTP client, request factory, and stream factory, then
passes the provider to `StructuredAddressAiInterpreter`.

An adapter may use the vendor's official PHP SDK or a PSR-18 HTTP client. No
adapter type may appear in a core DTO or interface.

The Laravel bridge may expose a driver manager:

```php
$ai = $manager->driver('openai');
$ai = $manager->driver('anthropic');
$ai = $manager->driver('custom');
```

Users must also be able to register a custom driver:

```php
$manager->extend('my-provider', fn ($app) => new MyStructuredAiProvider(...));
```

## Structured output contract

The address parser schema should contain only hints, never trusted IDs:

```json
{
  "city": "Київ",
  "region": null,
  "district": null,
  "warehouse_type": "branch",
  "warehouse_number": 285,
  "warehouse_text": "відділення 285",
  "confidence": 0.92,
  "uncertainties": []
}
```

The candidate-ranking schema may refer only to IDs supplied in the prompt:

```json
{
  "ranked_candidates": [
    {"candidate_id": "fixture-kyiv-285", "score": 0.98}
  ],
  "reason": "Exact city and branch number"
}
```

The resolver must reject candidate IDs that were not in the supplied set.
Scores are hints and must still pass the resolver's own thresholds.

## Fallback and failure policy

Use a chain only when configured:

```text
OpenAI -> Anthropic -> Ollama -> deterministic-only
```

Rules:

- a timeout or rate limit may move to the next configured provider;
- invalid structured output is a provider failure, not a successful answer;
- if all providers fail, return the deterministic result and diagnostics;
- never hide an AI failure as `not_found`;
- never retry indefinitely;
- expose provider name, attempt count, and latency in diagnostics without raw
  prompt content.

## Cost and privacy controls

- The core has no AI dependency and keeps AI disabled unless the host configures
  an interpreter; production applications should explicitly configure this
  quality layer and its budget.
- Add configurable timeout, retry count, model, maximum output tokens, and
  request budget.
- Cache only normalized, redacted requests; make caching opt-in for sensitive
  installations.
- Redact phone numbers, names, order numbers, and unrelated address fragments
  before a request.
- Do not include the full provider payload; send a compact candidate list.
- Allow a host application to provide a consent/policy callback that rejects AI
  use for a particular input.

## Testing

- `FakeStructuredAiProvider` returns fixture JSON and records calls.
- Contract tests verify every adapter produces the same normalized response.
- Tests cover malformed JSON, unknown candidate IDs, low confidence, timeout,
  fallback order, and deterministic operation with no AI configured.
- Real provider tests are opt-in and never run in default CI.
