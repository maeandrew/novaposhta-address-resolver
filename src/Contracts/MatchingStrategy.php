<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Contracts;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\MatchResult;

interface MatchingStrategy
{
    /**
     * @param iterable<\MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement|\MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse> $candidates
     */
    public function match(AddressQuery $query, iterable $candidates): MatchResult;
}
