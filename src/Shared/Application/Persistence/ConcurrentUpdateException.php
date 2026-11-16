<?php

declare(strict_types=1);

namespace App\Shared\Application\Persistence;

// Not a DomainException: no business rule refused the change, another one was saved first, and
// the same command run again on fresh data may well succeed.
final class ConcurrentUpdateException extends \RuntimeException
{
    public static function sinceLoaded(string $aggregate, int $loadedVersion): self
    {
        return new self(\sprintf('The %s was changed by another request since it was loaded at version %d. The request can be retried.', $aggregate, $loadedVersion));
    }
}
