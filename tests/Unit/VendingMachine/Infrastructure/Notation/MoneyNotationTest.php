<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Notation;

use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyNotationTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function coins(): iterable
    {
        yield '5 cents' => [5, '0.05'];
        yield '10 cents' => [10, '0.10'];
        yield '25 cents' => [25, '0.25'];
        yield '100 cents' => [100, '1'];
    }

    #[DataProvider('coins')]
    public function testWritesACoinAsTheStatementDoes(int $cents, string $notation): void
    {
        self::assertSame($notation, MoneyNotation::coinToken($cents));
    }

    #[DataProvider('coins')]
    public function testReadsTheCentsOfACoinWrittenAsTheStatementDoes(int $cents, string $notation): void
    {
        self::assertSame($cents, MoneyNotation::coinCents($notation));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notCoins(): iterable
    {
        yield 'a foreign coin' => ['0.50'];
        yield 'a coin without its trailing zero' => ['0.1'];
        yield 'a coin with decimals the statement leaves out' => ['1.00'];
        yield 'a word' => ['WATER'];
        yield 'nothing' => [''];
    }

    #[DataProvider('notCoins')]
    public function testReadsNoCentsFromWhatIsNotACoin(string $notation): void
    {
        self::assertNull(MoneyNotation::coinCents($notation));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function amounts(): iterable
    {
        yield 'nothing' => [0, '0.00'];
        yield 'cents only' => [65, '0.65'];
        yield 'a whole unit' => [100, '1.00'];
        yield 'units and cents' => [150, '1.50'];
        yield 'several units' => [1205, '12.05'];
    }

    #[DataProvider('amounts')]
    public function testWritesAnAmountWithTwoDecimals(int $cents, string $notation): void
    {
        self::assertSame($notation, MoneyNotation::twoDecimals($cents));
    }
}
