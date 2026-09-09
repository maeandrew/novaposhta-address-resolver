<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\AddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\AddressParser;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\MatchingStrategy;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionPolicy;

final class AddressResolverManager extends Manager
{
    private readonly Container $containerInstance;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->containerInstance = $container;
    }

    public function getDefaultDriver(): string
    {
        $driver = $this->containerInstance->make('config')->get(
            'novaposhta-address-resolver.driver',
            'custom',
        );

        if (!is_string($driver) || trim($driver) === '') {
            throw new InvalidArgumentException('The Nova Poshta resolver driver must be a non-empty string.');
        }

        return $driver;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(string $name): array
    {
        $config = $this->containerInstance->make('config')->get(
            'novaposhta-address-resolver.drivers.' . $name,
            [],
        );

        return is_array($config) ? $config : [];
    }

    protected function createCustomDriver(): AddressResolver
    {
        return $this->createConfiguredDriver('custom');
    }

    protected function createDriver($driver): AddressResolver
    {
        if (!is_string($driver)) {
            throw new InvalidArgumentException('The Nova Poshta resolver driver must be a string.');
        }

        if ($driver === 'custom' || $this->hasConfiguredDriver($driver)) {
            return $this->createConfiguredDriver($driver);
        }

        $resolved = parent::createDriver($driver);

        if (!$resolved instanceof AddressResolver) {
            throw new InvalidArgumentException(sprintf(
                'The configured driver [%s] must resolve to %s.',
                $driver,
                AddressResolver::class,
            ));
        }

        return $resolved;
    }

    private function createConfiguredDriver(string $name): AddressResolver
    {
        $config = $this->getConfig($name);
        $provider = $this->resolveService(
            $config['provider'] ?? LocationProvider::class,
            LocationProvider::class,
        );
        $parser = $this->resolveOptionalService($config['parser'] ?? null, AddressParser::class);
        $matchingStrategy = $this->resolveOptionalService(
            $config['matching_strategy'] ?? null,
            MatchingStrategy::class,
        );
        $aiInterpreter = $this->resolveOptionalService(
            $config['ai_interpreter'] ?? null,
            AddressAiInterpreter::class,
        );

        return new AddressResolver(
            $provider,
            $parser,
            $matchingStrategy,
            $this->resolvePolicy($config['policy'] ?? []),
            $aiInterpreter,
        );
    }

    private function hasConfiguredDriver(string $name): bool
    {
        $drivers = $this->containerInstance->make('config')->get(
            'novaposhta-address-resolver.drivers',
            [],
        );

        return is_array($drivers) && array_key_exists($name, $drivers);
    }

    private function resolvePolicy(mixed $value): ?ResolutionPolicy
    {
        if ($value === null || $value === []) {
            return null;
        }

        if ($value instanceof ResolutionPolicy) {
            return $value;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('The resolver policy must be an array or ResolutionPolicy instance.');
        }

        return new ResolutionPolicy(
            autoResolveThreshold: (float) ($value['auto_resolve_threshold'] ?? 0.90),
            ambiguityMargin: (float) ($value['ambiguity_margin'] ?? 0.08),
            minimumCandidateScore: (float) ($value['minimum_candidate_score'] ?? 0.35),
            settlementMinimumScore: (float) ($value['settlement_minimum_score'] ?? 0.65),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $expected
     * @return T
     */
    private function resolveService(mixed $definition, string $expected): object
    {
        if (is_object($definition) && $definition instanceof $expected) {
            return $definition;
        }

        $service = match (true) {
            is_string($definition) => $this->containerInstance->make($definition),
            is_callable($definition) => $definition($this->containerInstance),
            default => throw new InvalidArgumentException(sprintf(
                'The configured service for %s must be a class name, callable, or object.',
                $expected,
            )),
        };

        if (!is_object($service) || !($service instanceof $expected)) {
            throw new InvalidArgumentException(sprintf(
                'The configured service must implement %s.',
                $expected,
            ));
        }

        return $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $expected
     * @return T|null
     */
    private function resolveOptionalService(mixed $definition, string $expected): ?object
    {
        if ($definition === null) {
            return null;
        }

        return $this->resolveService($definition, $expected);
    }
}
