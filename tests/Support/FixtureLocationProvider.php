<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Support;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderHealth;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;
use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;
use RuntimeException;

final class FixtureLocationProvider implements LocationProvider
{
    /**
     * @param list<Settlement> $settlements
     * @param array<string, list<Warehouse>> $warehouses
     */
    public function __construct(
        private readonly array $settlements,
        private readonly array $warehouses,
        private readonly ?string $failure = null,
    ) {}

    public function searchSettlements(SettlementQuery $query): ProviderResult
    {
        $this->failIfConfigured('settlements');

        $normalizer = TextNormalizer::default();
        $records = array_values(array_filter(
            $this->settlements,
            static function (Settlement $settlement) use ($query, $normalizer): bool {
                if ($query->reference !== null) {
                    return $settlement->ref === $query->reference;
                }

                if ($query->name === null && $query->normalizedName === null) {
                    return true;
                }

                $needle = $normalizer->normalize($query->normalizedName ?? $query->name ?? '');
                $names = [$settlement->normalizedName, $normalizer->normalize($settlement->name)];

                foreach ($settlement->aliases as $alias) {
                    $names[] = $normalizer->normalize($alias);
                }

                $nameMatches = in_array($needle, $names, true)
                    || ($query->fuzzy && array_any($names, static fn(string $name): bool => str_contains($name, $needle)));

                if (!$nameMatches) {
                    return false;
                }

                return ($query->region === null || $normalizer->normalize((string) $settlement->region) === $query->region)
                    && ($query->district === null || $normalizer->normalize((string) $settlement->district) === $query->district);
            },
        ));

        return new ProviderResult($records, [], 'fixture');
    }

    public function searchWarehouses(Settlement $settlement, WarehouseQuery $query): ProviderResult
    {
        $this->failIfConfigured('warehouses');
        $records = $this->warehouses[$settlement->ref] ?? [];

        if ($query->reference !== null) {
            $records = array_values(array_filter(
                $records,
                static fn(Warehouse $warehouse): bool => $warehouse->ref === $query->reference,
            ));
        } elseif ($query->number !== null) {
            $records = array_values(array_filter(
                $records,
                static fn(Warehouse $warehouse): bool => $warehouse->number === $query->number
                    && ($query->type === null || $query->type === 'unknown' || $warehouse->type->value === $query->type),
            ));
        } elseif ($query->type !== null && $query->type !== 'unknown') {
            $records = array_values(array_filter(
                $records,
                static fn(Warehouse $warehouse): bool => $warehouse->type->value === $query->type,
            ));
        }

        return new ProviderResult($records, [], 'fixture');
    }

    public function healthCheck(): ProviderHealth
    {
        $this->failIfConfigured('health');

        return ProviderHealth::healthy('fixture');
    }

    private function failIfConfigured(string $operation): void
    {
        if ($this->failure === $operation || $this->failure === 'all') {
            throw new RuntimeException('Synthetic provider failure.');
        }
    }
}

/**
 * PHP 8.2-compatible equivalent of the PHP 8.4 array_any helper used only in tests.
 *
 * @param list<string> $values
 */
function array_any(array $values, callable $callback): bool
{
    foreach ($values as $value) {
        if ($callback($value)) {
            return true;
        }
    }

    return false;
}
