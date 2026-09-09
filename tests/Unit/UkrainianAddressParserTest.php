<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Unit;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Parsing\UkrainianAddressParser;
use PHPUnit\Framework\TestCase;

final class UkrainianAddressParserTest extends TestCase
{
    public function testItParsesAbbreviatedBranchInput(): void
    {
        $parsed = (new UkrainianAddressParser())->parse(AddressInput::fromText('м. Київ НП 285'));

        self::assertSame('київ', $parsed->city);
        self::assertSame(WarehouseType::BRANCH, $parsed->warehouseType);
        self::assertSame(285, $parsed->warehouseNumber);
    }

    public function testItParsesPickupPointAndStreetAddress(): void
    {
        $parsed = (new UkrainianAddressParser())->parse(
            AddressInput::fromText('Ободівка, пункт приймання-видачі, вул. Соборна, 33'),
        );

        self::assertSame('ободівка', $parsed->city);
        self::assertSame(WarehouseType::PICKUP, $parsed->warehouseType);
        self::assertSame('вулиця соборна 33', $parsed->streetAddress);
    }

    public function testCustomFieldsCanBeParsedWithoutRawText(): void
    {
        $parsed = (new UkrainianAddressParser())->parse(new AddressInput(
            raw: '',
            city: 'м. Київ',
            warehouse: 'відд.',
            warehouseNumber: '285',
        ));

        self::assertSame('київ', $parsed->city);
        self::assertSame(WarehouseType::BRANCH, $parsed->warehouseType);
        self::assertSame(285, $parsed->warehouseNumber);
    }
}
