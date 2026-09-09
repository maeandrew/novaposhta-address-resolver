<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Console\ResolveAddressCommand;

final class NovaPoshtaAddressResolverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/novaposhta-address-resolver.php',
            'novaposhta-address-resolver',
        );

        $this->app->singleton(AddressResolverManager::class, fn($app): AddressResolverManager => new AddressResolverManager($app));
        $this->app->alias(AddressResolverManager::class, 'novaposhta.address_resolver.manager');

        $this->app->singleton(AddressResolver::class, function ($app): AddressResolver {
            return $app->make(AddressResolverManager::class)->driver();
        });
        $this->app->alias(AddressResolver::class, 'novaposhta.address_resolver');

        $this->app->singleton(AddressResolutionService::class, function ($app): AddressResolutionService {
            $config = $app->make('config');
            $cacheConfig = $config->get('novaposhta-address-resolver.cache', []);
            $store = is_array($cacheConfig) ? ($cacheConfig['store'] ?? null) : null;

            return new AddressResolutionService(
                $app->make(AddressResolverManager::class),
                $app->make(CacheManager::class)->store(is_string($store) ? $store : null),
                $app->make(Dispatcher::class),
                [
                    'cache' => $cacheConfig,
                    'events' => $config->get('novaposhta-address-resolver.events', []),
                ],
            );
        });
        $this->app->alias(AddressResolutionService::class, 'novaposhta.address_resolver.service');
    }

    public function boot(): void
    {
        $configPath = __DIR__ . '/../config/novaposhta-address-resolver.php';

        $this->publishes([
            $configPath => config_path('novaposhta-address-resolver.php'),
        ], 'novaposhta-address-resolver-config');

        if ($this->app->runningInConsole()) {
            $this->commands([ResolveAddressCommand::class]);
        }
    }
}
