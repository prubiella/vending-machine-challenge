<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ServiceMachineController extends BaseController
{
    public function __invoke(
        #[MapRequestPayload]
        ServiceMachineRequest $request,
        #[CurrentUser]
        UserInterface $technician,
    ): Response {
        $this->command(new ServiceMachineCommand(
            $technician->getUserIdentifier(),
            $request->units,
            self::countsByCents($request->coins),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<int|string, int> $countsByNotation
     *
     * @return array<int, int>
     */
    private static function countsByCents(array $countsByNotation): array
    {
        $countsByCents = [];
        foreach ($countsByNotation as $notation => $count) {
            $countsByCents[MoneyNotation::requireCoinCents((string) $notation)] = $count;
        }

        return $countsByCents;
    }
}
