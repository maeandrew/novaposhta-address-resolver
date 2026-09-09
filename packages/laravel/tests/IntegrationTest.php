<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Tests;

use Illuminate\Support\Facades\Event;
use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolverManager;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Events\AddressNeedsReview;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Events\AddressResolved;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Jobs\ResolveAddressJob;
use PHPUnit\Framework\Attributes\Test;

final class IntegrationTest extends TestCase
{
    #[Test]
    public function it_resolves_through_the_manager_and_container_aliases(): void
    {
        $result = $this->app->make(AddressResolutionService::class)->resolve(
            AddressInput::fromText('Київ, відділення №285'),
        );

        self::assertSame(ResolutionStatus::RESOLVED, $result->status);
        self::assertSame('test-kyiv-285', $result->warehouse?->ref);
        self::assertInstanceOf(AddressResolverManager::class, $this->app->make('novaposhta.address_resolver.manager'));
        self::assertInstanceOf(AddressResolver::class, $this->app->make('novaposhta.address_resolver'));
    }

    #[Test]
    public function it_resolves_named_drivers_from_configuration(): void
    {
        $result = $this->app->make(AddressResolutionService::class)->resolve(
            AddressInput::fromText('Київ, відділення №133'),
            'named',
        );

        self::assertSame(ResolutionStatus::RESOLVED, $result->status);
        self::assertSame('test-kyiv-133', $result->warehouse?->ref);
    }

    #[Test]
    public function it_caches_results_without_caching_provider_errors(): void
    {
        $this->app['config']->set('novaposhta-address-resolver.cache.enabled', true);
        $service = $this->app->make(AddressResolutionService::class);
        $input = AddressInput::fromText('Київ, відділення №285');

        $service->resolve($input);
        $service->resolve($input);

        $provider = $this->app->make(LocationProvider::class);
        self::assertSame(1, $provider->healthChecks);
        self::assertSame(1, $provider->settlementSearches);
        self::assertSame(1, $provider->warehouseSearches);
    }

    #[Test]
    public function it_dispatches_resolution_events_only_for_fresh_results(): void
    {
        Event::fake();
        $service = $this->app->make(AddressResolutionService::class);

        $service->resolve(AddressInput::fromText('Київ, відділення №285'));
        Event::assertDispatched(AddressResolved::class);
        Event::assertNotDispatched(AddressNeedsReview::class);

        $reviewResult = $service->resolve(AddressInput::fromText('Київ'));
        self::assertSame(ResolutionStatus::AMBIGUOUS, $reviewResult->status);
        Event::assertDispatched(AddressNeedsReview::class);
    }

    #[Test]
    public function it_runs_the_queue_job_and_only_maps_resolved_results(): void
    {
        $mapped = [];
        $service = $this->app->make(AddressResolutionService::class);
        $resolved = $service->resolveAndMap(
            AddressInput::fromText('Київ, відділення №285'),
            static function (AddressInput $input, $result) use (&$mapped): void {
                $mapped = [$input->raw, $result->warehouse?->ref];
            },
        );

        self::assertSame(ResolutionStatus::RESOLVED, $resolved->status);
        self::assertSame(['Київ, відділення №285', 'test-kyiv-285'], $mapped);

        $ambiguous = $service->resolveAndMap(AddressInput::fromText('Київ'), static function (): void {
            self::fail('An ambiguous result must not be mapped.');
        });
        self::assertSame(ResolutionStatus::AMBIGUOUS, $ambiguous->status);

        $jobResult = (new ResolveAddressJob(AddressInput::fromText('Київ, відділення №133')))
            ->handle($service);
        self::assertSame('test-kyiv-133', $jobResult->warehouse?->ref);
    }
}
