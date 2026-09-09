<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Matching;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;

final class StrictMatchingStrategy extends BalancedMatchingStrategy
{
    protected function scoreSettlement(AddressQuery $query, Settlement $settlement): array
    {
        if ($query->settlementRef !== null) {
            $score = $query->settlementRef === $settlement->ref ? 1.0 : 0.0;

            return [$score, ['reference' => $score]];
        }

        $matchesName = $this->settlementNameScore($query->city, $settlement) === 1.0;
        $matchesRegion = $query->region === null
            || $this->similarity->score($query->region, $settlement->region) === 1.0;
        $matchesDistrict = $query->district === null
            || $this->similarity->score($query->district, $settlement->district) === 1.0;
        $score = $matchesName && $matchesRegion && $matchesDistrict ? 1.0 : 0.0;

        return [$score, [
            'name' => $matchesName ? 1.0 : 0.0,
            'region' => $matchesRegion ? 1.0 : 0.0,
            'district' => $matchesDistrict ? 1.0 : 0.0,
        ]];
    }

    protected function scoreWarehouse(AddressQuery $query, Warehouse $warehouse): array
    {
        if ($query->warehouseRef !== null) {
            $score = $query->warehouseRef === $warehouse->ref ? 1.0 : 0.0;

            return [$score, ['reference' => $score]];
        }

        if (!$query->hasWarehouseHint()) {
            return [0.0, ['type' => 0.0, 'number' => 0.0, 'address' => 0.0]];
        }

        $typeMatches = $query->warehouseType === WarehouseType::UNKNOWN
            || $warehouse->type === $query->warehouseType;
        $numberMatches = $query->warehouseNumber === null || $query->warehouseNumber === $warehouse->number;
        $addressMatches = $query->streetAddress === null
            || $this->similarity->score($query->streetAddress, $warehouse->address) === 1.0;

        $score = $typeMatches && $numberMatches && $addressMatches ? 1.0 : 0.0;

        return [$score, [
            'type' => $typeMatches ? 1.0 : 0.0,
            'number' => $numberMatches ? 1.0 : 0.0,
            'address' => $addressMatches ? 1.0 : 0.0,
        ]];
    }
}
