<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Enums;

enum WarehouseType: string
{
    case BRANCH = 'branch';
    case POSTOMAT = 'postomat';
    case PICKUP = 'pickup';
    case UNKNOWN = 'unknown';

    public static function fromValue(self|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');

        return match ($normalized) {
            'branch', 'відділення', 'відд', 'нп' => self::BRANCH,
            'postomat', 'поштомат', 'поштомати' => self::POSTOMAT,
            'pickup', 'pickup_point', 'pickup-point', 'пункт', 'пункт приймання-видачі' => self::PICKUP,
            default => self::UNKNOWN,
        };
    }
}
