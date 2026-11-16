<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Command\SelectProduct\SelectProductCommand;
use Symfony\Component\HttpFoundation\Response;

final class SelectProductController extends BaseController
{
    public function __invoke(string $selector): Response
    {
        $this->command(new SelectProductCommand($selector));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
