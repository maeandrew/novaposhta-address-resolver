<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\DTO;

final readonly class AiRankedCandidate
{
    public function __construct(
        public string $candidateId,
        public float $score,
    ) {}

    /**
     * @return array{candidate_id: string, score: float}
     */
    public function toArray(): array
    {
        return [
            'candidate_id' => $this->candidateId,
            'score' => round($this->score, 6),
        ];
    }
}
