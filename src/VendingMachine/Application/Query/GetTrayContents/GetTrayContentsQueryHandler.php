<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetTrayContents;

use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;
use App\VendingMachine\Domain\Product\Product;
use App\VendingMachine\Domain\VendingMachineRepository;

final readonly class GetTrayContentsQueryHandler
{
    public function __construct(
        private VendingMachineRepository $repository,
    ) {
    }

    public function __invoke(GetTrayContentsQuery $query): TrayContentsResponse
    {
        $tray = $this->repository->get()->tray();

        return new TrayContentsResponse(
            array_map(static fn (Product $product): string => $product->selector()->value(), $tray->products()),
            self::centsFromTheLargestCoin($tray->coins()),
        );
    }

    /**
     * @return list<int>
     */
    private static function centsFromTheLargestCoin(CoinCollection $coins): array
    {
        $cents = [];
        foreach (array_reverse(Coin::cases()) as $coin) {
            $cents = [...$cents, ...array_fill(0, $coins->countOf($coin), $coin->value)];
        }

        return $cents;
    }
}
