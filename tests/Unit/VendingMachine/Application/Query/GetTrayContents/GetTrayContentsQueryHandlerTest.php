<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Application\Query\GetTrayContents;

use App\Tests\Unit\VendingMachine\Application\VendingMachineRepositoryMother;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQueryHandler;
use App\VendingMachine\Domain\Change\FewestCoinsChangeMaker;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Product\Selector;
use PHPUnit\Framework\TestCase;

final class GetTrayContentsQueryHandlerTest extends TestCase
{
    public function testAnEmptyTrayHasNoSelectorsAndNoCoins(): void
    {
        $response = (new GetTrayContentsQueryHandler(VendingMachineRepositoryMother::withTheStatementProducts()))(new GetTrayContentsQuery());

        self::assertSame([], $response->selectors);
        self::assertSame([], $response->coins);
    }

    public function testListsTheSelectorsOfTheProductsAndTheCentsOfTheChange(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $machine = $repository->get();
        $machine->insertCoin(Coin::OneHundredCents);
        $machine->select(Selector::fromString('WATER'), new FewestCoinsChangeMaker(Coin::FiveCents, Coin::TenCents, Coin::TwentyFiveCents));
        $repository->save($machine);

        $response = (new GetTrayContentsQueryHandler($repository))(new GetTrayContentsQuery());

        self::assertSame(['WATER'], $response->selectors);
        self::assertSame([25, 10], $response->coins);
    }

    public function testListsEveryCoinFromTheLargest(): void
    {
        $repository = VendingMachineRepositoryMother::withTheStatementProducts();
        $machine = $repository->get();
        foreach ([Coin::TenCents, Coin::FiveCents, Coin::OneHundredCents, Coin::TenCents] as $coin) {
            $machine->insertCoin($coin);
        }
        $machine->returnCoins();
        $repository->save($machine);

        $response = (new GetTrayContentsQueryHandler($repository))(new GetTrayContentsQuery());

        self::assertSame([100, 10, 10, 5], $response->coins);
    }
}
