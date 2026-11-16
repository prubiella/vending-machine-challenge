<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\Inventory;
use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\MachineId;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\Product\ProductName;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Tray\Tray;
use App\VendingMachine\Domain\VendingMachine;
use App\VendingMachine\Domain\VendingMachineRepository;
use App\VendingMachine\Domain\Wallet\Wallet;
use App\VendingMachine\Infrastructure\Outbox\MachineOutbox;
use App\VendingMachine\Infrastructure\Outbox\OutboxEvent;
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
    abstract protected static function repository(): VendingMachineRepository&StoredMachineResetter&MachineOutbox;

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

    public function testTheDefaultMachineHasTheConfiguredId(): void
    {
        self::assertSame(self::configuredMachineId(), static::repository()->get()->id()->value());
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

    public function testTheOutboxOfANewMachineIsEmpty(): void
    {
        self::assertSame([], static::repository()->pending());
    }

    public function testSavesTheEventsOfTheMachineInItsOutboxOldestFirst(): void
    {
        $repository = static::repository();
        self::saveASaleThenAReturn($repository);

        $pending = $repository->pending();

        self::assertSame(['product_sold', 'credit_returned'], array_map(static fn (OutboxEvent $event): string => $event->type, $pending));
        self::assertEquals(['selector' => 'WATER', 'name' => 'Water', 'price_cents' => 65, 'change' => [5 => 0, 10 => 1, 25 => 1, 100 => 0]], $pending[0]->payload);
        self::assertEquals(['coins' => [5 => 1, 10 => 0, 25 => 0, 100 => 0]], $pending[1]->payload);
    }

    public function testGivesEachEventOfTheOutboxAUuidV7AndTheTimeOfItsSave(): void
    {
        $repository = static::repository();
        $before = new \DateTimeImmutable();
        self::saveASaleThenAReturn($repository);
        $after = new \DateTimeImmutable();

        $pending = $repository->pending();

        self::assertNotSame($pending[0]->id, $pending[1]->id);
        foreach ($pending as $event) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $event->id);
            self::assertGreaterThanOrEqual($before, $event->occurredAt);
            self::assertLessThanOrEqual($after, $event->occurredAt);
        }
    }

    public function testKeepsTheEventsOfEverySaveOfTheSameMachine(): void
    {
        $repository = static::repository();
        $machine = $repository->get();

        $machine->insertCoin(Coin::TenCents);
        $machine->returnCoins();
        $repository->save($machine);
        $machine->insertCoin(Coin::FiveCents);
        $machine->returnCoins();
        $repository->save($machine);

        self::assertCount(2, $repository->pending());
    }

    public function testARefusedSaveAddsNoEventToTheOutbox(): void
    {
        $repository = static::repository();
        $repository->save(self::machineWithEveryPartFilled());
        $first = $repository->get();
        $second = $repository->get();
        $first->returnCoins();
        $repository->save($first);
        $second->returnCoins();

        try {
            $repository->save($second);
            self::fail('Expected ConcurrentUpdateException.');
        } catch (ConcurrentUpdateException) {
            self::assertCount(1, $repository->pending());
        }
    }

    public function testResettingForgetsThePendingEvents(): void
    {
        $repository = static::repository();
        $machine = $repository->get();
        $machine->insertCoin(Coin::TenCents);
        $machine->returnCoins();
        $repository->save($machine);

        $repository->reset();

        self::assertSame([], $repository->pending());
    }

    public function testRemovesTheRelayedEventsAndKeepsTheOthers(): void
    {
        $repository = static::repository();
        $machine = $repository->get();
        $machine->insertCoin(Coin::TenCents);
        $machine->returnCoins();
        $machine->insertCoin(Coin::FiveCents);
        $machine->returnCoins();
        $repository->save($machine);
        [$relayed, $left] = $repository->pending();

        $repository->remove([$relayed->id]);

        self::assertEquals([$left], $repository->pending());
    }

    public function testRemovingEventsDoesNotCountAsAChangeOfTheMachine(): void
    {
        $repository = static::repository();
        $machine = $repository->get();
        $machine->insertCoin(Coin::TenCents);
        $machine->returnCoins();
        $repository->save($machine);
        $reloaded = $repository->get();

        $repository->remove(array_map(static fn (OutboxEvent $event): string => $event->id, $repository->pending()));
        $reloaded->insertCoin(Coin::FiveCents);
        $repository->save($reloaded);

        self::assertSame(5, $repository->get()->wallet()->credit()->total()->cents());
    }

    private static function saveASaleThenAReturn(VendingMachineRepository $repository): void
    {
        $machine = $repository->get();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), self::changeMaker());
        $repository->save($machine);
        $machine = $repository->get();
        $machine->insertCoin(Coin::FiveCents);
        $machine->returnCoins();
        $repository->save($machine);
    }

    private static function changeMaker(): FewestCoinsChangeMaker
    {
        return new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents);
    }

    private static function machineWithEveryPartFilled(): VendingMachine
    {
        $water = self::product('WATER', 'Water', 65);
        $juice = self::product('JUICE', 'Juice', 100);

        return VendingMachine::restore(
            MachineId::fromString(self::configuredMachineId()),
            Inventory::of(Slot::of($water, 4), Slot::of($juice, 0)),
            Wallet::of(CoinCollection::of(Coin::OneHundredCents, Coin::FiveCents), CoinCollection::of(Coin::TenCents, Coin::TwentyFiveCents, Coin::TwentyFiveCents), CoinCollection::of(Coin::OneHundredCents, Coin::OneHundredCents)),
            Tray::empty()->addProduct($juice)->addProduct($water)->addCoins(CoinCollection::of(Coin::TenCents, Coin::FiveCents)),
        );
    }

    private static function product(string $selector, string $name, int $priceInCents): Product
    {
        return Product::of(Selector::fromString($selector), ProductName::fromString($name), Money::fromCents($priceInCents));
    }

    protected static function configuredMachineId(): string
    {
        return self::getContainer()->getParameter('vending_machine.id');
    }

    private static function defaultMachine(): VendingMachine
    {
        return self::getContainer()->get(DefaultVendingMachineFactory::class)->create();
    }
}
