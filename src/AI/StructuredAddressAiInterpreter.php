<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI;

use Closure;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\AddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\StructuredAiProvider;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiAddressHints;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiRanking;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredPrompt;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\AiProviderException;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\InvalidStructuredResponseException;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Redaction\AddressRedactor;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Candidate;

final class StructuredAddressAiInterpreter implements AddressAiInterpreter
{
    public function __construct(
        private readonly StructuredAiProvider $provider,
        private readonly AddressRedactor $redactor = new AddressRedactor(),
        private readonly ?Closure $policy = null,
    ) {}

    public function parse(AddressInput $input): AiAddressHints
    {
        $this->assertAllowed($input);
        $prompt = StructuredPrompt::forAddressParsing($this->redactor->redact($input->raw));
        $response = $this->provider->generate($prompt);

        return $this->parseHints($response->data);
    }

    /**
     * @param list<Candidate> $candidates
     */
    public function rank(AddressInput $input, array $candidates): AiRanking
    {
        $this->assertAllowed($input);
        $compactCandidates = array_map(
            static function (Candidate $candidate): array {
                $record = $candidate->warehouse ?? $candidate->settlement;

                return [
                    'candidate_id' => $candidate->id,
                    'kind' => $candidate->kind,
                    'name' => $record?->name,
                    'address' => $candidate->warehouse?->address,
                    'number' => $candidate->warehouse?->number,
                    'type' => $candidate->warehouse?->type,
                    'region' => $candidate->settlement?->region,
                    'district' => $candidate->settlement?->district,
                ];
            },
            $candidates,
        );
        $prompt = StructuredPrompt::forCandidateRanking(
            $this->redactor->redact($input->raw),
            $compactCandidates,
        );
        $response = $this->provider->generate($prompt);

        return AiRanking::fromArray($response->data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseHints(array $data): AiAddressHints
    {
        $required = [
            'city',
            'region',
            'district',
            'warehouse_type',
            'warehouse_number',
            'warehouse_text',
            'confidence',
            'uncertainties',
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidStructuredResponseException(sprintf('AI address hints are missing %s.', $key));
            }
        }

        if (!is_numeric($data['confidence']) || !is_array($data['uncertainties'])) {
            throw new InvalidStructuredResponseException('AI address hints contain invalid confidence data.');
        }

        return new AiAddressHints(
            city: $this->nullableString($data['city']),
            region: $this->nullableString($data['region']),
            district: $this->nullableString($data['district']),
            warehouseType: (string) $data['warehouse_type'],
            warehouseNumber: is_int($data['warehouse_number']) || is_string($data['warehouse_number'])
                ? $data['warehouse_number']
                : null,
            warehouseText: $this->nullableString($data['warehouse_text']),
            confidence: (float) $data['confidence'],
            uncertainties: array_values(array_map('strval', $data['uncertainties'])),
        );
    }

    private function assertAllowed(AddressInput $input): void
    {
        if ($this->policy !== null && !($this->policy)($input)) {
            throw new AiProviderException('AI use was rejected by the application policy.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidStructuredResponseException('AI address hint must be a string or null.');
        }

        return trim($value) === '' ? null : trim($value);
    }
}
