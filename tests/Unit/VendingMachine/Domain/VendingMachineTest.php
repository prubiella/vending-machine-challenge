<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain;

use App\Shared\Domain\DomainException;
use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Inventory\UnknownSelectorException;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\Wallet\InsufficientCreditException;
use PHPUnit\Framework\TestCase;

final class VendingMachineTest extends TestCase
{
    public function testAFreshlyInstalledMachineHasEmptySlotsNoCoinsAndAnEmptyTray(): void
    {
        $machine = self::installed();

        self::assertSame(0, self::unitsOf($machine, 'WATER'));
        self::assertSame(0, self::unitsOf($machine, 'JUICE'));
        self::assertSame(0, self::unitsOf($machine, 'SODA'));
        self::assertTrue($machine->wallet()->stock()->isEmpty());
        self::assertTrue($machine->wallet()->credit()->isEmpty());
        self::assertSame([], $machine->tray()->products());
        self::assertTrue($machine->tray()->coins()->isEmpty());
    }

    public function testInsertedCoinsJoinTheCredit(): void
    {
        $machine = self::installed();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->insertCoin(Coin::TwentyFiveCents);

        self::assertTrue($machine->wallet()->credit()->equals(CoinCollection::of(Coin::OneHundredCents, Coin::TwentyFiveCents)));
        self::assertTrue($machine->wallet()->stock()->isEmpty());
    }

    public function testPayingTheExactPriceOfASodaGivesASoda(): void
    {
        $machine = self::serviced();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->insertCoin(Coin::TwentyFiveCents);
        $machine->insertCoin(Coin::TwentyFiveCents);
        $machine->select(Selector::fromString('SODA'), self::changeMaker());

        self::assertSame(['Soda'], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->isEmpty());
        self::assertSame(4, self::unitsOf($machine, 'SODA'));
        self::assertTrue($machine->wallet()->credit()->isEmpty());
    }

    public function testPayingAWaterWithAOneGivesTheWaterAndTheChange(): void
    {
        $machine = self::serviced();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        self::assertSame(['Water'], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
        $stockLeft = CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents, Coin::OneHundredCents);
        self::assertTrue($machine->wallet()->stock()->equals($stockLeft));
    }

    public function testAnUnknownSelectorKeepsTheCreditAndChangesNothing(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);

        self::assertSelectionRefused($machine, 'COFFEE', UnknownSelectorException::class);
    }

    public function testASoldOutProductKeepsTheCreditAndChangesNothing(): void
    {
        $machine = self::serviced();
        $machine->service(['WATER' => 0], $machine->wallet()->stock());
        $machine->insertCoin(Coin::OneHundredCents);

        self::assertSelectionRefused($machine, 'WATER', SoldOutException::class);
    }

    public function testInsufficientCreditKeepsTheCreditAndChangesNothing(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::TwentyFiveCents);
        $machine->insertCoin(Coin::TwentyFiveCents);

        self::assertSelectionRefused($machine, 'WATER', InsufficientCreditException::class);
    }

    public function testNoExactChangeKeepsTheCreditAndChangesNothing(): void
    {
        $machine = self::serviced();
        $machine->service([], CoinCollection::empty());
        $machine->insertCoin(Coin::OneHundredCents);

        self::assertSelectionRefused($machine, 'WATER', NoExactChangeException::class);
    }

    public function testAFreshlyInstalledMachineSellsNothing(): void
    {
        $machine = self::installed();
        $machine->insertCoin(Coin::OneHundredCents);

        self::assertSelectionRefused($machine, 'WATER', SoldOutException::class);
    }

    public function testServiceSetsTheUnitsOfEachSelectorAndTheCoinStock(): void
    {
        $machine = self::installed();

        $machine->service(['WATER' => 3, 'SODA' => 1], CoinCollection::of(Coin::TenCents, Coin::FiveCents));

        self::assertSame(3, self::unitsOf($machine, 'WATER'));
        self::assertSame(0, self::unitsOf($machine, 'JUICE'));
        self::assertSame(1, self::unitsOf($machine, 'SODA'));
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TenCents, Coin::FiveCents)));
    }

    public function testServiceAcceptsASelectorMadeOnlyOfDigits(): void
    {
        $machine = VendingMachine::install(self::product('42', 'Gum', 50));

        $machine->service(['42' => 3], CoinCollection::empty());

        self::assertSame(3, self::unitsOf($machine, '42'));
    }

    public function testServiceReplacesTheCountsRatherThanAddingToThem(): void
    {
        $machine = self::serviced();

        $machine->service(['WATER' => 2, 'JUICE' => 0], CoinCollection::of(Coin::TwentyFiveCents));

        self::assertSame(2, self::unitsOf($machine, 'WATER'));
        self::assertSame(0, self::unitsOf($machine, 'JUICE'));
        self::assertSame(5, self::unitsOf($machine, 'SODA'));
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
    }

    public function testServiceWithAnUnknownSelectorChangesNothing(): void
    {
        $machine = self::serviced();
        $before = self::snapshot($machine);

        try {
            $machine->service(['WATER' => 1, 'COFFEE' => 4], CoinCollection::empty());
            self::fail('Expected UnknownSelectorException.');
        } catch (UnknownSelectorException) {
            self::assertSame($before, self::snapshot($machine));
        }
    }

    /**
     * @param class-string<DomainException> $exception
     */
    private static function assertSelectionRefused(VendingMachine $machine, string $selector, string $exception): void
    {
        $before = self::snapshot($machine);

        try {
            $machine->select(Selector::fromString($selector), self::changeMaker());
            self::fail(\sprintf('Expected %s.', $exception));
        } catch (DomainException $refusal) {
            self::assertInstanceOf($exception, $refusal);
            self::assertSame($before, self::snapshot($machine));
        }
    }

    private static function installed(): VendingMachine
    {
        return VendingMachine::install(
            self::product('WATER', 'Water', 65),
            self::product('JUICE', 'Juice', 100),
            self::product('SODA', 'Soda', 150),
        );
    }

    private static function serviced(): VendingMachine
    {
        $machine = self::installed();
        $machine->service(
            ['WATER' => 5, 'JUICE' => 5, 'SODA' => 5],
            CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents, Coin::TenCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents),
        );

        return $machine;
    }

    private static function unitsOf(VendingMachine $machine, string $selector): int
    {
        return $machine->inventory()->slotFor(Selector::fromString($selector))->units();
    }

    /**
     * @return array{units: list<int>, credit: list<int>, stock: list<int>, trayProducts: list<string>, trayCoins: list<int>}
     */
    private static function snapshot(VendingMachine $machine): array
    {
        return [
            'units' => [self::unitsOf($machine, 'WATER'), self::unitsOf($machine, 'JUICE'), self::unitsOf($machine, 'SODA')],
            'credit' => self::countsOf($machine->wallet()->credit()),
            'stock' => self::countsOf($machine->wallet()->stock()),
            'trayProducts' => self::productsInTray($machine),
            'trayCoins' => self::countsOf($machine->tray()->coins()),
        ];
    }

    /**
     * @return list<string>
     */
    private static function productsInTray(VendingMachine $machine): array
    {
        return array_map(static fn (Product $product): string => $product->name()->value(), $machine->tray()->products());
    }

    /**
     * @return list<int>
     */
    private static function countsOf(CoinCollection $coins): array
    {
        return array_map(static fn (Coin $coin): int => $coins->countOf($coin), Coin::cases());
    }

    private static function changeMaker(): FewestCoinsChangeMaker
    {
        return new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents);
    }

    private static function product(string $selector, string $name, int $priceInCents): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString($name), Money::fromCents($priceInCents));
    }
}
