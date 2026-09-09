<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Contracts;

interface NormalizationRule
{
    public function apply(string $value): string;
}
