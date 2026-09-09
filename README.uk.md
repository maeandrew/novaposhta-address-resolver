# Nova Poshta Address Resolver

[English](README.md) · [Українська](README.uk.md)

Автономний PHP-інструмент для перетворення вільно введеної адреси Нової
пошти на перевірені населений пункт і відділення.

Проєкт навмисно розділений на ядро без фреймворків та опційні адаптери.
Застосунок може підключити готовий SDK Нової пошти, власний HTTP-клієнт,
детерміноване зіставлення або один із майбутніх AI-провайдерів.

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
        // Покажіть кандидатів спеціалісту й зберігайте лише після його вибору.
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

Детермінований pipeline працює без AI-ключа. AI-адаптери та fallback-ланцюжки
належать до майбутнього milestone M2. AI може ранжувати лише кандидатів,
отриманих від provider-а, але не може створювати чи зберігати reference
населеного пункту або відділення.

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

У репозиторії є DDEV-конфігурація для офлайн-розробки:

```bash
ddev start
ddev composer install
ddev composer quality
```

Quality-команда запускає PHP CS Fixer, PHPStan і повний PHPUnit suite. CI
виконує ті самі перевірки на PHP 8.2 та 8.3.

## Документація

- [План реалізації українською](docs/uk/IMPLEMENTATION_PLAN.md)
- [Архітектура AI-провайдерів українською](docs/uk/AI_PROVIDERS.md)
- [Архітектурні рішення українською](docs/uk/DECISIONS.md)
- [Синтетичні приклади українською](examples/README.uk.md)
- [Англійський індекс документації](docs/README.md)

## Ліцензія

MIT. Дивіться [LICENSE](LICENSE).
