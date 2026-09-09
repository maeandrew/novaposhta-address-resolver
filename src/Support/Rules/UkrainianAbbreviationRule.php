<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support\Rules;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\NormalizationRule;

final class UkrainianAbbreviationRule implements NormalizationRule
{
    public function apply(string $value): string
    {
        $replacements = [
            '/\bвідд\.?(?=\s|$)/u' => 'відділення',
            '/\bобл\.?(?=\s|$)/u' => 'область',
            '/\bр-н\.?(?=\s|$)/u' => 'район',
            '/\bвул\.?(?=\s|$)/u' => 'вулиця',
            '/\bпросп\.?(?=\s|$)/u' => 'проспект',
            '/\bпров\.?(?=\s|$)/u' => 'провулок',
            '/\bпл\.?(?=\s|$)/u' => 'площа',
        ];

        return (string) preg_replace(array_keys($replacements), array_values($replacements), $value);
    }
}
