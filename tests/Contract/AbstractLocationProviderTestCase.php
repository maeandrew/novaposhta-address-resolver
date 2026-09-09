<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Contract;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;
use PHPUnit\Framework\TestCase;

/**
 * Offline contract suite for LocationProvider adapters.
 *
 * An adapter test suite can extend this class and override the provider
 * factory plus the references used by its fake SDK or HTTP response.
 */
abstract class AbstractLocationProviderTestCase extends TestCase
{
    abstract protected function makeLocationProvider(): LocationProvider;

    abstract protected function expectedSettlementReference(): string;

    abstract protected function expectedWarehouseReference(): string;

    abstract protected function warehouseSettlementReference(): string;

    abstract protected function expectedProviderName(): string;

    abstract protected function settlementQuery(): SettlementQuery;

    abstract protected function warehouseQuery(): WarehouseQuery;

    public function testHealthCheckReturnsAProviderIdentity(): void
    {
        $health = $this->makeLocationProvider()->healthCheck();

        self::assertTrue($health->healthy);
        self::assertSame($this->expectedProviderName(), $health->providerName);
    }

    public function testSettlementSearchReturnsNormalizedSettlementRecords(): void
    {
        $result = $this->makeLocationProvider()->searchSettlements($this->settlementQuery());

        self::assertNotEmpty($result->records);
        self::assertSame($this->expectedSettlementReference(), $result->records[0]->ref);
        self::assertSame($this->expectedProviderName(), $result->providerName);
    }

    public function testWarehouseSearchHonorsNumberAndType(): void
    {
        $settlementResult = $this->makeLocationProvider()
            ->searchSettlements(new SettlementQuery(reference: $this->warehouseSettlementReference()));
        self::assertNotEmpty($settlementResult->records);
        $settlement = $settlementResult->records[0];
        self::assertInstanceOf(Settlement::class, $settlement);

        $result = $this->makeLocationProvider()->searchWarehouses(
            $settlement,
            $this->warehouseQuery(),
        );

        self::assertCount(1, $result->records);
        self::assertSame($this->expectedWarehouseReference(), $result->records[0]->ref);
        self::assertSame(WarehouseType::BRANCH, $result->records[0]->type);
    }
}
