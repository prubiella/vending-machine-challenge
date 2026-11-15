<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Console;

use App\VendingMachine\Infrastructure\Console\MoneyNotation;
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
        self::assertSame($notation, MoneyNotation::coin($cents));
    }
}
