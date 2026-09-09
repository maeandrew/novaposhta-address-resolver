<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Events;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;

final readonly class AddressNeedsReview
{
    public function __construct(
        public AddressInput $input,
        public ResolutionResult $result,
    ) {}
}
