<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\SensitiveCommand;

final class LoggedCommand
{
    public static function name(object $command): string
    {
        return new \ReflectionClass($command)->getShortName();
    }

    /**
     * @return array{command: class-string, data: array<mixed>}
     */
    public static function context(object $command): array
    {
        return ['command' => $command::class, 'data' => self::data($command)];
    }

    /**
     * @return array<mixed>
     */
    private static function data(object $command): array
    {
        $data = get_object_vars($command);
        if (!$command instanceof SensitiveCommand) {
            return $data;
        }

        return self::maskSensitiveFields($command, $data);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function maskSensitiveFields(SensitiveCommand $command, array $data): array
    {
        foreach ($command->sensitiveFields() as $field => $masking) {
            if (!\array_key_exists($field, $data)) {
                throw new \LogicException(\sprintf('%s masks the field "%s", which is not one of its public properties.', $command::class, $field));
            }
            $data[$field] = $masking->mask($data[$field]);
        }

        return $data;
    }
}
