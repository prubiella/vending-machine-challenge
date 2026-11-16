<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Outbox;

use App\Shared\Application\Uuid\UuidGenerator;
use App\VendingMachine\Domain\Event\CreditReturned;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Event\ProductInstalled;
use App\VendingMachine\Domain\Event\ProductRetired;
use App\VendingMachine\Domain\Event\ProductSold;
use App\VendingMachine\Domain\Event\StockRunningLow;
use App\VendingMachine\Domain\Event\VendingMachineEvent;
use App\VendingMachine\Domain\VendingMachine;
use Psr\Clock\ClockInterface;

/**
 * Takes the events a machine recorded and gives each the id and the time the domain leaves out, as
 * the repository saves the machine (ADR 20).
 */
final readonly class OutboxEventFactory
{
    public const string STOCK_RUNNING_LOW = 'stock_running_low';

    public function __construct(
        private UuidGenerator $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<OutboxEvent>
     */
    public function releasedFrom(VendingMachine $machine): array
    {
        return array_map($this->outboxEvent(...), $machine->releaseEvents());
    }

    private function outboxEvent(VendingMachineEvent $event): OutboxEvent
    {
        [$type, $payload] = self::typeAndPayload($event);

        return new OutboxEvent($this->uuidGenerator->generate(), $type, $this->clock->now(), $payload);
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private static function typeAndPayload(VendingMachineEvent $event): array
    {
        return match (true) {
            $event instanceof ProductSold => ['product_sold', [
                'selector' => $event->product()->selector()->value(),
                'name' => $event->product()->name()->value(),
                'price_cents' => $event->product()->price()->cents(),
                'change' => $event->change()->countsByCents(),
            ]],
            $event instanceof CreditReturned => ['credit_returned', [
                'coins' => $event->coins()->countsByCents(),
            ]],
            $event instanceof MachineServiced => ['machine_serviced', [
                'technician_id' => $event->technicianId()->value(),
                'units' => $event->unitsBySelector(),
                'coins' => $event->coins()->countsByCents(),
                'collected_from_cash_box' => $event->collectedFromCashBox()->countsByCents(),
            ]],
            $event instanceof ProductInstalled => ['product_installed', [
                'technician_id' => $event->technicianId()->value(),
                'selector' => $event->product()->selector()->value(),
                'name' => $event->product()->name()->value(),
                'price_cents' => $event->product()->price()->cents(),
                'units' => $event->units(),
            ]],
            $event instanceof ProductRetired => ['product_retired', [
                'technician_id' => $event->technicianId()->value(),
                'selector' => $event->product()->selector()->value(),
                'name' => $event->product()->name()->value(),
                'price_cents' => $event->product()->price()->cents(),
                'units_removed' => $event->unitsRemoved(),
            ]],
            $event instanceof StockRunningLow => [self::STOCK_RUNNING_LOW, [
                'selector' => $event->selector()->value(),
                'units_left' => $event->unitsLeft(),
            ]],
            default => throw new \LogicException(\sprintf('The outbox has no type for %s.', $event::class)),
        };
    }
}
