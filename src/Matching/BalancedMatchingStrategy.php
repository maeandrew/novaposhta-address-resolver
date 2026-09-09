<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Matching;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\MatchingStrategy;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Candidate;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\MatchResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;

class BalancedMatchingStrategy implements MatchingStrategy
{
    protected readonly StringSimilarity $similarity;

    public function __construct(?TextNormalizer $normalizer = null)
    {
        $this->similarity = new StringSimilarity($normalizer ?? TextNormalizer::default());
    }

    /**
     * @param iterable<Settlement|Warehouse> $candidates
     */
    public function match(AddressQuery $query, iterable $candidates): MatchResult
    {
        $matches = [];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof Settlement) {
                [$score, $signals] = $this->scoreSettlement($query, $candidate);
            } elseif ($candidate instanceof Warehouse) {
                [$score, $signals] = $this->scoreWarehouse($query, $candidate);
            } else {
                continue;
            }

            $matches[] = new Candidate($candidate, $score, $signals);
        }

        usort(
            $matches,
            static fn(Candidate $left, Candidate $right): int => $right->score <=> $left->score
                ?: strcmp($left->id, $right->id),
        );

        return new MatchResult($matches);
    }

    /**
     * @return array{0: float, 1: array<string, float|int|string>}
     */
    protected function scoreSettlement(AddressQuery $query, Settlement $settlement): array
    {
        if ($query->settlementRef !== null) {
            $score = $query->settlementRef === $settlement->ref ? 1.0 : 0.0;

            return [$score, ['reference' => $score]];
        }

        $nameScore = $this->settlementNameScore($query->city, $settlement);

        if ($nameScore === 0.0) {
            return [0.0, ['name' => 0.0]];
        }

        $score = $nameScore * 0.82;
        $signals = ['name' => $nameScore];

        if ($query->region !== null) {
            $regionScore = $this->similarity->score($query->region, $settlement->region);
            $signals['region'] = $regionScore;
            $score += $regionScore * 0.12;
        }

        if ($query->district !== null) {
            $districtScore = $this->similarity->score($query->district, $settlement->district);
            $signals['district'] = $districtScore;
            $score += $districtScore * 0.06;
        }

        return [min(1.0, $score), $signals];
    }

    /**
     * @return array{0: float, 1: array<string, float|int|string>}
     */
    protected function scoreWarehouse(AddressQuery $query, Warehouse $warehouse): array
    {
        if ($query->warehouseRef !== null) {
            $score = $query->warehouseRef === $warehouse->ref ? 1.0 : 0.0;

            return [$score, ['reference' => $score]];
        }

        $warehouseType = WarehouseType::fromValue($warehouse->type);

        if ($query->warehouseType !== WarehouseType::UNKNOWN
            && $warehouseType !== $query->warehouseType) {
            return [0.0, ['type' => 0.0]];
        }

        if ($query->warehouseNumber !== null) {
            $numberScore = $warehouse->number === $query->warehouseNumber ? 1.0 : 0.0;

            if ($numberScore === 0.0) {
                return [0.0, ['number' => 0.0]];
            }

            $typeScore = $query->warehouseType === WarehouseType::UNKNOWN ? 0.95 : 1.0;

            return [$typeScore, ['number' => $numberScore, 'type' => $typeScore]];
        }

        $typeScore = $query->warehouseType === WarehouseType::UNKNOWN ? 0.25 : 0.45;
        $addressScore = $query->streetAddress === null
            ? 0.0
            : max(
                $this->similarity->score($query->streetAddress, $warehouse->address),
                $this->similarity->score($query->streetAddress, $warehouse->name),
            );
        $textScore = $query->warehouseText === null
            ? 0.0
            : max(
                $this->similarity->score($query->warehouseText, $warehouse->name),
                $this->similarity->score($query->warehouseText, $warehouse->address),
            );

        $addressWeight = $query->warehouseType === WarehouseType::UNKNOWN ? 0.75 : 0.55;
        $textWeight = 1.0 - $addressWeight;
        $score = $typeScore + ($addressScore * $addressWeight) + ($textScore * $textWeight * 0.1);

        return [min(1.0, $score), [
            'type' => $query->warehouseType === WarehouseType::UNKNOWN ? 0.0 : 1.0,
            'address' => $addressScore,
            'text' => $textScore,
        ]];
    }

    protected function settlementNameScore(string $queryCity, Settlement $settlement): float
    {
        $variants = [$settlement->normalizedName, $settlement->name, ...$settlement->aliases];
        $score = 0.0;

        foreach ($variants as $variant) {
            $score = max($score, $this->similarity->score($queryCity, $this->stripSettlementPrefix($variant)));
        }

        return $score;
    }

    private function stripSettlementPrefix(string $value): string
    {
        $value = trim($value);

        return (string) preg_replace('/^(?:м|місто|с|село)\s+/u', '', $value);
    }
}
