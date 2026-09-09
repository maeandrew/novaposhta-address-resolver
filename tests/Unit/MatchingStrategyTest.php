<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Unit;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Matching\BalancedMatchingStrategy;
use MaeAndrew\NovaPoshtaAddressResolver\Matching\StrictMatchingStrategy;
use MaeAndrew\NovaPoshtaAddressResolver\Matching\StringSimilarity;
use PHPUnit\Framework\TestCase;

final class MatchingStrategyTest extends TestCase
{
    public function testBalancedStrategyUsesExactWarehouseNumber(): void
    {
        $query = new AddressQuery(
            city: 'Київ',
            warehouseType: WarehouseType::BRANCH,
            warehouseNumber: 285,
        );
        $matches = (new BalancedMatchingStrategy())->match($query, [
            new Warehouse('fixture-kyiv-12', 'fixture-kyiv', 12, 'Відділення №12', 'вулиця Тестова, 1', 'branch'),
            new Warehouse('fixture-kyiv-285', 'fixture-kyiv', 285, 'Відділення №285', 'проспект Прикладний, 10', 'branch'),
        ]);

        self::assertSame('fixture-kyiv-285', $matches->top()?->ref);
        self::assertSame(1.0, $matches->top()?->score);
    }

    public function testStrictStrategyDoesNotFuzzyMatchDifferentSettlement(): void
    {
        $query = new AddressQuery(city: 'Київ');
        $matches = (new StrictMatchingStrategy())->match($query, [
            new Settlement('fixture-lviv', 'Львів', 'львів'),
        ]);

        self::assertSame(0.0, $matches->top()?->score);
    }

    public function testNumberMatchDoesNotOverrideAConflictingStreetAddress(): void
    {
        $query = new AddressQuery(
            city: 'Київ',
            warehouseType: WarehouseType::BRANCH,
            warehouseNumber: 285,
            streetAddress: 'вулиця Чужа, 99',
        );
        $matches = (new BalancedMatchingStrategy())->match($query, [
            new Warehouse('fixture-kyiv-285', 'fixture-kyiv', 285, 'Відділення №285', 'проспект Прикладний, 10', 'branch'),
        ]);

        self::assertLessThan(0.90, $matches->top()?->score);
    }

    public function testWarehouseTextHasMeaningfulWeight(): void
    {
        $query = new AddressQuery(
            city: 'Київ',
            warehouseType: WarehouseType::BRANCH,
            warehouseText: 'відділення 285',
        );
        $matches = (new BalancedMatchingStrategy())->match($query, [
            new Warehouse('fixture-kyiv-12', 'fixture-kyiv', 12, 'Відділення №12', 'вулиця Тестова, 1', 'branch'),
            new Warehouse('fixture-kyiv-285', 'fixture-kyiv', 285, 'Відділення №285', 'проспект Прикладний, 10', 'branch'),
        ]);

        self::assertSame('fixture-kyiv-285', $matches->top()?->ref);
        self::assertGreaterThan($matches->second()?->score ?? 0.0, $matches->top()?->score ?? 0.0);
    }

    public function testAQueryWithoutWarehouseHintsDoesNotMatchEveryWarehouse(): void
    {
        $matches = (new StrictMatchingStrategy())->match(new AddressQuery(city: 'Київ'), [
            new Warehouse('fixture-kyiv-12', 'fixture-kyiv', 12, 'Відділення №12', 'вулиця Тестова, 1', 'branch'),
        ]);

        self::assertSame(0.0, $matches->top()?->score);
    }

    public function testSimilarityDoesNotTreatASettlementPrefixAsAWholeWordMatch(): void
    {
        $similarity = new StringSimilarity();

        self::assertLessThan(0.65, $similarity->score('Бар', 'Барвінкове'));
        self::assertGreaterThanOrEqual(0.85, $similarity->score('Львив', 'Львів'));
    }
}
