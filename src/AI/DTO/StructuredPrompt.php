<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\DTO;

final readonly class StructuredPrompt
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $schema
     * @param list<string> $allowedCandidateIds
     */
    public function __construct(
        public string $task,
        public string $systemInstruction,
        public string $userText,
        public array $payload,
        public array $schema,
        public string $schemaName,
        public array $allowedCandidateIds = [],
    ) {}

    public static function forAddressParsing(string $redactedText): self
    {
        return new self(
            task: 'parse_address',
            systemInstruction: 'Extract address hints only. Never invent provider references or IDs.',
            userText: $redactedText,
            payload: ['address' => $redactedText],
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'city' => ['type' => ['string', 'null']],
                    'region' => ['type' => ['string', 'null']],
                    'district' => ['type' => ['string', 'null']],
                    'warehouse_type' => [
                        'type' => 'string',
                        'enum' => ['branch', 'postomat', 'pickup', 'unknown'],
                    ],
                    'warehouse_number' => ['type' => ['integer', 'null']],
                    'warehouse_text' => ['type' => ['string', 'null']],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'uncertainties' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => [
                    'city',
                    'region',
                    'district',
                    'warehouse_type',
                    'warehouse_number',
                    'warehouse_text',
                    'confidence',
                    'uncertainties',
                ],
            ],
            schemaName: 'address_hints',
        );
    }

    /**
     * @param list<array<string, mixed>> $candidates
     */
    public static function forCandidateRanking(string $redactedText, array $candidates): self
    {
        $allowedIds = array_values(array_map(
            static fn(array $candidate): string => (string) ($candidate['candidate_id'] ?? ''),
            $candidates,
        ));

        return new self(
            task: 'rank_candidates',
            systemInstruction: 'Rank only the supplied candidate IDs. Never create or alter IDs.',
            userText: $redactedText,
            payload: [
                'address' => $redactedText,
                'candidates' => $candidates,
            ],
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'ranked_candidates' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'candidate_id' => ['type' => 'string'],
                                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            ],
                            'required' => ['candidate_id', 'score'],
                        ],
                    ],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['ranked_candidates', 'reason'],
            ],
            schemaName: 'candidate_ranking',
            allowedCandidateIds: $allowedIds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'system_instruction' => $this->systemInstruction,
            'user_text' => $this->userText,
            'payload' => $this->payload,
            'schema' => $this->schema,
            'schema_name' => $this->schemaName,
            'allowed_candidate_ids' => $this->allowedCandidateIds,
        ];
    }
}
