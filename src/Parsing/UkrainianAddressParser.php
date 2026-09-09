<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Parsing;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\AddressParser;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ParsedAddress;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Support\TextNormalizer;

final class UkrainianAddressParser implements AddressParser
{
    private readonly TextNormalizer $normalizer;

    public function __construct(?TextNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? TextNormalizer::default();
    }

    public function parse(AddressInput $input): ParsedAddress
    {
        $source = trim($input->raw);

        if ($source === '') {
            $source = $this->composeSource($input);
        }

        $warehouseType = $this->detectWarehouseType($source, $input->warehouse);
        $warehouseNumber = $input->warehouseNumber ?? $this->detectWarehouseNumber($source, $input->warehouse);
        $warehouseText = $this->detectWarehouseText($source, $input->warehouse, $warehouseType, $warehouseNumber);
        $city = $this->detectCity($source, $input->city, $warehouseType, $warehouseNumber);
        $region = $this->normalizeNullable($input->region ?? $this->detectLabeledValue($source, 'region'));
        $district = $this->normalizeNullable($input->district ?? $this->detectLabeledValue($source, 'district'));
        $streetAddress = $this->detectStreetAddress($source, $city, $warehouseType);
        $warnings = [];

        if ($city === '') {
            $warnings[] = 'city_not_detected';
        }

        if ($warehouseType === WarehouseType::UNKNOWN && $warehouseNumber !== null) {
            $warnings[] = 'warehouse_type_not_detected';
        }

        return new ParsedAddress(
            city: $city,
            region: $region,
            district: $district,
            warehouseType: $warehouseType,
            warehouseNumber: $warehouseNumber,
            warehouseText: $warehouseText,
            streetAddress: $streetAddress,
            settlementRef: $this->nullableTrim($input->settlementRef),
            warehouseRef: $this->nullableTrim($input->warehouseRef),
            warnings: $warnings,
        );
    }

