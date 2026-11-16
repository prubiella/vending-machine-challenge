<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use Symfony\Component\HttpFoundation\Response;

final class ReturnCoinsController extends BaseController
{
    public function __invoke(): Response
    {
        $this->command(new ReturnCoinsCommand());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
