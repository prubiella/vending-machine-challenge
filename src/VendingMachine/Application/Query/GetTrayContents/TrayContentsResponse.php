<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetTrayContents;

final readonly class TrayContentsResponse
{
    /**
     * @param list<string> $selectors the selector of each product, in the order they dropped
     * @param list<int>    $coins     the cents of each coin, from the largest
     */
    public function __construct(
        public array $selectors,
        public array $coins,
    ) {
    }
}
