<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\Redaction;

final class AddressRedactor
{
    public function redact(string $value): string
    {
        $value = (string) preg_replace(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
            '[REDACTED_EMAIL]',
            $value,
        );
        $value = (string) preg_replace(
            '/(?<!\d)(?:\+?380[\s-]?\d{2}[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}|0\d{2}[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2})(?!\d)/u',
            '[REDACTED_PHONE]',
            $value,
        );

        return (string) preg_replace(
            '/\b(?:замовлення|заказ|order)\s*(?:№|#|:)?\s*[\p{L}\p{N}-]+/iu',
            '[REDACTED_ORDER]',
            $value,
        );
    }
}
