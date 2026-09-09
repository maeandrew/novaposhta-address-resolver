# Nova Poshta Address Resolver — Laravel bridge

[English](README.md) · [Українська](README.uk.md)

Це опційна Laravel-інтеграція. Core без фреймворків залишається окремою
залежністю і не імпортує Laravel-класи.

## Встановлення

```bash
composer require maeandrew/novaposhta-address-resolver-laravel
```

Пакет розрахований на Laravel 13 і PHP 8.3+. Laravel package discovery
зареєструє service provider автоматично.

Під час розробки цього monorepo запустіть `ddev exec bash scripts/install-laravel.sh`,
щоб встановити bridge разом із локальним core package.

## Налаштування provider-а

Зареєструйте provider у service provider host-застосунку:

```php
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;

$this->app->bind(LocationProvider::class, fn ($app) => new MyLocationProvider(
    // SDK або HTTP-клієнт host-застосунку
));
```

Опційну конфігурацію можна опублікувати:

```bash
php artisan vendor:publish --tag=novaposhta-address-resolver-config
```

Драйвер `custom` за замовчуванням знаходить `LocationProvider::class` у
container. У конфігурації можна вказати parser, matching strategy, policy та AI
interpreter.

## Використання сервісу

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

$result = app(AddressResolutionService::class)->resolve(
    AddressInput::fromText($message),
);
```

Cache вимкнений за замовчуванням. Увімкніть його лише після вибору відповідного
store та TTL. У cache key використовується hash input; сирий текст не є самим
ключем.

Для свіжих resolution можна отримувати `AddressResolved` і
`AddressNeedsReview`; cache hit не створює дубльованих events.

## Queue та Artisan

```php
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Jobs\ResolveAddressJob;

ResolveAddressJob::dispatch(AddressInput::fromText($message));
```

Розв’язати адресу без запису host-моделі:

```bash
php artisan novaposhta:resolve "Київ, відділення №285" --json --dry-run
```

Bridge не передбачає `Order` model або назви колонок. Для збереження результату
використовуйте явний callback host-застосунку і залишайте ambiguous result на
ручну перевірку:

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;

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

Callback виконується лише для `resolved`. Host-застосунок сам визначає, як
зберігати ambiguous candidates і diagnostics.
