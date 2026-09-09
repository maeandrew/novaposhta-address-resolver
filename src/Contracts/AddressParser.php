<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Contracts;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ParsedAddress;

interface AddressParser
{
    public function parse(AddressInput $input): ParsedAddress;
}
