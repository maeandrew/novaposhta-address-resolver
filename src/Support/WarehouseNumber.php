<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support;

use InvalidArgumentException;

final class WarehouseNumber
{
    public static function normalize(int|string|null $number): ?int
    {
        if ($number === null || $number === '') {
            return null;
        }

        if (is_int($number)) {
            if ($number < 0) {
                throw new InvalidArgumentException('Warehouse number cannot be negative.');
            }

            return $number;
        }

        $number = trim($number);

        if (preg_match('/^\d+$/', $number) !== 1) {
            throw new InvalidArgumentException('Warehouse number must contain only digits.');
        }

        return (int) $number;
    }
}
