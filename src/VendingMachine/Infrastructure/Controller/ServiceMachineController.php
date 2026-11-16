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
            self::requireProducts($request->install),
            self::requireSelectors($request->retire),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Refuses a product whose fields are missing or of the wrong type with a 422, as for the counts.
     * A negative price is refused here too, since Money cannot hold one: the domain's own refusal
     * of it means a broken invariant (500), not a technician's typo.
     *
     * @param array<int|string, mixed> $install
     *
     * @return list<array{selector: string, name: string, priceCents: int, units: int}>
     */
    private static function requireProducts(array $install): array
    {
        $products = [];
        foreach ($install as $product) {
            if (!\is_array($product) || !\is_string($product['selector'] ?? null) || !\is_string($product['name'] ?? null)
                || !\is_int($product['priceCents'] ?? null) || $product['priceCents'] < 0 || !\is_int($product['units'] ?? null)) {
                throw new UnprocessableEntityHttpException('Give each product of "install" a "selector" and a "name" as text, "units" as a whole number and "priceCents" as a whole number of cents, zero or more.');
            }
            $products[] = ['selector' => $product['selector'], 'name' => $product['name'], 'priceCents' => $product['priceCents'], 'units' => $product['units']];
        }

        return $products;
    }

    /**
     * @param array<int|string, mixed> $retire
     *
     * @return list<string>
     */
    private static function requireSelectors(array $retire): array
    {
        $selectors = [];
        foreach ($retire as $selector) {
            if (!\is_string($selector)) {
                throw new UnprocessableEntityHttpException('Give "retire" as a list of selectors, such as ["WATER"].');
            }
            $selectors[] = $selector;
        }

        return $selectors;
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
