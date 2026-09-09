<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\NormalizationRule;

final class NormalizationPipeline
{
    /**
     * @param iterable<NormalizationRule> $rules
     */
    public function __construct(private readonly iterable $rules) {}

    public function normalize(string $value): string
    {
        foreach ($this->rules as $rule) {
            $value = $rule->apply($value);
        }

        return $value;
    }
}
