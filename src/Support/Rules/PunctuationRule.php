<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support\Rules;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\NormalizationRule;

final class PunctuationRule implements NormalizationRule
{
    public function apply(string $value): string
    {
        $value = str_replace(
            ["'", '’', '‘', 'ʼ', 'ʻ', 'ʹ', '՚'],
            ' ',
            $value,
        );
        $value = str_replace(['№', '#'], ' ', $value);

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
    }
}
