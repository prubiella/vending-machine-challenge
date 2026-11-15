<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Controller;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\Query;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    protected function command(Command $command): void
    {
        $this->commandBus->dispatch($command);
    }

    protected function query(Query $query): mixed
    {
        return $this->queryBus->query($query);
    }
}
