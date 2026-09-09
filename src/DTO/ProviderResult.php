<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

/**
 * @template T of Settlement|Warehouse
 */
final readonly class ProviderResult
{
    /**
     * @param list<T> $records
     * @param list<Diagnostic|string> $diagnostics
     */
    public function __construct(
        public array $records,
        public array $diagnostics = [],
        public ?string $providerName = null,
    ) {}

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->records;
    }

    /**
     * @param list<T> $records
     * @param list<Diagnostic|string> $diagnostics
     * @return self<T>
     */
    public static function fromRecords(
        array $records,
        array $diagnostics = [],
        ?string $providerName = null,
    ): self {
        return new self($records, $diagnostics, $providerName);
    }
}
