<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support\Rules;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\NormalizationRule;

final class CaseFoldRule implements NormalizationRule
{
    public function apply(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
