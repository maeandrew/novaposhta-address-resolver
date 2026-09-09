<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

final readonly class MatchResult
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(public array $candidates) {}

    public function top(): ?Candidate
    {
        return $this->candidates[0] ?? null;
    }

    public function second(): ?Candidate
    {
        return $this->candidates[1] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    /**
     * @return list<Candidate>
     */
    public function above(float $minimumScore): array
    {
        return array_values(array_filter(
            $this->candidates,
            static fn(Candidate $candidate): bool => $candidate->score >= $minimumScore,
        ));
    }
}
