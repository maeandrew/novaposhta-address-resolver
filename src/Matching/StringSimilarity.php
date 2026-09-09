<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Matching;

use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;

final class StringSimilarity
{
    private readonly TextNormalizer $normalizer;

    public function __construct(?TextNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? TextNormalizer::default();
    }

    public function score(?string $left, ?string $right): float
    {
        $left = $this->normalizer->normalize((string) $left);
        $right = $this->normalizer->normalize((string) $right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 1.0;
        }

        $leftTokens = array_values(array_filter(explode(' ', $left)));
        $rightTokens = array_values(array_filter(explode(' ', $right)));
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));
        $jaccard = $union === 0 ? 0.0 : $intersection / $union;

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return max($jaccard, 0.85);
        }

        return $jaccard;
    }
}
