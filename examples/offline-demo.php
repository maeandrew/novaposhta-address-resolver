<?php

declare(strict_types=1);

use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderHealth;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;

require dirname(__DIR__) . '/vendor/autoload.php';

final class FixtureLocationProvider implements LocationProvider
{
    /**
     * @param list<Settlement> $settlements
     * @param array<string, list<Warehouse>> $warehouses
     */
    public function __construct(
        private readonly array $settlements,
        private readonly array $warehouses,
    ) {
    }

    public function searchSettlements(SettlementQuery $query): ProviderResult
    {
        return new ProviderResult($this->settlements, [], 'offline-fixtures');
    }

    public function searchWarehouses(Settlement $settlement, WarehouseQuery $query): ProviderResult
    {
        return new ProviderResult($this->warehouses[$settlement->ref] ?? [], [], 'offline-fixtures');
    }

    public function healthCheck(): ProviderHealth
    {
        return ProviderHealth::healthy('offline-fixtures');
    }
}

$settlementRecords = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/settlements.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$warehouseRecords = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/warehouses.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

$settlements = array_map(
    static fn (array $record): Settlement => new Settlement(
        ref: (string) $record['ref'],
        name: (string) $record['name'],
        normalizedName: (string) $record['normalized_name'],
        region: $record['region'] !== null ? (string) $record['region'] : null,
        district: $record['district'] !== null ? (string) $record['district'] : null,
        type: (string) $record['type'],
        aliases: array_values(array_map('strval', $record['aliases'])),
    ),
    $settlementRecords,
);
$warehouses = [];

foreach ($warehouseRecords as $settlementRef => $records) {
    $warehouses[(string) $settlementRef] = array_map(
        static fn (array $record): Warehouse => new Warehouse(
            ref: (string) $record['ref'],
            settlementRef: (string) $record['settlement_ref'],
            number: $record['number'],
            name: (string) $record['name'],
            address: (string) $record['address'],
            type: (string) $record['type'],
            isActive: (bool) $record['is_active'],
        ),
        $records,
    );
}

$address = $argv[1] ?? 'Київ 133';
$result = (new AddressResolver(new FixtureLocationProvider($settlements, $warehouses)))
    ->resolve(AddressInput::fromText($address));

echo json_encode(
    $result->toArray(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . PHP_EOL;
