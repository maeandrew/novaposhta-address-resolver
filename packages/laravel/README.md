# Nova Poshta Address Resolver — Laravel bridge

[English](README.md) · [Українська](README.uk.md)

This package is an optional Laravel integration. The framework-free resolver
core remains a separate dependency and does not import Laravel classes.

## Install

```bash
composer require maeandrew/novaposhta-address-resolver-laravel
```

The package supports Laravel 11, 12, and 13 on PHP 8.2+. Laravel package
discovery registers the service provider automatically.

When developing this monorepo, run `ddev exec bash scripts/install-laravel.sh`
to install the bridge against the local core package.

## Configure a provider

Bind a provider in the host application's service provider:

```php
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;

$this->app->bind(LocationProvider::class, fn ($app) => new MyLocationProvider(
    // host-owned SDK or HTTP client
));
```

Publish the optional configuration:

```bash
php artisan vendor:publish --tag=novaposhta-address-resolver-config
```

The default `custom` driver resolves `LocationProvider::class` from the
container. Applications can configure parser, matching strategy, policy, and
AI interpreter class names in the published file.

## Use the service

```php
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

$result = app(AddressResolutionService::class)->resolve(
    AddressInput::fromText($message),
);
```

Caching is disabled by default. Enable it only after choosing an appropriate
store and TTL for the host application. Cache keys contain a hash of the input;
raw messages are not used as cache keys. By default only `resolved` and
`ambiguous` results are cached. Transient `not_found` and `invalid_input`
results are resolved again on the next request; the `cache.statuses` option can
be changed when the host has a different freshness policy.

`AddressResolved` and `AddressNeedsReview` events are dispatched for fresh
resolutions when events are enabled. Cached results do not dispatch duplicate
events.

## Queue and Artisan

```php
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Jobs\ResolveAddressJob;

ResolveAddressJob::dispatch(AddressInput::fromText($message));
```

Resolve an address without persisting a host model:

```bash
php artisan novaposhta:resolve "Київ, відділення №285" --json --dry-run
```

The bridge never assumes an `Order` model or database columns. To persist a
resolved result, use an explicit host-owned mapper and keep the review state
when the result is ambiguous:

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

The callback runs only for a `resolved` result. The host application decides
how to store ambiguous candidates and diagnostics.
