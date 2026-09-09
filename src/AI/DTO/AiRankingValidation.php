<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\DTO;

final readonly class AiRankingValidation
{
    /**
     * @param list<string> $unknownCandidateIds
     */
    public function __construct(
        public bool $accepted,
        public ?AiRanking $ranking = null,
        public array $unknownCandidateIds = [],
    ) {}
}
