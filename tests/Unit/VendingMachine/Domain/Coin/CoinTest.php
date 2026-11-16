<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Coin;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\UnknownCoinException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoinTest extends TestCase
{
    /**
     * @return iterable<string, array{Coin, int}>
     */
    public static function amounts(): iterable
    {
        yield '5 cents' => [Coin::FiveCents, 5];
        yield '10 cents' => [Coin::TenCents, 10];
        yield '25 cents' => [Coin::TwentyFiveCents, 25];
        yield '100 cents' => [Coin::OneHundredCents, 100];
    }

    #[DataProvider('amounts')]
    public function testIsWorthItsAmountInCents(Coin $coin, int $cents): void
    {
        self::assertSame($cents, $coin->amount()->cents());
    }

    #[DataProvider('amounts')]
    public function testIsFoundByItsAmountInCents(Coin $coin, int $cents): void
    {
        self::assertSame($coin, Coin::fromCents($cents));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rejectedAmounts(): iterable
    {
        yield '1 cent' => [1];
        yield '2 cents' => [2];
        yield '20 cents' => [20];
        yield '50 cents' => [50];
        yield '200 cents' => [200];
    }

    #[DataProvider('rejectedAmounts')]
    public function testIsNotAnyOtherAmount(int $cents): void
    {
        $this->expectException(UnknownCoinException::class);
        $this->expectExceptionMessageIs(\sprintf('There is no coin of %d cents.', $cents));

        Coin::fromCents($cents);
    }

    public function testTheSmallestCoinIsTheFiveCents(): void
    {
        self::assertSame(Coin::FiveCents, Coin::smallest());
    }
}
