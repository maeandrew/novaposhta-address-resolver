<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Contracts;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderHealth;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;

interface LocationProvider
{
    /**
     * @return ProviderResult<Settlement>
     */
    public function searchSettlements(SettlementQuery $query): ProviderResult;

    /**
     * @return ProviderResult<\MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse>
     */
    public function searchWarehouses(
        Settlement $settlement,
        WarehouseQuery $query,
    ): ProviderResult;

    public function healthCheck(): ProviderHealth;
}
