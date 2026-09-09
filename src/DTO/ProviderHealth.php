<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

final readonly class ProviderHealth
{
    /**
     * @param list<Diagnostic|string> $diagnostics
     */
    public function __construct(
        public bool $healthy,
        public ?string $providerName = null,
        public ?string $message = null,
        public array $diagnostics = [],
    ) {}

    public static function healthy(?string $providerName = null): self
    {
        return new self(true, $providerName);
    }

    public static function unhealthy(string $message, ?string $providerName = null): self
    {
        return new self(false, $providerName, $message);
    }
}
