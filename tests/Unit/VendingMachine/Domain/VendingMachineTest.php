<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain;

use App\Shared\Domain\DomainException;
use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Event\CreditReturned;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Event\ProductSold;
use App\VendingMachine\Domain\Event\StockRunningLow;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Inventory\UnknownSelectorException;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\TechnicianId;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\Wallet\CoinTubeCapacityExceededException;
use App\VendingMachine\Domain\Wallet\InsufficientCreditException;
use App\VendingMachine\Domain\Wallet\Wallet;
use PHPUnit\Framework\TestCase;

final class VendingMachineTest extends TestCase
{
    private const string MACHINE_ID = '01a1e2f0-aa80-70ea-832f-6110cbf86e31';
    private const string TECHNICIAN_ID = '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61';

    public function testAnInstalledMachineHasTheIdItWasInstalledWith(): void
    {
        self::assertTrue(self::installed()->id()->equals(MachineId::fromString(self::MACHINE_ID)));
    }

    public function testARestoredMachineKeepsItsId(): void
    {
        $machine = VendingMachine::restore(MachineId::fromString(self::MACHINE_ID), Inventory::of(), Wallet::stocked(CoinCollection::empty()), Tray::empty());

        self::assertTrue($machine->id()->equals(MachineId::fromString(self::MACHINE_ID)));
    }

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

    public function testPayingAWaterWithAOneHundredCentCoinGivesTheWaterAndTheChange(): void
    {
        $machine = self::serviced();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        self::assertSame(['Water'], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
        $stockLeft = CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents);
        self::assertTrue($machine->wallet()->stock()->equals($stockLeft));
        self::assertTrue($machine->wallet()->cashBox()->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testReturningTheCoinsDropsTheInsertedCoinsInTheTray(): void
    {
        $machine = self::serviced();
        $stock = $machine->wallet()->stock();

        $machine->insertCoin(Coin::TenCents);
        $machine->insertCoin(Coin::TenCents);
        $machine->returnCoins();

        self::assertSame([], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TenCents, Coin::TenCents)));
        self::assertTrue($machine->wallet()->credit()->isEmpty());
        self::assertTrue($machine->wallet()->stock()->equals($stock));
    }

