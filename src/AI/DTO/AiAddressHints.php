<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\WarehouseNumber;

final readonly class AiAddressHints
{
    /**
     * @param list<string> $uncertainties
     */
    public function __construct(
        public ?string $city,
        public ?string $region,
        public ?string $district,
        WarehouseType|string $warehouseType,
        int|string|null $warehouseNumber,
        public ?string $warehouseText,
        float $confidence,
        public array $uncertainties = [],
    ) {
        $this->warehouseType = WarehouseType::fromValue($warehouseType);
        $this->warehouseNumber = WarehouseNumber::normalize($warehouseNumber);
        $this->confidence = max(0.0, min(1.0, $confidence));
    }

    public readonly WarehouseType $warehouseType;
    public readonly ?int $warehouseNumber;
    public readonly float $confidence;

    public function isUsable(float $minimumConfidence = 0.70): bool
    {
        return $this->confidence >= $minimumConfidence
            && ($this->city !== null || $this->warehouseNumber !== null);
    }

    public function toAddressInput(AddressInput $source): AddressInput
    {
        return new AddressInput(
            raw: $source->raw,
            city: $this->city ?? $source->city,
            region: $this->region ?? $source->region,
            district: $this->district ?? $source->district,
            warehouse: $this->warehouseText ?? $source->warehouse,
            warehouseNumber: $this->warehouseNumber ?? $source->warehouseNumber,
            postalCode: $source->postalCode,
            settlementRef: $source->settlementRef,
            warehouseRef: $source->warehouseRef,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'city' => $this->city,
            'region' => $this->region,
            'district' => $this->district,
            'warehouse_type' => $this->warehouseType->value,
            'warehouse_number' => $this->warehouseNumber,
            'warehouse_text' => $this->warehouseText,
            'confidence' => round($this->confidence, 6),
            'uncertainties' => array_values($this->uncertainties),
        ];
    }

}
