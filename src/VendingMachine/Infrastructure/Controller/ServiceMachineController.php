<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Controller;

use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Infrastructure\Notation\MoneyNotation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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
        $units = self::requireWholeCounts('units', $request->units);
        $coinsByNotation = self::requireWholeCounts('coins', $request->coins);

        $this->command(new ServiceMachineCommand(
            $technician->getUserIdentifier(),
            $units,
            self::rekeyByCoinCents($coinsByNotation),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Refuses a count that is not a whole number with a 422: the payload's arrays are typed by PHPDoc
     * only, so the serializer does not check their values.
     *
     * @param array<int|string, mixed> $counts
     *
     * @return array<int|string, int>
     */
    private static function requireWholeCounts(string $field, array $counts): array
    {
        foreach ($counts as $key => $count) {
            if (!\is_int($count)) {
                throw new UnprocessableEntityHttpException(\sprintf('Give each count of "%s" as a whole number, got %s for %s.', $field, get_debug_type($count), $key));
            }
        }

        /* @var array<int|string, int> $counts */
        return $counts;
    }

    /**
     * Keys each count by its coin's cents, refusing a notation that is not a coin the machine takes.
     *
     * @param array<int|string, int> $countsByNotation
     *
     * @return array<int, int>
     */
    private static function rekeyByCoinCents(array $countsByNotation): array
    {
        $countsByCents = [];
        foreach ($countsByNotation as $notation => $count) {
            $countsByCents[MoneyNotation::requireCoinCents((string) $notation)] = $count;
        }

        return $countsByCents;
    }
}
