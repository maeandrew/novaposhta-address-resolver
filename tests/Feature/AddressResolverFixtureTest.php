<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Feature;

use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FixtureLoader;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FixtureLocationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressResolverFixtureTest extends TestCase
{
    #[DataProvider('addressCases')]
    public function testFixtureCaseResolvesDeterministically(array $case): void
    {
        $provider = new FixtureLocationProvider(
            FixtureLoader::settlements(),
            FixtureLoader::warehouses(),
        );
        $result = (new AddressResolver($provider))->resolve(AddressInput::fromText((string) $case['input']));
        $expected = $case['expected'];

        self::assertSame($expected['status'], $result->status->value, $case['id']);

        if (isset($expected['settlement_ref'])) {
            self::assertSame($expected['settlement_ref'], $result->settlement?->ref, $case['id']);
        }

        if (isset($expected['warehouse_ref'])) {
            self::assertSame($expected['warehouse_ref'], $result->warehouse?->ref, $case['id']);
        }
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function addressCases(): iterable
    {
        foreach (FixtureLoader::addressCases() as $case) {
            yield (string) $case['id'] => [$case];
        }
    }

    public function testProviderFailureIsNotReportedAsNotFound(): void
    {
        $provider = new FixtureLocationProvider(
            FixtureLoader::settlements(),
            FixtureLoader::warehouses(),
            'warehouses',
        );
        $result = (new AddressResolver($provider))->resolve(
            AddressInput::fromText('Київ, відділення №285'),
        );

        self::assertSame(ResolutionStatus::PROVIDER_ERROR, $result->status);
        self::assertContains('provider_error', $result->diagnosticCodes());
    }
}
