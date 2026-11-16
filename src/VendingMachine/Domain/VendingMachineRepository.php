<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain;

interface VendingMachineRepository
{
    public function get(): VendingMachine;

    public function save(VendingMachine $machine): void;
}
