<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support\Rules;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\NormalizationRule;

final class UnicodeWhitespaceRule implements NormalizationRule
{
    public function apply(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
