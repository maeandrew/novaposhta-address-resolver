<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\Support\WarehouseNumber;

final readonly class AddressInput
{
    public readonly ?int $warehouseNumber;

    public function __construct(
        public string $raw,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $district = null,
        public ?string $warehouse = null,
        int|string|null $warehouseNumber = null,
        public ?string $postalCode = null,
        public ?string $settlementRef = null,
        public ?string $warehouseRef = null,
    ) {
        $this->warehouseNumber = self::normalizeNumber($warehouseNumber);
    }

    public static function fromText(string $raw): self
    {
        return new self($raw);
    }

    public function hasMeaningfulData(): bool
    {
        return trim($this->raw) !== ''
            || self::hasText($this->city)
            || self::hasText($this->region)
            || self::hasText($this->district)
            || self::hasText($this->warehouse)
            || $this->warehouseNumber !== null
            || self::hasText($this->postalCode)
            || self::hasText($this->settlementRef)
            || self::hasText($this->warehouseRef);
    }

    public function asText(): string
    {
        if (trim($this->raw) !== '') {
            return $this->raw;
        }

        $parts = [];

        foreach ([$this->city, $this->region, $this->district, $this->warehouse, $this->postalCode] as $part) {
            if ($part !== null && trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        if ($this->warehouseNumber !== null) {
            $parts[] = (string) $this->warehouseNumber;
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'city' => $this->city,
            'region' => $this->region,
            'district' => $this->district,
            'warehouse' => $this->warehouse,
            'warehouse_number' => $this->warehouseNumber,
            'postal_code' => $this->postalCode,
            'settlement_ref' => $this->settlementRef,
            'warehouse_ref' => $this->warehouseRef,
        ];
    }

    private static function hasText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private static function normalizeNumber(int|string|null $number): ?int
    {
        return WarehouseNumber::normalize($number);
    }
}
