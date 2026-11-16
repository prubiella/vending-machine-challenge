<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class InsertCoinController extends BaseController
{
    public function __invoke(#[MapRequestPayload] InsertCoinRequest $request): Response
    {
        $this->command(new InsertCoinCommand(MoneyNotation::requireCoinCents($request->coin)));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
