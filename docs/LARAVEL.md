# Laravel integration

[English](LARAVEL.md) · [Українська](uk/LARAVEL.md)

The Laravel bridge is an optional package:

```bash
# after publishing the bridge as a separate package
composer require maeandrew/novaposhta-address-resolver-laravel
```

It targets Laravel 13 and PHP 8.3+. The bridge remains separate from the
framework-free core and does not assume an order model, database table, or
column name.

## Provider binding

Register the application's Nova Poshta data client behind the core contract:

```php
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;

$this->app->bind(LocationProvider::class, fn ($app) => new MyLocationProvider(
    $app->make(MyNovaPoshtaClient::class),
));
```

Then resolve through the bridge service:

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

$result = app(AddressResolutionService::class)->resolve(
    AddressInput::fromText($message),
);
```

Publish configuration when the application needs custom thresholds, a parser,
an AI interpreter, cache, or queue settings:

```bash
php artisan vendor:publish --tag=novaposhta-address-resolver-config
```

Caching is disabled by default. Fresh `resolved` and `ambiguous` results emit
`AddressResolved` and `AddressNeedsReview`; cache hits do not emit duplicates.

## Queue and command

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Jobs\ResolveAddressJob;

ResolveAddressJob::dispatch(AddressInput::fromText($message));
```

```bash
php artisan novaposhta:resolve "Київ, відділення №285" --json --dry-run
```

The command and job resolve addresses only. Persistence remains explicit in the
host application:

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

The mapper runs only for `resolved`. The host application decides how to store
review candidates and diagnostics.
