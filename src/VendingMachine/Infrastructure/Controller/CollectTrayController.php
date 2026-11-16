<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Command\CollectTray\CollectTrayCommand;
use Symfony\Component\HttpFoundation\Response;

final class CollectTrayController extends BaseController
{
    public function __invoke(): Response
    {
        $this->command(new CollectTrayCommand());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
