<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Bus;

use App\Application\Bus\Query;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class InMemoryQueryBus
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    public function query(Query $query): mixed
    {
        /** @var HandledStamp $stamp */
        $stamp = $this->queryBus->dispatch($query)->last(HandledStamp::class);

        return $stamp->getResult();
    }
}
