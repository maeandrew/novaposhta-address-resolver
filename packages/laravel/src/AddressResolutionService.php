<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Contracts\ResolutionResultMapper;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Events\AddressNeedsReview;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\Events\AddressResolved;

final class AddressResolutionService
{
    private readonly bool $cacheEnabled;
    private readonly int $cacheTtl;
    private readonly string $cachePrefix;
    /** @var list<string> */
    private readonly array $cacheableStatuses;
    private readonly bool $eventsEnabled;
    private readonly bool $dispatchEventsOnCache;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly AddressResolverManager $manager,
        private readonly Repository $cache,
        private readonly Dispatcher $events,
        array $config = [],
    ) {
        $cacheConfig = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $eventsConfig = is_array($config['events'] ?? null) ? $config['events'] : [];

        $this->cacheEnabled = (bool) ($cacheConfig['enabled'] ?? false);
        $this->cacheTtl = max(1, (int) ($cacheConfig['ttl'] ?? 86400));
        $this->cachePrefix = trim((string) ($cacheConfig['prefix'] ?? 'novaposhta-address-resolver'))
            ?: 'novaposhta-address-resolver';
        $configuredStatuses = $cacheConfig['statuses'] ?? [
            ResolutionStatus::RESOLVED->value,
            ResolutionStatus::AMBIGUOUS->value,
        ];
        $this->cacheableStatuses = is_array($configuredStatuses)
            ? array_values(array_filter($configuredStatuses, static fn(mixed $status): bool => is_string($status)))
            : [ResolutionStatus::RESOLVED->value, ResolutionStatus::AMBIGUOUS->value];
        $this->eventsEnabled = (bool) ($eventsConfig['enabled'] ?? true);
        $this->dispatchEventsOnCache = (bool) ($eventsConfig['dispatch_on_cache'] ?? false);
    }

    public function resolve(AddressInput $input, ?string $driver = null): ResolutionResult
    {
        $driverName = $driver ?? $this->manager->getDefaultDriver();
        $cacheKey = $this->cacheKey($input, $driverName);

        if ($this->cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if ($cached instanceof ResolutionResult) {
                if ($this->dispatchEventsOnCache) {
                    $this->dispatchEvent($input, $cached);
                }

                return $cached;
            }
        }

        $result = $this->manager->driver($driverName)->resolve($input);

        if ($this->cacheEnabled && in_array($result->status->value, $this->cacheableStatuses, true)) {
            $this->cache->put($cacheKey, $result, $this->cacheTtl);
        }

        $this->dispatchEvent($input, $result);

        return $result;
    }

    /**
     * Resolve an address and let the host application persist only an accepted result.
     */
    public function resolveAndMap(
        AddressInput $input,
        ResolutionResultMapper|Closure $mapper,
        ?string $driver = null,
    ): ResolutionResult {
        $result = $this->resolve($input, $driver);

        if ($result->isResolved()) {
            if ($mapper instanceof ResolutionResultMapper) {
                $mapper->map($input, $result);
            } else {
                $mapper($input, $result);
            }
        }

        return $result;
    }

    private function dispatchEvent(AddressInput $input, ResolutionResult $result): void
    {
        if (!$this->eventsEnabled) {
            return;
        }

        if ($result->isResolved()) {
            $this->events->dispatch(new AddressResolved($input, $result));
        } elseif ($result->needsReview()) {
            $this->events->dispatch(new AddressNeedsReview($input, $result));
        }
    }

    private function cacheKey(AddressInput $input, string $driver): string
    {
        return $this->cachePrefix . ':' . hash(
            'sha256',
            json_encode([
                'driver' => $driver,
                'input' => $input->toArray(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
