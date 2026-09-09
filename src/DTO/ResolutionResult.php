<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;

final readonly class ResolutionResult
{
    /**
     * @param list<Candidate> $candidates
     * @param list<Diagnostic|string> $diagnostics
     */
    public function __construct(
        ResolutionStatus|string $status,
        public ?Settlement $settlement,
        public ?Warehouse $warehouse,
        float $confidence,
        public array $candidates,
        public ParsedAddress $parsedAddress,
        array $diagnostics = [],
        public ?string $providerName = null,
    ) {
        $this->status = $status instanceof ResolutionStatus
            ? $status
            : ResolutionStatus::from($status);
        $this->confidence = max(0.0, min(1.0, $confidence));
        $this->diagnostics = array_map(
            static fn(Diagnostic|string $diagnostic): Diagnostic => $diagnostic instanceof Diagnostic
                ? $diagnostic
                : Diagnostic::fromString($diagnostic),
            $diagnostics,
        );
    }

    public readonly ResolutionStatus $status;
    public readonly float $confidence;
    /** @var list<Diagnostic> */
    public readonly array $diagnostics;

    public function isResolved(): bool
    {
        return $this->status === ResolutionStatus::RESOLVED;
    }

    public function needsReview(): bool
    {
        return $this->status === ResolutionStatus::AMBIGUOUS;
    }

    /**
     * @return list<string>
     */
    public function diagnosticCodes(): array
    {
        return array_map(
            static fn(Diagnostic $diagnostic): string => $diagnostic->code,
            $this->diagnostics,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeRawPayload = false): array
    {
        return [
            'status' => $this->status->value,
            'settlement' => $this->settlement?->toArray($includeRawPayload),
            'warehouse' => $this->warehouse?->toArray($includeRawPayload),
            'confidence' => round($this->confidence, 6),
            'candidates' => array_map(
                static fn(Candidate $candidate): array => $candidate->toArray($includeRawPayload),
                $this->candidates,
            ),
            'parsed_address' => $this->parsedAddress->toArray(),
            'diagnostics' => array_map(
                static fn(Diagnostic $diagnostic): array => $diagnostic->toArray(),
                $this->diagnostics,
            ),
            'provider_name' => $this->providerName,
        ];
    }
}
