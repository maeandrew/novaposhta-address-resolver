# Архітектура AI-провайдерів

[English](../AI_PROVIDERS.md) · [Українська](AI_PROVIDERS.md)

## Принцип

Для production-ввідних даних AI є рекомендованим шаром якості. Він краще
обробляє одруки, змішані українську та російську, пропущені назви полів і
нестандартні повідомлення. Детермінований pipeline залишається fallback для
збоїв provider-а, обмежень приватності, offline-обробки й тестів, тому
framework-free core технічно працює без AI SDK або API key. Provider Нової
пошти залишається єдиним джерелом settlement і warehouse references.

## Два контракти

Structured generation і address interpretation мають бути розділені:

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

`AddressAiInterpreter` будує task-specific schema поверх vendor-neutral
`StructuredAiProvider`. Core не імпортує OpenAI, Anthropic, Gemini або інший
SDK.

## Structured output

У репозиторії є опційний адаптер OpenAI Responses API. Після окремої публікації
його можна встановити з Packagist:

```bash
composer require maeandrew/novaposhta-address-resolver-openai
```

Під час розробки monorepo запустіть `ddev exec bash scripts/install-openai.sh`.

`OpenAiStructuredAiProvider` залежить лише від PSR HTTP interfaces. Host-
застосунок передає HTTP client, request factory і stream factory, після чого
provider підключається до `StructuredAddressAiInterpreter`.

Parser AI повертає лише hints і ніколи не отримує право створювати IDs:

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

Ranker може посилатися тільки на IDs, які resolver передав у prompt:

```json
{
  "ranked_candidates": [
    {"candidate_id": "fixture-kyiv-285", "score": 0.98}
  ],
  "reason": "Точний населений пункт і номер відділення"
}
```

Невідомий candidate ID відхиляється. Навіть відомий ID проходить власну
resolution policy і provider validation.

## Fallback і помилки

Fallback chain підключається лише конфігурацією, наприклад:

```text
OpenAI -> Anthropic -> Ollama -> deterministic-only
```

Timeout, rate limit або invalid structured output переходять до наступного
provider-а. Якщо всі спроби завершилися помилкою, повертається deterministic
result із diagnostic; помилка AI не маскується під `not_found`.

## Приватність і вартість

- Core не має AI dependency і не вмикає AI без конфігурації host-застосунку;
  для production слід явно налаштувати цей шар якості та його budget.
- Timeout, retries, model, output tokens і budget мають бути налаштованими.
- Перед запитом треба redacted phone numbers, emails, order IDs та сторонні
  fragments.
- У prompt передається компактний список кандидатів без повного provider
  payload.
- Host application може заборонити AI для конкретного input через policy
  callback.

## Тестування

Тести використовують fake structured provider і перевіряють malformed JSON,
unknown IDs, low confidence, timeout, fallback order та deterministic no-AI
режим. Live provider tests не запускаються у звичайному CI.
