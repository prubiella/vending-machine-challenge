<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Infrastructure\Change;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Infrastructure\Change\LoggingChangeMaker;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class LoggingChangeMakerTest extends TestCase
{
    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
    }

    public function testGivesTheChangeOfTheChangeMakerItDecoratesAndLogsItAtInfo(): void
    {
        $change = $this->changeMaker()->makeChange(Money::fromCents(35), CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents, Coin::FiveCents));

        self::assertTrue($change->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
        self::assertSame([[
            Level::Info,
            'Change of 35 cents made.',
            ['amount_cents' => 35, 'change_by_cents' => [5 => 0, 10 => 1, 25 => 1, 100 => 0]],
        ]], $this->records());
    }

    public function testLogsAMissingExactChangeAtNoticeAndRethrowsIt(): void
    {
        try {
            $this->changeMaker()->makeChange(Money::fromCents(20), CoinCollection::of(Coin::TwentyFiveCents));
            self::fail('Expected a NoExactChangeException.');
        } catch (NoExactChangeException $e) {
            self::assertSame([[
                Level::Notice,
                'No change made: Cannot give exactly 20 cents with the coins available.',
                ['amount_cents' => 20, 'available_by_cents' => [5 => 0, 10 => 0, 25 => 1, 100 => 0]],
            ]], $this->records());
        }
    }

    /**
     * @return list<array{Level, string, array<mixed>}>
     */
    private function records(): array
    {
        return array_values(array_map(static fn (LogRecord $record): array => [$record->level, $record->message, $record->context], $this->logs->getRecords()));
    }

    private function changeMaker(): LoggingChangeMaker
    {
        return new LoggingChangeMaker(
            new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents),
            new Logger('test', [$this->logs]),
        );
    }
}
