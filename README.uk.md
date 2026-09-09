# Nova Poshta Address Resolver

[English](README.md) · [Українська](README.uk.md)

Автономний PHP-інструмент для перетворення вільно введеної адреси Нової
пошти на перевірені населений пункт і відділення.

Проєкт навмисно розділений на ядро без фреймворків та опційні адаптери.
Застосунок може підключити готовий SDK Нової пошти, власний HTTP-клієнт,
детерміноване зіставлення або один із доступних AI-провайдерів.

> Неофіційний community-проєкт. Він не пов’язаний із компанією «Нова пошта».

## Що вирішує проєкт

Реальні повідомлення про замовлення часто мають різні форми:

```text
Київ, відділення номер 285
м. Київ НП 285
Львів поштомат 12345
```

Resolver нормалізує та розбирає текст, запитує provider локацій, оцінює
кандидатів і повертає результат, безпечний для подальшого збереження:

```text
resolved       точно вибрані населений пункт і відділення
ambiguous      є кілька правдоподібних кандидатів; потрібна перевірка
not_found      відповідний кандидат не знайдений
provider_error зовнішній provider повернув помилку або некоректні дані
invalid_input  текст неможливо змістовно розібрати
```

Ядро не змінює замовлення й не обирає відділення, коли доказів недостатньо.

## Встановлення

```bash
composer require maeandrew/novaposhta-address-resolver
```

Ядро потребує PHP 8.2 або новішого та розширення `ext-mbstring`. Воно не має
залежностей від Laravel, HTTP-клієнта, SDK Нової пошти чи AI SDK.

## Використання ядра

```php
use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;

// $locationProvider — будь-яка реалізація LocationProvider у вашому застосунку.
$resolver = new AddressResolver($locationProvider);

$result = $resolver->resolve(
    AddressInput::fromText('Київ, відділення №285')
);

if ($result->isResolved()) {
    $settlementRef = $result->settlement?->ref;
    $warehouseRef = $result->warehouse?->ref;
} elseif ($result->needsReview()) {
    foreach ($result->candidates as $candidate) {
        // Покажіть фахівцеві запропоновані варіанти й збережіть лише після підтвердження.
    }
}

$safePayload = $result->toArray();
```

`toArray()` повертає статус, нормалізовані записи, confidence, кандидатів,
розібрані поля, diagnostics і назву provider. Сирі provider payload-и не
включаються, доки явно не передати `toArray(includeRawPayload: true)`.

## Розширення provider-ом

Застосунок передає ядру джерело істини через три невеликі методи. Provider
перекладає відповіді свого SDK, HTTP-клієнта або локальної бази в DTO ядра:

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
        // Запитайте обране джерело й поверніть list<Settlement>.
        return new ProviderResult([]);
    }

    public function searchWarehouses(
        Settlement $settlement,
        WarehouseQuery $query,
    ): ProviderResult {
        // Запитуйте лише відділення, що належать $settlement->ref.
        return new ProviderResult([]);
    }

    public function healthCheck(): ProviderHealth
    {
        return ProviderHealth::healthy('my-provider');
    }
}
```

Помилки provider-а мають викидати `ProviderException` або інший зрозумілий
exception. Resolver поверне `provider_error`, а не замаскує збій під
`not_found`. Provider повинен повертати нормалізовані `Settlement` і
`Warehouse`; він не має довіряти reference, який надійшов від AI або не був
перевірений у власному джерелі даних.

Вбудований parser розпізнає українські форми `м.`, `відд.`, `відділення`,
`поштомат`, `пункт`, `№` і `НП`. Matching можна налаштувати:

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

За замовчуванням використовується `BalancedMatchingStrategy`.
`StrictMatchingStrategy` працює з точними reference, назвами, типами,
номерами й адресами. `SuggestMatchingStrategy` повертає рейтинг кандидатів,
залишаючи остаточне рішення для перевірки.

## AI — опційний

Детермінований pipeline працює без AI-ключа. Контракти AI та fake provider уже
є в ядрі. AI може ранжувати лише кандидатів, отриманих від provider-а, але не
може створювати чи зберігати reference населеного пункту або відділення.

Опційний OpenAI adapter використовує Responses API та структурований JSON:

```bash
# після публікації окремого adapter package
composer require maeandrew/novaposhta-address-resolver-openai
```

Під час розробки monorepo встановіть його локальні залежності командою
`ddev exec bash scripts/install-openai.sh`. Адаптер винесений в
окремий package, щоб Composer-встановлення ядра залишалося компактним.

Адаптер приймає PSR HTTP client і factory від host-застосунку, тому ядро та
адаптер не залежать від конкретного HTTP-клієнта:

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

Адаптер опційний, а його тести використовують fake HTTP client; CI не робить
запитів до AI endpoint.

## Опційний Laravel bridge

Laravel-інтеграція є окремим package і не додає framework-класи до core:

```bash
# після публікації bridge package
composer require maeandrew/novaposhta-address-resolver-laravel
```

Вона надає bindings для container, налаштований cache, resolution events,
queue job і `novaposhta:resolve`. Host-застосунок реєструє власний
`LocationProvider` і сам визначає, як зберігати знайдені references. Дивіться
[гайд Laravel-інтеграції](docs/LARAVEL.md).

Під час розробки monorepo встановлюйте bridge разом із локальним core командою
`ddev exec bash scripts/install-laravel.sh`.

## Безпека та обмеження

- Ядро не записує дані в замовлення, клієнтів, бази даних або об’єкти caller-а.
- Тести й CI не роблять live API-запитів; використовуються синтетичні записи
  `fixture-*`.
- Raw input і raw provider payload-и за замовчуванням не потрапляють у
  diagnostics. Перед опційним AI-викликом застосунок має вилучати персональні
  дані.
- Fuzzy matching лише пропонує кандидатів. Результат залишається `ambiguous`,
  якщо кандидати надто близькі або не досягнуто налаштованого порогу.
- Актуальність даних provider-а, автентифікація, retry, rate limits і cache
  належать до відповідальності адаптера або host-застосунку.

## Розробка

Для узгодженої локальної розробки в репозиторії є DDEV-конфігурація з PHP та
Composer:

```bash
ddev start
ddev composer install
ddev composer quality
```

Quality-команда запускає PHP CS Fixer, PHPStan і повний PHPUnit suite. CI
виконує ті самі перевірки на PHP 8.2 та 8.3.

Опційний Laravel bridge має окреме локальне встановлення залежностей і quality
suite:

```bash
ddev exec bash scripts/install-laravel.sh
ddev composer quality --working-dir=packages/laravel
```

## Документація

- [План реалізації українською](docs/uk/IMPLEMENTATION_PLAN.md)
- [Архітектура AI-провайдерів українською](docs/uk/AI_PROVIDERS.md)
- [Архітектурні рішення українською](docs/uk/DECISIONS.md)
- [Синтетичні приклади українською](examples/README.uk.md)
- [Англійський індекс документації](docs/README.md)
- [Український індекс документації](docs/README.uk.md)
- [Laravel integration guide](docs/LARAVEL.md)

## Ліцензія

MIT. Дивіться [LICENSE](LICENSE).
