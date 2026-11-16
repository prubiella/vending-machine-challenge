<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Query\GetMachine\GetMachineQuery;
use Symfony\Component\HttpFoundation\Response;

final class GetMachineController extends BaseController
{
    public function __invoke(): Response
    {
        return $this->json($this->query(new GetMachineQuery()));
    }
}