    public function testTheTrayPilesUpSalesAndReturnsUntilCollected(): void
    {
        $machine = self::serviced();

        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());
        $machine->insertCoin(Coin::FiveCents);
        $machine->returnCoins();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('JUICE'), self::changeMaker());

        self::assertSame(['Water', 'Juice'], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents, Coin::FiveCents)));
    }

    public function testCollectingTheTrayEmptiesIt(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        $machine->collectTray();

        self::assertSame([], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->isEmpty());
    }

    public function testWhatDropsInAfterCollectingStartsAFreshPile(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());
        $machine->collectTray();

        $machine->insertCoin(Coin::TenCents);
        $machine->returnCoins();

        self::assertSame([], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TenCents)));
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
        $machine->service(self::technician(), ['WATER' => 0], $machine->wallet()->stock());
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
        $machine->service(self::technician(), [], CoinCollection::empty());
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

        $machine->service(self::technician(), ['WATER' => 3, 'SODA' => 1], CoinCollection::of(Coin::TenCents, Coin::FiveCents));

        self::assertSame(3, self::unitsOf($machine, 'WATER'));
        self::assertSame(0, self::unitsOf($machine, 'JUICE'));
        self::assertSame(1, self::unitsOf($machine, 'SODA'));
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TenCents, Coin::FiveCents)));
    }

    public function testServiceReplacesTheCountsRatherThanAddingToThem(): void
    {
        $machine = self::serviced();

        $machine->service(self::technician(), ['WATER' => 2, 'JUICE' => 0], CoinCollection::of(Coin::TwentyFiveCents));

        self::assertSame(2, self::unitsOf($machine, 'WATER'));
        self::assertSame(0, self::unitsOf($machine, 'JUICE'));
        self::assertSame(5, self::unitsOf($machine, 'SODA'));
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
    }

    public function testServiceDropsThePendingCreditInTheTray(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->insertCoin(Coin::FiveCents);

        $machine->service(self::technician(), ['WATER' => 8], CoinCollection::of(Coin::TwentyFiveCents));

        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::OneHundredCents, Coin::FiveCents)));
        self::assertTrue($machine->wallet()->credit()->isEmpty());
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TwentyFiveCents)));
        self::assertSame(8, self::unitsOf($machine, 'WATER'));
    }

    public function testServiceWithAnUnknownSelectorChangesNothing(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::TenCents);
        $before = self::snapshot($machine);

        try {
            $machine->service(self::technician(), ['WATER' => 1, 'COFFEE' => 4], CoinCollection::empty());
            self::fail('Expected UnknownSelectorException.');
        } catch (UnknownSelectorException) {
            self::assertSame($before, self::snapshot($machine));
        }
    }

    public function testServicePastACapacityChangesNothing(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::TenCents);
        $before = self::snapshot($machine);

        try {
            $machine->service(self::technician(), ['WATER' => 1], CoinCollection::fromCounts([25 => Wallet::TUBE_CAPACITY + 1]));
            self::fail('Expected CoinTubeCapacityExceededException.');
        } catch (CoinTubeCapacityExceededException) {
            self::assertSame($before, self::snapshot($machine));
        }
    }

    public function testASaleSendsTheCreditOfAFullTubeToTheCashBox(): void
    {
        $machine = self::installed();
        $machine->service(self::technician(), ['WATER' => 1], CoinCollection::fromCounts([5 => 10, 10 => 10, 25 => Wallet::TUBE_CAPACITY]));
        $machine->insertCoin(Coin::TwentyFiveCents);
        $machine->insertCoin(Coin::TwentyFiveCents);
        $machine->insertCoin(Coin::TwentyFiveCents);

        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        self::assertSame(['Water'], self::productsInTray($machine));
        self::assertSame(Wallet::TUBE_CAPACITY, $machine->wallet()->stock()->countOf(Coin::TwentyFiveCents));
        self::assertSame(3, $machine->wallet()->cashBox()->countOf(Coin::TwentyFiveCents));
    }

    public function testServiceEmptiesTheCashBox(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());
        self::assertFalse($machine->wallet()->cashBox()->isEmpty());

        $machine->service(self::technician(), ['WATER' => 1], CoinCollection::empty());

        self::assertTrue($machine->wallet()->cashBox()->isEmpty());
    }

    public function testARestoredMachineCarriesOnFromTheGivenParts(): void
    {
        $inventory = Inventory::of(Slot::of(self::product('WATER', 'Water', 65), 3));
        $wallet = Wallet::of(CoinCollection::of(Coin::OneHundredCents), CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents));
        $tray = Tray::empty()->addProduct(self::product('SODA', 'Soda', 150));

        $machine = VendingMachine::restore(MachineId::fromString(self::MACHINE_ID), $inventory, $wallet, $tray);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        self::assertSame(2, self::unitsOf($machine, 'WATER'));
        self::assertSame(['Soda', 'Water'], self::productsInTray($machine));
        self::assertTrue($machine->tray()->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
        self::assertTrue($machine->wallet()->stock()->isEmpty());
        self::assertTrue($machine->wallet()->cashBox()->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testAnInstalledOrRestoredMachineHasNoEvents(): void
    {
        $restored = VendingMachine::restore(MachineId::fromString(self::MACHINE_ID), Inventory::of(), Wallet::stocked(CoinCollection::empty()), Tray::empty());
        $stocked = VendingMachine::installStocked(MachineId::fromString(self::MACHINE_ID), ['WATER' => 2], CoinCollection::of(Coin::TenCents), self::product('WATER', 'Water', 65));

        self::assertSame([], self::installed()->releaseEvents());
        self::assertSame([], $restored->releaseEvents());
        self::assertSame([], $stocked->releaseEvents());
    }

    public function testAMachineInstalledStockedHoldsTheUnitsAndCoinsItWasGiven(): void
    {
        $machine = VendingMachine::installStocked(MachineId::fromString(self::MACHINE_ID), ['WATER' => 2], CoinCollection::of(Coin::TenCents), self::product('WATER', 'Water', 65));

        self::assertSame(2, self::unitsOf($machine, 'WATER'));
        self::assertTrue($machine->wallet()->stock()->equals(CoinCollection::of(Coin::TenCents)));
    }

    public function testAMachineInstalledStockedIsBoundedByItsCapacities(): void
    {
        $this->expectException(CoinTubeCapacityExceededException::class);

        VendingMachine::installStocked(MachineId::fromString(self::MACHINE_ID), [], CoinCollection::fromCounts([10 => Wallet::TUBE_CAPACITY + 1]));
    }

    public function testASaleRecordsTheProductSoldAndTheChangeGiven(): void
    {
        $machine = self::serviced();
        $machine->releaseEvents();
        $machine->insertCoin(Coin::OneHundredCents);

        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductSold::class, $events[0]);
        self::assertSame('WATER', $events[0]->product()->selector()->value());
        self::assertSame(65, $events[0]->product()->price()->cents());
        self::assertTrue($events[0]->change()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
    }

    public function testARefusedSaleRecordsNothing(): void
    {
        $machine = self::serviced();
        $machine->releaseEvents();
        $machine->insertCoin(Coin::TenCents);

        try {
            $machine->select(Selector::fromString('WATER'), self::changeMaker());
        } catch (InsufficientCreditException) {
        }

        self::assertSame([], $machine->releaseEvents());
    }

    public function testReturningTheCreditRecordsTheCoinsReturned(): void
    {
        $machine = self::serviced();
        $machine->releaseEvents();
        $machine->insertCoin(Coin::TenCents);
        $machine->insertCoin(Coin::FiveCents);

        $machine->returnCoins();

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CreditReturned::class, $events[0]);
        self::assertTrue($events[0]->coins()->equals(CoinCollection::of(Coin::TenCents, Coin::FiveCents)));
    }

    public function testReturningNoCreditRecordsNothing(): void
    {
        $machine = self::serviced();
        $machine->releaseEvents();

        $machine->returnCoins();

        self::assertSame([], $machine->releaseEvents());
    }

    public function testServiceRecordsWhoServicedTheMachineAndWhatTheySet(): void
    {
        $machine = self::installed();

        $machine->service(self::technician(), ['WATER' => 3, 'SODA' => 1], CoinCollection::of(Coin::TenCents));

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertTrue($events[0]->technicianId()->equals(self::technician()));
        self::assertSame(['WATER' => 3, 'SODA' => 1], $events[0]->unitsBySelector());
        self::assertTrue($events[0]->coins()->equals(CoinCollection::of(Coin::TenCents)));
        self::assertTrue($events[0]->collectedFromCashBox()->isEmpty());
    }

    public function testServiceRecordsWhatTheTechnicianCollectedFromTheCashBox(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());
        $machine->releaseEvents();

        $machine->service(self::technician(), [], CoinCollection::empty());

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertTrue($events[0]->collectedFromCashBox()->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testServiceRecordsTheCreditItReturns(): void
    {
        $machine = self::serviced();
        $machine->releaseEvents();
        $machine->insertCoin(Coin::OneHundredCents);

        $machine->service(self::technician(), [], CoinCollection::empty());

        $events = $machine->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertInstanceOf(CreditReturned::class, $events[1]);
        self::assertTrue($events[1]->coins()->equals(CoinCollection::of(Coin::OneHundredCents)));
    }

    public function testARefusedServiceRecordsNothing(): void
    {
        $machine = self::serviced();
        $machine->releaseEvents();
        $machine->insertCoin(Coin::TenCents);

        try {
            $machine->service(self::technician(), ['COFFEE' => 1], CoinCollection::empty());
            self::fail('Expected UnknownSelectorException.');
        } catch (UnknownSelectorException) {
        }

        self::assertSame([], $machine->releaseEvents());
    }

    public function testASaleThatLeavesASlotAtTheLowStockThresholdRecordsThatItRunsLow(): void
    {
        $machine = self::installed();
        $machine->service(self::technician(), ['WATER' => Slot::LOW_STOCK_THRESHOLD + 1], CoinCollection::fromCounts([25 => 10, 10 => 10]));
        $machine->releaseEvents();
        $machine->insertCoin(Coin::OneHundredCents);

        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        $events = $machine->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ProductSold::class, $events[0]);
        self::assertInstanceOf(StockRunningLow::class, $events[1]);
        self::assertSame('WATER', $events[1]->selector()->value());
        self::assertSame(Slot::LOW_STOCK_THRESHOLD, $events[1]->unitsLeft());
    }

    public function testASaleFromASlotAlreadyAtTheLowStockThresholdRecordsNoLowStock(): void
    {
        $machine = self::installed();
        $machine->service(self::technician(), ['WATER' => Slot::LOW_STOCK_THRESHOLD], CoinCollection::fromCounts([25 => 10, 10 => 10]));
        $machine->releaseEvents();
        $machine->insertCoin(Coin::OneHundredCents);

        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        self::assertSame([ProductSold::class], array_map(static fn (object $event): string => $event::class, $machine->releaseEvents()));
    }

    public function testASaleFarAboveTheLowStockThresholdRecordsNoLowStock(): void
    {
        $machine = self::installed();
        $machine->service(self::technician(), ['WATER' => Slot::LOW_STOCK_THRESHOLD + 5], CoinCollection::fromCounts([25 => 10, 10 => 10]));
        $machine->releaseEvents();
        $machine->insertCoin(Coin::OneHundredCents);

        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        self::assertSame([ProductSold::class], array_map(static fn (object $event): string => $event::class, $machine->releaseEvents()));
    }

    public function testEventsAreReleasedOnceInTheOrderTheyHappened(): void
    {
        $machine = self::serviced();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());

        $events = $machine->releaseEvents();

        self::assertCount(2, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertInstanceOf(ProductSold::class, $events[1]);
        self::assertSame([], $machine->releaseEvents());
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
            MachineId::fromString(self::MACHINE_ID),
            self::product('WATER', 'Water', 65),
            self::product('JUICE', 'Juice', 100),
            self::product('SODA', 'Soda', 150),
        );
    }

    private static function serviced(): VendingMachine
    {
        $machine = self::installed();
        $machine->service(
            self::technician(),
            ['WATER' => 5, 'JUICE' => 5, 'SODA' => 5],
            CoinCollection::of(Coin::FiveCents, Coin::FiveCents, Coin::TenCents, Coin::TenCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents),
        );

        return $machine;
    }

    private static function technician(): TechnicianId
    {
        return TechnicianId::fromString(self::TECHNICIAN_ID);
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
