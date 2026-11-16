<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Command\SelectProduct;

use App\Tests\Unit\VendingMachine\Application\InMemoryVendingMachineRepository;
use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommandHandler;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Product\Product;
use PHPUnit\Framework\TestCase;

final class SelectProductCommandHandlerTest extends TestCase
{
    public function testDropsTheSelectedProductAndItsChangeInTheTray(): void
    {
        $repository = self::repositoryWithCredit(Coin::OneHundredCents);

        self::handler($repository)(new SelectProductCommand('WATER'));

        $tray = $repository->get()->tray();
        self::assertSame(['WATER'], array_map(static fn (Product $product): string => $product->selector()->value(), $tray->products()));
        self::assertTrue($tray->coins()->equals(CoinCollection::of(Coin::TwentyFiveCents, Coin::TenCents)));
    }

    public function testARefusedSelectionKeepsTheCredit(): void
    {
        $repository = self::repositoryWithCredit(Coin::OneHundredCents, Coin::OneHundredCents);

        try {
            self::handler($repository)(new SelectProductCommand('SODA'));
            self::fail('Expected the soda to be sold out.');
        } catch (SoldOutException) {
            self::assertSame(200, $repository->get()->wallet()->credit()->total()->cents());
        }
    }

    private static function repositoryWithCredit(Coin ...$coins): InMemoryVendingMachineRepository
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $machine = $repository->get();
        foreach ($coins as $coin) {
            $machine->insertCoin($coin);
        }
        $repository->save($machine);

        return $repository;
    }

    private static function handler(InMemoryVendingMachineRepository $repository): SelectProductCommandHandler
    {
        return new SelectProductCommandHandler($repository, new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents));
    }
}
