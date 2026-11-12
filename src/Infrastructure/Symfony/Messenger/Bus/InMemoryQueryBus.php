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
        /** @var HandledStamp[] $stamps */
        $stamps = $this->queryBus->dispatch($query)->all(HandledStamp::class);

        if (1 !== \count($stamps)) {
            throw new \LogicException(\sprintf('Query "%s" must be handled by exactly one handler, %d handled it.', $query::class, \count($stamps)));
        }

        return $stamps[0]->getResult();
    }
}
