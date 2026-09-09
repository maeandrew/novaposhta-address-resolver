<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use InvalidArgumentException;

final readonly class ResolutionPolicy
{
    public function __construct(
        public float $autoResolveThreshold = 0.90,
        public float $ambiguityMargin = 0.08,
        public float $minimumCandidateScore = 0.35,
        public float $settlementMinimumScore = 0.65,
    ) {
        foreach ([
            'autoResolveThreshold' => $this->autoResolveThreshold,
            'ambiguityMargin' => $this->ambiguityMargin,
            'minimumCandidateScore' => $this->minimumCandidateScore,
            'settlementMinimumScore' => $this->settlementMinimumScore,
        ] as $name => $value) {
            if ($value < 0.0 || $value > 1.0) {
                throw new InvalidArgumentException(sprintf('%s must be between 0 and 1.', $name));
            }
        }
    }

    public function canAutoResolve(MatchResult $matches): bool
    {
        $top = $matches->top();

        if ($top === null || $top->score < $this->autoResolveThreshold) {
            return false;
        }

        $second = $matches->second();

        return $second === null || ($top->score - $second->score) >= $this->ambiguityMargin;
    }
}
