<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Tests;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderHealth;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;

final class InMemoryLocationProvider implements LocationProvider
{
    public int $healthChecks = 0;
    public int $settlementSearches = 0;
    public int $warehouseSearches = 0;

    /**
     * @var list<Settlement>
     */
    private array $settlements;

    /**
     * @var array<string, list<Warehouse>>
     */
    private array $warehouses;

    public function __construct()
    {
        $this->settlements = [new Settlement(
            ref: 'test-kyiv',
            name: 'Київ',
            normalizedName: 'київ',
            region: 'Київська',
            district: 'Київський',
            aliases: ['Киев'],
        )];
        $this->warehouses = [
            'test-kyiv' => [
                new Warehouse(
                    ref: 'test-kyiv-285',
                    settlementRef: 'test-kyiv',
                    number: 285,
                    name: 'Відділення №285',
                    address: 'вулиця Тестова, 1',
                    type: 'branch',
                ),
                new Warehouse(
                    ref: 'test-kyiv-133',
                    settlementRef: 'test-kyiv',
                    number: 133,
                    name: 'Відділення №133',
                    address: 'вулиця Прикладна, 13',
                    type: 'branch',
                ),
            ],
        ];
    }

    public function searchSettlements(SettlementQuery $query): ProviderResult
    {
        $this->settlementSearches++;

        return new ProviderResult($this->settlements, [], 'laravel-test-provider');
    }

    public function searchWarehouses(Settlement $settlement, WarehouseQuery $query): ProviderResult
    {
        $this->warehouseSearches++;

        return new ProviderResult(
            $this->warehouses[$settlement->ref] ?? [],
            [],
            'laravel-test-provider',
        );
    }

    public function healthCheck(): ProviderHealth
    {
        $this->healthChecks++;

        return ProviderHealth::healthy('laravel-test-provider');
    }
}
