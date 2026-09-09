<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\WarehouseNumber;

final readonly class WarehouseQuery
{
    public readonly ?string $type;

    public function __construct(
        public ?string $reference = null,
        int|string|null $number = null,
        public ?string $name = null,
        public ?string $address = null,
        WarehouseType|string|null $type = null,
        public bool $fuzzy = false,
    ) {
        $this->number = WarehouseNumber::normalize($number);
        $this->type = $type === null ? null : WarehouseType::fromValue($type)->value;
    }

    public readonly ?int $number;

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'number' => $this->number,
            'name' => $this->name,
            'address' => $this->address,
            'type' => $this->type,
            'fuzzy' => $this->fuzzy,
        ];
    }

}
