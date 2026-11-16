<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

final class LoggedCommand
{
    public static function name(object $command): string
    {
        return new \ReflectionClass($command)->getShortName();
    }

    /**
     * @return array{command: class-string}
     */
    public static function context(object $command): array
    {
        return ['command' => $command::class];
    }
}
