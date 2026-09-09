<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\Redaction;

final class AddressRedactor
{
    /**
     * @param list<string> $knownNames
     */
    public function __construct(private readonly array $knownNames = []) {}

    public function redact(string $value): string
    {
        $value = (string) preg_replace(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
            '[REDACTED_EMAIL]',
            $value,
        );
        $value = (string) preg_replace(
            '/(?<!\d)(?:\+?380[\s-]*\(?\d{2}\)?[\s-]*\d{3}[\s-]*\d{2}[\s-]*\d{2}|\+?38[\s-]*\(?0\d{2}\)?[\s-]*\d{3}[\s-]*\d{2}[\s-]*\d{2}|\(?0\d{2}\)?[\s-]*\d{3}[\s-]*\d{2}[\s-]*\d{2})(?!\d)/u',
            '[REDACTED_PHONE]',
            $value,
        );
        $value = (string) preg_replace(
            '/(?:\b(?:піб|фио|ім(?:\'|’|ʼ)?я|имя|name|full\s+name)\b)\s*[:#-]?\s*[\p{L}][\p{L}.\'’ʼ-]*(?:\s+[\p{L}][\p{L}.\'’ʼ-]*){1,3}/iu',
            '[REDACTED_NAME]',
            $value,
        );
        $value = $this->redactKnownNames($value);

        return (string) preg_replace(
            '/\b(?:замовлення|заказ|order)\s*(?:№|#|:)?\s*[\p{L}\p{N}-]+/iu',
            '[REDACTED_ORDER]',
            $value,
        );
    }

    private function redactKnownNames(string $value): string
    {
        $names = array_values(array_filter(
            $this->knownNames,
            static fn(mixed $name): bool => is_string($name) && trim($name) !== '',
        ));

        if ($names === []) {
            return $value;
        }

        usort($names, static fn(string $left, string $right): int => mb_strlen($right, 'UTF-8') <=> mb_strlen($left, 'UTF-8'));
        $alternatives = implode('|', array_map(static fn(string $name): string => preg_quote(trim($name), '/'), $names));

        return (string) preg_replace(
            '/(?<!\p{L})(?:' . $alternatives . ')(?!\p{L})/iu',
            '[REDACTED_NAME]',
            $value,
        );
    }
}
