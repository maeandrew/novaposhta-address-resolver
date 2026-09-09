<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\WarehouseNumber;

final readonly class ParsedAddress
{
    public readonly string $normalizedCity;
    public readonly ?string $normalizedRegion;
    public readonly ?string $normalizedDistrict;

    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public string $city = '',
        public ?string $region = null,
        public ?string $district = null,
        WarehouseType|string $warehouseType = WarehouseType::UNKNOWN,
        int|string|null $warehouseNumber = null,
        public ?string $warehouseText = null,
        public ?string $streetAddress = null,
        public ?string $settlementRef = null,
        public ?string $warehouseRef = null,
        public array $warnings = [],
    ) {
        $this->normalizedCity = $this->city;
        $this->normalizedRegion = $this->region;
        $this->normalizedDistrict = $this->district;
        $this->warehouseType = WarehouseType::fromValue($warehouseType);
        $this->warehouseNumber = WarehouseNumber::normalize($warehouseNumber);
    }

    public readonly WarehouseType $warehouseType;
    public readonly ?int $warehouseNumber;

    public static function empty(): self
    {
        return new self();
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
            'street_address' => $this->streetAddress,
            'settlement_ref' => $this->settlementRef,
            'warehouse_ref' => $this->warehouseRef,
            'warnings' => array_values($this->warnings),
        ];
    }

}
