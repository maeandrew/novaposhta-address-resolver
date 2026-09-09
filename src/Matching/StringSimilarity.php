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

        if ($this->containsWholeTokens($leftTokens, $rightTokens)) {
            return max($jaccard, 0.85);
        }

        if (count($leftTokens) === 1 && count($rightTokens) === 1) {
            $shortestLength = min($this->length($leftTokens[0]), $this->length($rightTokens[0]));
            $editScore = $this->editSimilarity($leftTokens[0], $rightTokens[0]);

            if ($shortestLength >= 4 && $editScore >= 0.75) {
                return max($jaccard, min(0.95, 0.70 + ($editScore * 0.20)));
            }
        }

        return $jaccard;
    }

    /**
     * @param list<string> $leftTokens
     * @param list<string> $rightTokens
     */
    private function containsWholeTokens(array $leftTokens, array $rightTokens): bool
    {
        $shorter = count($leftTokens) <= count($rightTokens) ? $leftTokens : $rightTokens;
        $longer = count($leftTokens) <= count($rightTokens) ? $rightTokens : $leftTokens;

        return $shorter !== [] && count(array_diff($shorter, $longer)) === 0;
    }

    private function editSimilarity(string $left, string $right): float
    {
        $leftCharacters = $this->characters($left);
        $rightCharacters = $this->characters($right);
        $leftLength = count($leftCharacters);
        $rightLength = count($rightCharacters);

        if ($leftLength === 0 || $rightLength === 0) {
            return 0.0;
        }

        $previous = range(0, $rightLength);

        for ($leftIndex = 1; $leftIndex <= $leftLength; $leftIndex++) {
            $current = [$leftIndex];

            for ($rightIndex = 1; $rightIndex <= $rightLength; $rightIndex++) {
                $cost = $leftCharacters[$leftIndex - 1] === $rightCharacters[$rightIndex - 1] ? 0 : 1;
                $current[$rightIndex] = min(
                    $current[$rightIndex - 1] + 1,
                    $previous[$rightIndex] + 1,
                    $previous[$rightIndex - 1] + $cost,
                );
            }

            $previous = $current;
        }

        return 1.0 - ($previous[$rightLength] / max($leftLength, $rightLength));
    }

    /**
     * @return list<string>
     */
    private function characters(string $value): array
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? $characters : [];
    }

    private function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }
}
