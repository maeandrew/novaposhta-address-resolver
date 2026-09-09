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
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Replace this class with an adapter for the application's SDK, HTTP client,
 * or local read model. The resolver only depends on this small contract.
 */
final class ExampleLocationProvider implements LocationProvider
{
    /** @var list<Settlement> */
    private array $settlements;

    /** @var array<string, list<Warehouse>> */
    private array $warehouses;

    public function __construct()
    {
        $this->settlements = [
            new Settlement(
                ref: 'example-settlement',
                name: 'Прикладне',
                normalizedName: 'прикладне',
                region: 'Демонстраційна',
                type: 'town',
            ),
        ];
        $this->warehouses = [
            'example-settlement' => [
                new Warehouse(
                    ref: 'example-warehouse-1',
                    settlementRef: 'example-settlement',
                    number: 1,
                    name: 'Відділення №1',
                    address: 'вулиця Прикладна, 1',
                    type: WarehouseType::BRANCH,
                ),
            ],
        ];
    }

    public function searchSettlements(SettlementQuery $query): ProviderResult
    {
        $normalizer = TextNormalizer::default();
        $needle = $query->normalizedName ?? $query->name;
        $records = array_values(array_filter(
            $this->settlements,
            static function (Settlement $settlement) use ($normalizer, $needle, $query): bool {
                if ($query->reference !== null) {
                    return $settlement->ref === $query->reference;
                }

                if ($needle !== null && $normalizer->normalize($needle) !== $settlement->normalizedName) {
                    return false;
                }

                return ($query->region === null
                    || $normalizer->normalize($query->region) === $normalizer->normalize((string) $settlement->region))
                    && ($query->district === null
                        || $normalizer->normalize($query->district)
                            === $normalizer->normalize((string) $settlement->district));
            },
        ));

        return new ProviderResult($records, providerName: 'custom-example');
    }

    public function searchWarehouses(Settlement $settlement, WarehouseQuery $query): ProviderResult
    {
        $records = $this->warehouses[$settlement->ref] ?? [];
        $records = array_values(array_filter(
            $records,
            static function (Warehouse $warehouse) use ($query): bool {
                if ($query->reference !== null && $warehouse->ref !== $query->reference) {
                    return false;
                }

                if ($query->number !== null && $warehouse->number !== $query->number) {
                    return false;
                }

                return $query->type === null
                    || $query->type === WarehouseType::UNKNOWN->value
                    || $warehouse->type->value === $query->type;
            },
        ));

        return new ProviderResult($records, providerName: 'custom-example');
    }

    public function healthCheck(): ProviderHealth
    {
        return ProviderHealth::healthy('custom-example');
    }
}

$address = $argv[1] ?? 'Прикладне, відділення 1';
$result = (new AddressResolver(new ExampleLocationProvider()))
    ->resolve(AddressInput::fromText($address));

echo json_encode(
    $result->toArray(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . PHP_EOL;
