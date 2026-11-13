<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Coin;

use App\Shared\Domain\Money\Money;

final readonly class CoinCollection
{
    /**
     * @param array<int, positive-int> $countsByCents sorted by key, so equal collections have equal arrays
     */
    private function __construct(private array $countsByCents)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(Coin ...$coins): self
    {
        $collection = self::empty();
        foreach ($coins as $coin) {
            $collection = $collection->add($coin);
        }

        return $collection;
    }

    public function add(Coin $coin): self
    {
        $countsByCents = $this->countsByCents;
        $countsByCents[$coin->value] = ($countsByCents[$coin->value] ?? 0) + 1;
        ksort($countsByCents);

        return new self($countsByCents);
    }

    public function countOf(Coin $coin): int
    {
        return $this->countsByCents[$coin->value] ?? 0;
    }

    public function total(): Money
    {
        $cents = 0;
        foreach ($this->countsByCents as $coinCents => $count) {
            $cents += $coinCents * $count;
        }

        return Money::fromCents($cents);
    }

    public function isEmpty(): bool
    {
        return [] === $this->countsByCents;
    }

    public function equals(self $other): bool
    {
        return $this->countsByCents === $other->countsByCents;
    }
}
