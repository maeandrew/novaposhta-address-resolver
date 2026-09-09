<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Contract;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FixtureLoader;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FixtureLocationProvider;

final class FixtureLocationProviderContractTest extends AbstractLocationProviderTestCase
{
    protected function makeLocationProvider(): LocationProvider
    {
        return new FixtureLocationProvider(FixtureLoader::settlements(), FixtureLoader::warehouses());
    }

    protected function expectedSettlementReference(): string
    {
        return 'fixture-ivanivka-vinnytsia';
    }

    protected function expectedWarehouseReference(): string
    {
        return 'fixture-kyiv-133';
    }

    protected function warehouseSettlementReference(): string
    {
        return 'fixture-kyiv';
    }

    protected function expectedProviderName(): string
    {
        return 'fixture';
    }

    protected function settlementQuery(): SettlementQuery
    {
        return new SettlementQuery(
            name: 'Іванівка',
            normalizedName: 'іванівка',
            region: 'Вінницька',
        );
    }

    protected function warehouseQuery(): WarehouseQuery
    {
        return new WarehouseQuery(number: 133, type: WarehouseType::BRANCH);
    }
}
