<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\InvalidStructuredResponseException;

final readonly class AiRanking
{
    /**
     * @param list<AiRankedCandidate> $rankedCandidates
     */
    public function __construct(
        public array $rankedCandidates,
        public string $reason = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['ranked_candidates']) || !is_array($data['ranked_candidates'])) {
            throw new InvalidStructuredResponseException('AI ranking is missing ranked_candidates.');
        }

        $rankedCandidates = [];

        foreach ($data['ranked_candidates'] as $candidate) {
            if (!is_array($candidate)
                || !isset($candidate['candidate_id'], $candidate['score'])
                || !is_string($candidate['candidate_id'])
                || !is_numeric($candidate['score'])) {
                throw new InvalidStructuredResponseException('AI ranking contains an invalid candidate entry.');
            }

            $score = (float) $candidate['score'];
            if ($score < 0.0 || $score > 1.0) {
                throw new InvalidStructuredResponseException('AI ranking score must be between 0 and 1.');
            }

            $rankedCandidates[] = new AiRankedCandidate($candidate['candidate_id'], $score);
        }

        usort(
            $rankedCandidates,
            static fn(AiRankedCandidate $left, AiRankedCandidate $right): int => $right->score <=> $left->score
                ?: strcmp($left->candidateId, $right->candidateId),
        );

        return new self($rankedCandidates, isset($data['reason']) ? (string) $data['reason'] : '');
    }

    public function top(): ?AiRankedCandidate
    {
        return $this->rankedCandidates[0] ?? null;
    }

    public function second(): ?AiRankedCandidate
    {
        return $this->rankedCandidates[1] ?? null;
    }

    public function canAutoSelect(float $threshold, float $margin): bool
    {
        $top = $this->top();

        if ($top === null || $top->score < $threshold) {
            return false;
        }

        $second = $this->second();

        return $second === null || ($top->score - $second->score) >= $margin;
    }

    /**
     * @param list<string> $allowedCandidateIds
     */
    public function validateAgainst(array $allowedCandidateIds): AiRankingValidation
    {
        $allowed = array_fill_keys($allowedCandidateIds, true);
        $unknown = [];

        foreach ($this->rankedCandidates as $candidate) {
            if (!isset($allowed[$candidate->candidateId])) {
                $unknown[] = $candidate->candidateId;
            }
        }

        $unknown = array_values(array_unique($unknown));

        return new AiRankingValidation($unknown === [], $unknown === [] ? $this : null, $unknown);
    }

    /**
     * @return array{ranked_candidates: list<array{candidate_id: string, score: float}>, reason: string}
     */
    public function toArray(): array
    {
        return [
            'ranked_candidates' => array_map(
                static fn(AiRankedCandidate $candidate): array => $candidate->toArray(),
                $this->rankedCandidates,
            ),
            'reason' => $this->reason,
        ];
    }
}
