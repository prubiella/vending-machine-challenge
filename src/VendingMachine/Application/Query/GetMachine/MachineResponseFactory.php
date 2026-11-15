<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachine;

use App\VendingMachine\Domain\Inventory\Slot;
use App\VendingMachine\Domain\VendingMachine;

final readonly class MachineResponseFactory
{
    public function fromMachine(VendingMachine $machine): MachineResponse
    {
        return new MachineResponse(
            array_map(self::productOf(...), $machine->inventory()->slots()),
            $machine->wallet()->credit()->total()->cents(),
        );
    }

    private static function productOf(Slot $slot): ProductResponse
    {
        $product = $slot->product();

        return new ProductResponse(
            $product->selector()->value(),
            $product->name()->value(),
            $product->price()->cents(),
            !$slot->isEmpty(),
        );
    }
}
