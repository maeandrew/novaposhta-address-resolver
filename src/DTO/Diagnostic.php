<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\DTO;

final readonly class Diagnostic
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $severity = 'info',
        public array $context = [],
    ) {}

    public static function fromString(string $message, string $code = 'diagnostic'): self
    {
        return new self($code, $message);
    }

    /**
     * @return array{code: string, message: string, severity: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity,
            'context' => $this->context,
        ];
    }
}
