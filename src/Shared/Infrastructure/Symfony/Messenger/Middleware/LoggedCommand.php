<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\SensitiveCommand;

final class LoggedCommand
{
    private const string MASK = '***';

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

    /**
     * @return array<mixed>
     */
    public static function data(object $command): array
    {
        $data = get_object_vars($command);
        if ($command instanceof SensitiveCommand) {
            foreach ($command->sensitiveFields() as $field) {
                if (\array_key_exists($field, $data)) {
                    $data[$field] = self::MASK;
                }
            }
        }

        return $data;
    }
}
