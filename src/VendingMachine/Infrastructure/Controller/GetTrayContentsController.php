<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Query\GetTrayContents\GetTrayContentsQuery;
use Symfony\Component\HttpFoundation\Response;

final class GetTrayContentsController extends BaseController
{
    public function __invoke(): Response
    {
        return $this->json($this->query(new GetTrayContentsQuery()));
    }
}
