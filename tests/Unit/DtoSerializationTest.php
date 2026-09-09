<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Unit;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ParsedAddress;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;
use PHPUnit\Framework\TestCase;

final class DtoSerializationTest extends TestCase
{
    public function testResolutionSerializationHasStablePublicShapeAndOmitsRawPayload(): void
    {
        $parsed = ParsedAddress::empty();
        $result = new ResolutionResult(
            ResolutionStatus::INVALID_INPUT,
            null,
            null,
            0.0,
            [],
            $parsed,
            ['empty_input'],
            'fixture',
        );

        self::assertSame([
            'status',
            'settlement',
            'warehouse',
            'confidence',
            'candidates',
            'parsed_address',
            'diagnostics',
            'provider_name',
        ], array_keys($result->toArray()));
        self::assertSame('invalid_input', $result->toArray()['status']);
        self::assertSame('empty_input', $result->toArray()['diagnostics'][0]['message']);
    }

    public function testAddressInputCanBeCreatedFromText(): void
    {
        $input = AddressInput::fromText(' Київ ');

        self::assertSame(' Київ ', $input->raw);
        self::assertTrue($input->hasMeaningfulData());
    }
}
