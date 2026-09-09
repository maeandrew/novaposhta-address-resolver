<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

final readonly class Candidate
{
    public readonly string $id;
    public readonly string $ref;
    public readonly string $kind;
    public readonly ?Settlement $settlement;
    public readonly ?Warehouse $warehouse;
    public readonly float $score;

    /**
     * @param Settlement|Warehouse $record
     * @param array<string, float|int|string> $signals
     */
    public function __construct(
        public Settlement|Warehouse $record,
        float $score,
        public array $signals = [],
    ) {
        $this->id = $record->ref;
        $this->ref = $record->ref;
        $this->kind = $record instanceof Settlement ? 'settlement' : 'warehouse';
        $this->settlement = $record instanceof Settlement ? $record : null;
        $this->warehouse = $record instanceof Warehouse ? $record : null;
        $this->score = max(0.0, min(1.0, $score));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeRawPayload = false): array
    {
        return [
            'id' => $this->id,
            'ref' => $this->ref,
            'kind' => $this->kind,
            'score' => round($this->score, 6),
            'signals' => $this->signals,
            'settlement' => $this->settlement?->toArray($includeRawPayload),
            'warehouse' => $this->warehouse?->toArray($includeRawPayload),
        ];
    }
}
