<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Change;

use App\Shared\Domain\Money\Money;
use App\VendingMachine\Domain\Coin\Coin;
use App\VendingMachine\Domain\Coin\CoinCollection;

final readonly class FewestCoinsChangeMaker implements ChangeMaker
{
    public function makeChange(Money $amount, CoinCollection $available): CoinCollection
    {
        $target = $amount->cents();
        /** @var array<int, list<Coin>> $fewestCoinsByCents */
        $fewestCoinsByCents = [0 => []];

        foreach (Coin::cases() as $coin) {
            $withThisCoin = $fewestCoinsByCents;
            foreach ($fewestCoinsByCents as $cents => $coins) {
                $maxCount = min($available->countOf($coin), intdiv($target - $cents, $coin->value));
                for ($count = 1; $count <= $maxCount; ++$count) {
                    $reached = $cents + $count * $coin->value;
                    $candidate = [...$coins, ...array_fill(0, $count, $coin)];
                    if (!isset($withThisCoin[$reached]) || \count($candidate) < \count($withThisCoin[$reached])) {
                        $withThisCoin[$reached] = $candidate;
                    }
                }
            }
            $fewestCoinsByCents = $withThisCoin;
        }

        $change = $fewestCoinsByCents[$target] ?? throw NoExactChangeException::forAmount($amount);

        return CoinCollection::of(...$change);
    }
}
