<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use App\VendingMachine\Domain\Wallet\Wallet;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use App\VendingMachine\Infrastructure\Persistence\StoredMachineResetter;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What every adapter of the VendingMachineRepository port must do, run once per adapter, so they
 * stay interchangeable.
 */
abstract class VendingMachineRepositoryContractTestCase extends KernelTestCase
{
    abstract protected static function repository(): VendingMachineRepository&StoredMachineResetter;

    // Not the StartsWithNoStoredMachine trait: that one resets the container's store, and this case
    // resets the adapter under test.
    #[Before]
    protected function forgetTheStoredMachine(): void
    {
        static::repository()->reset();
    }

    public function testGivesTheDefaultMachineWhenNoneIsStored(): void
    {
        self::assertEquals(self::defaultMachine(), static::repository()->get());
    }

    public function testGivesBackEveryPartOfTheMachineItSaved(): void
    {
        $repository = static::repository();
        $machine = self::machineWithEveryPartFilled();

        $repository->save($machine);

        self::assertEquals($machine, $repository->get());
    }

    public function testSavingAgainReplacesTheStoredMachine(): void
    {
        $repository = static::repository();
        $repository->save(self::machineWithEveryPartFilled());
        $machine = $repository->get();

        $machine->collectTray();
        $machine->insertCoin(Coin::FiveCents);
        $repository->save($machine);

        self::assertEquals($machine, $repository->get());
    }

    public function testAChangeIsNotStoredUntilTheMachineIsSaved(): void
    {
        $repository = static::repository();

        $repository->get()->insertCoin(Coin::OneHundredCents);

        self::assertEquals(self::defaultMachine(), $repository->get());
    }

    public function testResettingPutsTheDefaultMachineBack(): void
    {
        $repository = static::repository();
        $repository->save(self::machineWithEveryPartFilled());

        $repository->reset();

        self::assertEquals(self::defaultMachine(), $repository->get());
    }

    public function testRefusesToSaveAMachineThatChangedSinceItWasLoaded(): void
    {
        $repository = static::repository();
        $repository->save(self::machineWithEveryPartFilled());
        $first = $repository->get();
        $second = $repository->get();
        $repository->save($first);

        $this->expectException(ConcurrentUpdateException::class);

        $repository->save($second);
    }

    public function testRefusesToSaveADefaultMachineThatAnotherSaveStoredFirst(): void
    {
        $repository = static::repository();
        $first = $repository->get();
        $second = $repository->get();
        $repository->save($first);

        $this->expectException(ConcurrentUpdateException::class);

        $repository->save($second);
    }

    public function testRefusesToSaveAMachineWhoseRowWasResetSinceItWasLoaded(): void
    {
        $repository = static::repository();
        $repository->save(self::machineWithEveryPartFilled());
        $machine = $repository->get();
        $repository->reset();

        $this->expectException(ConcurrentUpdateException::class);

        $repository->save($machine);
    }

    public function testKeepsSavingTheSameMachineAfterItsOwnSaves(): void
    {
        $repository = static::repository();
        $machine = $repository->get();

        $machine->insertCoin(Coin::TenCents);
        $repository->save($machine);
        $machine->insertCoin(Coin::FiveCents);
        $repository->save($machine);

        self::assertSame(15, $repository->get()->wallet()->credit()->total()->cents());
    }

    public function testTheLoserOfAConflictSavesOnceItReloads(): void
    {
        $repository = static::repository();
        $first = $repository->get();
        $repository->get();
        $repository->save($first);

        $reloaded = $repository->get();
        $reloaded->insertCoin(Coin::TwentyFiveCents);
        $repository->save($reloaded);

        self::assertSame(25, $repository->get()->wallet()->credit()->total()->cents());
    }

    private static function machineWithEveryPartFilled(): VendingMachine
    {
        $water = self::product('WATER', 'Water', 65);
        $juice = self::product('JUICE', 'Juice', 100);

        return VendingMachine::restore(
            Inventory::of(Slot::of($water, 4), Slot::of($juice, 0)),
            Wallet::of(CoinCollection::of(Coin::OneHundredCents, Coin::FiveCents), CoinCollection::of(Coin::TenCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents)),
            Tray::empty()->addProduct($juice)->addProduct($water)->addCoins(CoinCollection::of(Coin::TenCents, Coin::FiveCents)),
        );
    }

    private static function product(string $selector, string $name, int $priceInCents): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString($name), Money::fromCents($priceInCents));
    }

    private static function defaultMachine(): VendingMachine
    {
        return self::getContainer()->get(DefaultVendingMachineFactory::class)->create();
    }
}
