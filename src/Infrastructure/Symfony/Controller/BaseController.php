<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Controller;

use App\Infrastructure\Symfony\Messenger\Bus\InMemoryCommandBus;
use App\Infrastructure\Symfony\Messenger\Bus\InMemoryQueryBus;
use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\Query;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    public function __construct(
        private readonly InMemoryCommandBus $commandBus,
        private readonly InMemoryQueryBus $queryBus,
    ) {
    }

    protected function command(Command $command): void
    {
        $this->commandBus->commandHandler($command);
    }

    protected function query(Query $query): mixed
    {
        return $this->queryBus->query($query);
    }
}
