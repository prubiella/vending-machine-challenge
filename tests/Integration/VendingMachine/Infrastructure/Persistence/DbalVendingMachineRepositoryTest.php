<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\Shared\Domain\Money\Money;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\Wallet\Wallet;
use App\VendingMachine\Infrastructure\Persistence\DbalVendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\DefaultVendingMachineFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalVendingMachineRepositoryTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testGivesTheDefaultMachineWhenNoneIsStored(): void
    {
        self::assertEquals(self::defaultMachine(), self::repository()->get());
    }

    public function testGivesBackEveryPartOfTheMachineItSaved(): void
    {
        $repository = self::repository();
        $machine = self::machineWithEveryPartFilled();

        $repository->save($machine);

        self::assertEquals($machine, $repository->get());
    }

    public function testSavingAgainReplacesTheStoredMachine(): void
    {
        $repository = self::repository();
        $repository->save(self::machineWithEveryPartFilled());
        $machine = $repository->get();

        $machine->collectTray();
        $machine->insertCoin(Coin::FiveCents);
        $repository->save($machine);

        self::assertEquals($machine, $repository->get());
    }

    public function testAChangeIsNotStoredUntilTheMachineIsSaved(): void
    {
        $repository = self::repository();

        $repository->get()->insertCoin(Coin::OneHundredCents);

        self::assertEquals(self::defaultMachine(), $repository->get());
    }

    public function testResettingPutsTheDefaultMachineBack(): void
    {
        $repository = self::repository();
        $repository->save(self::machineWithEveryPartFilled());

        $repository->reset();

        self::assertEquals(self::defaultMachine(), $repository->get());
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

    private static function repository(): DbalVendingMachineRepository
    {
        return self::getContainer()->get(DbalVendingMachineRepository::class);
    }
}