    private function composeSource(AddressInput $input): string
    {
        $parts = [];

        foreach ([$input->city, $input->region, $input->district, $input->warehouse, $input->postalCode] as $part) {
            if ($part !== null && trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        if ($input->warehouseNumber !== null) {
            $parts[] = (string) $input->warehouseNumber;
        }

        return implode(', ', $parts);
    }

    private function detectWarehouseType(string $source, ?string $warehouse): WarehouseType
    {
        $value = trim($source . ' ' . (string) $warehouse);

        if (preg_match('/\b(?:поштомат\w*|постамат\w*|постомат\w*|postomat)\b/iu', $value) === 1) {
            return WarehouseType::POSTOMAT;
        }

        if (preg_match(
            '/(?:\bпункт(?:\s+(?:приймання|при(?:й|и)мання|прийому|приёма)[-\s]?(?:видачі|выдачи))?\b|\bпвз\b|\bсамовивоз\w*\b|\bсамовывоз\w*\b)/iu',
            $value,
        ) === 1) {
            return WarehouseType::PICKUP;
        }

        if (preg_match(
            '/(?:\bвідділен\w*\b|\bвідд\.?\b|\bотделен\w*\b|\bотд\.?\b|\bнп\b|\bпвз\b|\bbranch\b|\bфілі\w*\b|\bфилиал\w*\b|\bнова\s+пошта\b|\bновая\s+почта\b)/iu',
            $value,
        ) === 1) {
            return WarehouseType::BRANCH;
        }

        return WarehouseType::UNKNOWN;
    }

    private function detectWarehouseNumber(string $source, ?string $warehouse): ?int
    {
        $value = trim($source . ' ' . (string) $warehouse);
        $patterns = [
            '/(?:№|#|номер)\s*(\d+)/iu',
            '/(?:відділен\w*|відд\.?|отделен\w*|отд\.?|поштомат\w*|постамат\w*|постомат\w*|пункт\w*|пвз|нп|branch|нова\s+пошта|новая\s+почта)\s*(?:№|#|номер|no\.?|n\.?|номер)?\s*(\d+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        if (!str_contains($source, ',') && !str_contains($source, ';')
            && preg_match('/(?:^|\s)(\d{1,6})\s*$/u', $source, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function detectWarehouseText(
        string $source,
        ?string $warehouse,
        WarehouseType $type,
        ?int $number,
    ): ?string {
        if ($warehouse !== null && trim($warehouse) !== '') {
            return trim($warehouse);
        }

        if ($type === WarehouseType::PICKUP
            && preg_match(
                '/(?:\bпункт(?:\s+(?:приймання|при(?:й|и)мання|прийому|приёма)[-\s]?(?:видачі|выдачи))?\b|\bпвз\b|\bсамовивоз\w*\b|\bсамовывоз\w*\b)/iu',
                $source,
                $matches,
            ) === 1) {
            return trim($matches[0]);
        }

        if (preg_match(
            '/(?:\bвідділен\w*\b|\bвідд\.?\b|\bотделен\w*\b|\bотд\.?\b|\bпоштомат\w*\b|\bпостамат\w*\b|\bпостомат\w*\b|\bнп\b|\bbranch\b|\bнова\s+пошта\b|\bновая\s+почта\b)(?:\s*(?:№|#|номер|no\.?|n\.?)?\s*\d+)?/iu',
            $source,
            $matches,
        ) === 1) {
            return trim($matches[0]);
        }

        return $number === null ? null : (string) $number;
    }

    private function detectCity(
        string $source,
        ?string $explicitCity,
        WarehouseType $warehouseType,
        ?int $warehouseNumber,
    ): string {
        if ($explicitCity !== null && trim($explicitCity) !== '') {
            return $this->normalizeCity(trim($explicitCity));
        }

        if ($source === '') {
            return '';
        }

        $cityPart = preg_split('/[,;]+/u', $source, 2)[0] ?? $source;

        if (!str_contains($source, ',') && !str_contains($source, ';')) {
            $marker = '(?:відділен\w*|відд\.?|отделен\w*|отд\.?|поштомат\w*|постамат\w*|постомат\w*|пункт\w*|пвз|нп|branch|нова\s+пошта|новая\s+почта|філі\w*|филиал\w*)';
            $parts = preg_split('/\b' . $marker . '\b/iu', $cityPart, 2);

            if (is_array($parts) && isset($parts[0]) && trim($parts[0]) !== '') {
                $cityPart = $parts[0];
            }
        }

        if ($warehouseType === WarehouseType::PICKUP && str_contains($source, ',')) {
            $cityPart = (preg_split('/[,;]+/u', $source, 2)[0] ?? $source);
        }

        if ($warehouseNumber !== null && !str_contains($cityPart, ',') && !str_contains($cityPart, ';')) {
            $cityPart = (string) preg_replace('/\s+\d{1,6}\s*$/u', '', $cityPart);
        }

        return $this->normalizeCity($cityPart);
    }

    private function detectStreetAddress(string $source, string $city, WarehouseType $type): ?string
    {
        if ($source === '') {
            return null;
        }

        $parts = preg_split('/[,;]+/u', $source);

        if (is_array($parts) && count($parts) > 1) {
            $addressParts = array_slice($parts, 1);

            if ($type === WarehouseType::PICKUP && $addressParts !== []) {
                array_shift($addressParts);
            }

            $candidate = trim(implode(', ', $addressParts));

            if ($candidate !== '' && $this->looksLikeStreet($candidate)) {
                return $this->normalizer->normalize($candidate);
            }
        }

        if (preg_match(
            '/\b(?:вул\.?|вулиця|ул\.?|улица|просп\.?|проспект|пров\.?|провулок|пер\.?|переулок|пл\.?|площа|площадь)\b.*$/iu',
            $source,
            $matches,
        ) === 1) {
            $candidate = trim($matches[0]);

            return $candidate === '' ? null : $this->normalizer->normalize($candidate);
        }

        return null;
    }

    private function detectLabeledValue(string $source, string $label): ?string
    {
        $pattern = $label === 'region'
            ? '/([^,;]+?)\s+(?:область|обл\.?)\b/iu'
            : '/([^,;]+?)\s+(?:район|р-н\.?)\b/iu';

        if (preg_match($pattern, $source, $matches) !== 1) {
            return null;
        }

        return $this->normalizeNullable($matches[1]);
    }

    private function normalizeCity(string $city): string
    {
        $city = trim($city);
        $city = (string) preg_replace('/^\s*(?:м\.?|місто|г\.?|город|с\.?|село|смт\.?|пгт\.?)\s+/iu', '', $city);
        $city = (string) preg_replace('/\s+(?:область|обл\.?)\s*$/iu', '', $city);

        return $this->normalizer->normalize($city);
    }

    private function normalizeNullable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = $this->normalizer->normalize($value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function looksLikeStreet(string $value): bool
    {
        return preg_match(
            '/\b(?:вул\.?|вулиця|ул\.?|улица|просп\.?|проспект|пров\.?|провулок|пер\.?|переулок|пл\.?|площа|площадь)\b/iu',
            $value,
        ) === 1;
    }
}
