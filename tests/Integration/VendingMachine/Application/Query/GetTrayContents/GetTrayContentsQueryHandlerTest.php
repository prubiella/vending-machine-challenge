<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Application\Query\GetTrayContents;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Tests\Integration\VendingMachine\StartsWithNoStoredMachine;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use App\VendingMachine\Application\Query\GetTrayContents\TrayContentsResponse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetTrayContentsQueryHandlerTest extends KernelTestCase
{
    use StartsWithNoStoredMachine;

    public function testTheBusAnswersWithWhatTheTrayHolds(): void
    {
        $container = self::getContainer();
        $commandBus = $container->get(CommandBus::class);
        $commandBus->dispatch(new InsertCoinCommand(100));
        $commandBus->dispatch(new SelectProductCommand('WATER'));

        $response = $container->get(QueryBus::class)->query(new GetTrayContentsQuery());

        self::assertEquals(new TrayContentsResponse(['WATER'], [25, 10]), $response);
    }
}
