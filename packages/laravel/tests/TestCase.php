<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Tests;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\NovaPoshtaAddressResolverServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaPoshtaAddressResolverServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $provider = new InMemoryLocationProvider();

        $app->singleton(LocationProvider::class, static fn() => $provider);
        $app['config']->set('novaposhta-address-resolver.drivers.custom.provider', LocationProvider::class);
        $app['config']->set('novaposhta-address-resolver.drivers.named.provider', LocationProvider::class);
        $app['config']->set('novaposhta-address-resolver.cache.enabled', false);
        $app['config']->set('novaposhta-address-resolver.events.enabled', true);
    }
}
