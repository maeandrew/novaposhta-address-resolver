<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Unit;

use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Parsing\UkrainianAddressParser;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('spokenWarehouseNumbers')]
    public function testItParsesSpokenWarehouseNumbers(string $input, int $expected): void
    {
        $parsed = (new UkrainianAddressParser())->parse(AddressInput::fromText($input));

        self::assertSame($expected, $parsed->warehouseNumber);
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function spokenWarehouseNumbers(): iterable
    {
        yield 'ukrainian compound number' => ['Київ, відділення двадцять п’ять', 25];
        yield 'ukrainian number with marker' => ['Київ, номер двісті вісімдесят п’ять', 285];
        yield 'russian compound number' => ['Киев, отделение сорок два', 42];
        yield 'bare spoken number' => ['Київ двадцять п’ять', 25];
    }

    public function testItFindsCityAfterRegionInInvertedAddressOrder(): void
    {
        $parsed = (new UkrainianAddressParser())->parse(
            AddressInput::fromText('Київська область, м. Васильків, відділення один'),
        );

        self::assertSame('васильків', $parsed->city);
        self::assertSame('київська', $parsed->region);
        self::assertSame(1, $parsed->warehouseNumber);
    }

    public function testStreetHouseNumberIsNotTreatedAsWarehouseNumber(): void
    {
        $parsed = (new UkrainianAddressParser())->parse(
            AddressInput::fromText('Київ вул. Хрещатик 15'),
        );

        self::assertSame('київ', $parsed->city);
        self::assertNull($parsed->warehouseNumber);
        self::assertSame('вулиця хрещатик 15', $parsed->streetAddress);
    }
}
