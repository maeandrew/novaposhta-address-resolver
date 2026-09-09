# Laravel-інтеграція

[English](../LARAVEL.md) · [Українська](LARAVEL.md)

Laravel bridge є опційним package:

```bash
composer require maeandrew/novaposhta-address-resolver-laravel
```

Він підтримує Laravel 11, 12 і 13 на PHP 8.2+. Bridge залишається окремим від core
без фреймворків і не передбачає order model, таблицю або назви колонок.

## Binding provider-а

Зареєструйте клієнт даних Нової пошти за core-контрактом:

```php
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;

$this->app->bind(LocationProvider::class, fn ($app) => new MyLocationProvider(
    $app->make(MyNovaPoshtaClient::class),
));
```

Потім використовуйте bridge service:

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

$result = app(AddressResolutionService::class)->resolve(
    AddressInput::fromText($message),
);
```

Опублікуйте конфігурацію, якщо потрібні власні thresholds, parser, AI
interpreter, cache або queue settings:

```bash
php artisan vendor:publish --tag=novaposhta-address-resolver-config
```

Cache вимкнений за замовчуванням. За замовчуванням зберігаються лише `resolved`
та `ambiguous`; `not_found` і `invalid_input` вважаються тимчасовими. Список
можна змінити через `cache.statuses`, якщо host має власну політику актуальності.
Свіжі `resolved` та `ambiguous` results
створюють `AddressResolved` і `AddressNeedsReview`; cache hit не створює
дубльованих events.

## Queue та command

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Jobs\ResolveAddressJob;

ResolveAddressJob::dispatch(AddressInput::fromText($message));
```

```bash
php artisan novaposhta:resolve "Київ, відділення №285" --json --dry-run
```

Command і job лише розв’язують адреси. Persistence залишається явною дією
host-застосунку:

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

$result = app(AddressResolutionService::class)->resolveAndMap(
    AddressInput::fromText($message),
    function (AddressInput $input, ResolutionResult $result) use ($order): void {
        $order->forceFill([
            'settlement_ref' => $result->settlement?->ref,
            'warehouse_ref' => $result->warehouse?->ref,
        ])->save();
    },
);
```

Mapper працює лише для `resolved`. Host-застосунок сам визначає, як зберігати
кандидатів для перевірки та diagnostics.
