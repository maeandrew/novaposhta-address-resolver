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

    public function testItParsesBareNumberAfterCity(): void
    {
        $parsed = (new UkrainianAddressParser())->parse(AddressInput::fromText('Київ 133'));

        self::assertSame('київ', $parsed->city);
        self::assertSame(WarehouseType::UNKNOWN, $parsed->warehouseType);
        self::assertSame(133, $parsed->warehouseNumber);
    }

    public function testItRecognizesRussianBranchAndPostomatWords(): void
    {
        $parser = new UkrainianAddressParser();

        $branch = $parser->parse(AddressInput::fromText('Киев, отделение №12'));
        $postomat = $parser->parse(AddressInput::fromText('Львов, постамат 12345'));

        self::assertSame('киев', $branch->city);
        self::assertSame(WarehouseType::BRANCH, $branch->warehouseType);
        self::assertSame(12, $branch->warehouseNumber);
        self::assertSame('львов', $postomat->city);
        self::assertSame(WarehouseType::POSTOMAT, $postomat->warehouseType);
        self::assertSame(12345, $postomat->warehouseNumber);
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
