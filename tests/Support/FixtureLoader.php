<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Support;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use RuntimeException;

final class FixtureLoader
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function addressCases(): array
    {
        return self::json('address-cases.json');
    }

    /**
     * @return list<Settlement>
     */
    public static function settlements(): array
    {
        return array_map(
            static fn(array $record): Settlement => new Settlement(
                ref: (string) $record['ref'],
                name: (string) $record['name'],
                normalizedName: (string) $record['normalized_name'],
                region: $record['region'] !== null ? (string) $record['region'] : null,
                district: $record['district'] !== null ? (string) $record['district'] : null,
                type: (string) $record['type'],
                aliases: array_values(array_map('strval', $record['aliases'])),
            ),
            self::json('settlements.json'),
        );
    }

    /**
     * @return array<string, list<Warehouse>>
     */
    public static function warehouses(): array
    {
        $records = self::json('warehouses.json');
        $result = [];

        foreach ($records as $settlementRef => $warehouses) {
            $result[(string) $settlementRef] = array_map(
                static fn(array $record): Warehouse => new Warehouse(
                    ref: (string) $record['ref'],
                    settlementRef: (string) $record['settlement_ref'],
                    number: $record['number'],
                    name: (string) $record['name'],
                    address: (string) $record['address'],
                    type: (string) $record['type'],
                    isActive: (bool) $record['is_active'],
                ),
                $warehouses,
            );
        }

        return $result;
    }

    /**
     * @return mixed
     */
    private static function json(string $file): mixed
    {
        $path = dirname(__DIR__, 2) . '/examples/fixtures/' . $file;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read fixture %s.', $file));
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
