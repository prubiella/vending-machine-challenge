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

    /**
     * @param array<int, int> $countsByCents
     */
    public static function fromCounts(array $countsByCents): self
    {
        $positiveCounts = [];
        foreach ($countsByCents as $cents => $count) {
            $coin = Coin::fromCents($cents);
            if ($count < 0) {
                throw NegativeCoinCountException::forCoin($coin, $count);
            }
            if ($count > 0) {
                $positiveCounts[$coin->value] = $count;
            }
        }
        ksort($positiveCounts);

        return new self($positiveCounts);
    }

    /**
     * @return array<int, int> every coin included, from the smallest
     */
    public function countsByCents(): array
    {
        $countsByCents = [];
        foreach (Coin::cases() as $coin) {
            $countsByCents[$coin->value] = $this->countOf($coin);
        }

        return $countsByCents;
    }

    public function add(Coin $coin): self
    {
        $countsByCents = $this->countsByCents;
        $countsByCents[$coin->value] = ($countsByCents[$coin->value] ?? 0) + 1;
        ksort($countsByCents);

        return new self($countsByCents);
    }

    public function remove(Coin $coin): self
    {
        $countsByCents = $this->countsByCents;
        $count = $countsByCents[$coin->value] ?? throw CoinNotInCollectionException::forCoin($coin);

        if (1 === $count) {
            unset($countsByCents[$coin->value]);
        } else {
            $countsByCents[$coin->value] = $count - 1;
        }

        return new self($countsByCents);
    }

    public function merge(self $other): self
    {
        $countsByCents = $this->countsByCents;
        foreach ($other->countsByCents as $coinCents => $count) {
            $countsByCents[$coinCents] = ($countsByCents[$coinCents] ?? 0) + $count;
        }
        ksort($countsByCents);

        return new self($countsByCents);
    }

    public function subtract(self $other): self
    {
        $countsByCents = $this->countsByCents;
        foreach ($other->countsByCents as $coinCents => $count) {
            $remaining = ($countsByCents[$coinCents] ?? 0) - $count;
            if ($remaining < 0) {
                throw CoinNotInCollectionException::forCoin(Coin::from($coinCents));
            }
            if (0 === $remaining) {
                unset($countsByCents[$coinCents]);
            } else {
                $countsByCents[$coinCents] = $remaining;
            }
        }

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
