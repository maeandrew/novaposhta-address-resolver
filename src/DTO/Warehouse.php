<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use InvalidArgumentException;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\WarehouseNumber;

final readonly class Warehouse
{
    /**
     * @param array<string, mixed>|null $rawPayload
     */
    public function __construct(
        public string $ref,
        public string $settlementRef,
        int|string|null $number,
        public string $name,
        public string $address,
        WarehouseType|string $type = WarehouseType::UNKNOWN,
        public bool $isActive = true,
        public ?array $rawPayload = null,
    ) {
        if (trim($this->ref) === '') {
            throw new InvalidArgumentException('Warehouse reference cannot be empty.');
        }

        if (trim($this->settlementRef) === '') {
            throw new InvalidArgumentException('Warehouse settlement reference cannot be empty.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Warehouse name cannot be empty.');
        }

        $this->number = WarehouseNumber::normalize($number);
        $this->type = WarehouseType::fromValue($type);
    }

    public readonly ?int $number;
    public readonly WarehouseType $type;

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeRawPayload = false): array
    {
        $result = [
            'ref' => $this->ref,
            'settlement_ref' => $this->settlementRef,
            'number' => $this->number,
            'name' => $this->name,
            'address' => $this->address,
            'type' => $this->type->value,
            'is_active' => $this->isActive,
        ];

        if ($includeRawPayload) {
            $result['raw_payload'] = $this->rawPayload;
        }

        return $result;
    }

}
