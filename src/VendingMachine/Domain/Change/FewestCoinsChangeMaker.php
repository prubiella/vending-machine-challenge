<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Change;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;

final readonly class FewestCoinsChangeMaker implements ChangeMaker
{
    /** @var list<Coin> */
    private array $given;

    public function __construct(Coin ...$given)
    {
        $this->given = array_values($given);
    }

    public function makeChange(Money $amount, CoinCollection $available): CoinCollection
    {
        $fewestCoinsByCents = [0 => []];
        foreach ($this->given as $coin) {
            $fewestCoinsByCents = $this->addingCoin($coin, $available->countOf($coin), $fewestCoinsByCents, $amount->cents());
        }

        $change = $fewestCoinsByCents[$amount->cents()] ?? throw NoExactChangeException::forAmount($amount);

        return CoinCollection::of(...$change);
    }

    /**
     * @param array<int, list<Coin>> $fewestCoinsByCents
     *
     * @return array<int, list<Coin>>
     */
    private function addingCoin(Coin $coin, int $stock, array $fewestCoinsByCents, int $target): array
    {
        $withThisCoin = $fewestCoinsByCents;
        foreach ($fewestCoinsByCents as $cents => $coins) {
            $fitting = min($stock, intdiv($target - $cents, $coin->value));
            for ($count = 1; $count <= $fitting; ++$count) {
                $withThisCoin = $this->keepingFewer(
                    $withThisCoin,
                    $cents + $count * $coin->value,
                    [...$coins, ...array_fill(0, $count, $coin)],
                );
            }
        }

        return $withThisCoin;
    }

    /**
     * @param array<int, list<Coin>> $fewestCoinsByCents
     * @param list<Coin>             $candidate
     *
     * @return array<int, list<Coin>>
     */
    private function keepingFewer(array $fewestCoinsByCents, int $cents, array $candidate): array
    {
        if (!isset($fewestCoinsByCents[$cents]) || \count($candidate) < \count($fewestCoinsByCents[$cents])) {
            $fewestCoinsByCents[$cents] = $candidate;
        }

        return $fewestCoinsByCents;
    }
}
