<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;
use MaeAndrew\NovaPoshtaAddressResolver\Support\WarehouseNumber;

final readonly class AddressQuery
{
    public readonly string $city;
    public readonly ?string $region;
    public readonly ?string $district;
    public readonly WarehouseType $warehouseType;
    public readonly ?int $warehouseNumber;
    public readonly ?string $warehouseText;
    public readonly ?string $streetAddress;
    public readonly ?string $settlementRef;
    public readonly ?string $warehouseRef;

    public function __construct(
        string $city = '',
        ?string $region = null,
        ?string $district = null,
        WarehouseType|string $warehouseType = WarehouseType::UNKNOWN,
        int|string|null $warehouseNumber = null,
        ?string $warehouseText = null,
        ?string $streetAddress = null,
        ?string $settlementRef = null,
        ?string $warehouseRef = null,
        ?TextNormalizer $normalizer = null,
    ) {
        $normalizer ??= TextNormalizer::default();
        $this->city = $normalizer->normalize($city);
        $this->region = self::normalizeNullable($normalizer, $region);
        $this->district = self::normalizeNullable($normalizer, $district);
        $this->warehouseType = WarehouseType::fromValue($warehouseType);
        $this->warehouseNumber = self::normalizeNumber($warehouseNumber);
        $this->warehouseText = self::normalizeNullable($normalizer, $warehouseText);
        $this->streetAddress = self::normalizeNullable($normalizer, $streetAddress);
        $this->settlementRef = self::normalizeNullable($normalizer, $settlementRef, false);
        $this->warehouseRef = self::normalizeNullable($normalizer, $warehouseRef, false);
    }

    public static function fromParsed(ParsedAddress $parsed, ?TextNormalizer $normalizer = null): self
    {
        return new self(
            city: $parsed->city,
            region: $parsed->region,
            district: $parsed->district,
            warehouseType: $parsed->warehouseType,
            warehouseNumber: $parsed->warehouseNumber,
            warehouseText: $parsed->warehouseText,
            streetAddress: $parsed->streetAddress,
            settlementRef: $parsed->settlementRef,
            warehouseRef: $parsed->warehouseRef,
            normalizer: $normalizer,
        );
    }

    public function hasWarehouseHint(): bool
    {
        return $this->warehouseType !== WarehouseType::UNKNOWN
            || $this->warehouseNumber !== null
            || $this->warehouseText !== null
            || $this->streetAddress !== null
            || $this->warehouseRef !== null;
    }

    /**
     * @return array<string, int|string|null>
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
        ];
    }

    private static function normalizeNullable(
        TextNormalizer $normalizer,
        ?string $value,
        bool $normalize = true,
    ): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = $normalize ? $normalizer->normalize($value) : trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeNumber(int|string|null $number): ?int
    {
        return WarehouseNumber::normalize($number);
    }
}
