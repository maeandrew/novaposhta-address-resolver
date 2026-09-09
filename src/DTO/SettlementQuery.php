<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

final readonly class SettlementQuery
{
    public function __construct(
        public ?string $reference = null,
        public ?string $name = null,
        public ?string $normalizedName = null,
        public ?string $region = null,
        public ?string $district = null,
        public bool $fuzzy = false,
    ) {}

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'name' => $this->name,
            'normalized_name' => $this->normalizedName,
            'region' => $this->region,
            'district' => $this->district,
            'fuzzy' => $this->fuzzy,
        ];
    }
}
