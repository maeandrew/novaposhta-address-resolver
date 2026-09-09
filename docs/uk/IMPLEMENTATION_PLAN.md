# План реалізації

[English](../IMPLEMENTATION_PLAN.md) · [Українська](IMPLEMENTATION_PLAN.md)

Це українська коротка версія повного [плану реалізації](../IMPLEMENTATION_PLAN.md).
Повний англійський документ залишається canonical reference для API та
acceptance criteria.

## Продукт

Проєкт перетворює вільно введену адресу на типізований результат із settlement,
warehouse, confidence, candidates, diagnostics і status. Він підтримує:

1. застосунок із готовим SDK Нової пошти;
2. власний HTTP або database client;
3. детерміноване зіставлення без AI та опційну AI-допомогу.

Це address resolver, а не order management або delivery SDK.

## Межі core

Core не містить моделей замовлень і клієнтів, persistence, migrations, Laravel
service providers, queue/cache, vendor HTTP client, AI SDK, shipment creation,
tracking або payment logic. Усі зовнішні системи проходять через interfaces і
optional adapters.

## Реалізований domain API

- `AddressInput` приймає raw text або окремі city/region/district і warehouse
  hints.
- `ParsedAddress` містить нормалізовані поля та parser warnings.
- `Settlement` і `Warehouse` є immutable normalized DTO без raw payload у
  serialization за замовчуванням.
- `ResolutionResult` має `resolved`, `ambiguous`, `not_found`,
  `provider_error` та `invalid_input`.
- `LocationProvider` повертає `ProviderResult` і `ProviderHealth`.
- `AddressParser`, `NormalizationRule` і `MatchingStrategy` можна замінити.

## Алгоритм

1. Перевірити, що input змістовний.
2. Детерміновано розібрати українські скорочення та числа.
3. Перевірити caller/provider references через provider.
4. Знайти settlement і застосувати region/district signals.
5. Знайти warehouse за reference, type, number та address.
6. Застосувати threshold і ambiguity margin.
7. Повернути candidates і diagnostics без зміни caller state.

Provider timeout, malformed response або authentication failure стає
`provider_error`; це не перетворюється на `not_found`.

## Milestones

- **M0** — Composer package, PSR-4, DDEV, quality tools і CI.
- **M1** — український parser, normalization, provider contract, strict/balanced/
  suggest strategies, policy та fixture tests.
- **M2** — structured AI contract, fake provider, fallback, redaction і
  optional OpenAI adapter через PSR HTTP. AI не може винайти reference.
  Залежність залишається optional, але для production-вільного тексту AI є
  рекомендованим шаром якості.
- **M3** — окремий optional Laravel package з manager, config, cache, queue,
  events, Artisan command і host-model mapping example. Laravel package не
  просочується в core.

## Тестування та приватність

Усі тести offline і використовують лише synthetic `fixture-*` records. Raw
customer message не логуються за замовчуванням. AI отримує мінімальний
redacted address text і компактний список відомих provider candidates.
