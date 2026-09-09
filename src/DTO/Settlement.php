<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

use InvalidArgumentException;
use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;

final readonly class Settlement
{
    /**
     * @param list<string> $aliases
     * @param array<string, mixed>|null $rawPayload
     */
    public function __construct(
        public string $ref,
        public string $name,
        ?string $normalizedName = null,
        public ?string $region = null,
        public ?string $district = null,
        public string $type = 'city',
        public array $aliases = [],
        public ?array $rawPayload = null,
        ?TextNormalizer $normalizer = null,
    ) {
        if (trim($this->ref) === '') {
            throw new InvalidArgumentException('Settlement reference cannot be empty.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Settlement name cannot be empty.');
        }

        $normalizer ??= TextNormalizer::default();
        $this->normalizedName = $normalizedName !== null && trim($normalizedName) !== ''
            ? $normalizer->normalize($normalizedName)
            : $normalizer->normalize($this->name);
    }

    public readonly string $normalizedName;

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeRawPayload = false): array
    {
        $result = [
            'ref' => $this->ref,
            'name' => $this->name,
            'normalized_name' => $this->normalizedName,
            'region' => $this->region,
            'district' => $this->district,
            'type' => $this->type,
            'aliases' => array_values($this->aliases),
        ];

        if ($includeRawPayload) {
            $result['raw_payload'] = $this->rawPayload;
        }

        return $result;
    }
}
