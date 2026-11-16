<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\Masking;
use App\Shared\Application\Bus\SensitiveCommand;

final class LoggedCommand
{
    private const string MASK = '***';
    private const int KEPT_CHARACTERS = 3;

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
        if ($command instanceof SensitiveCommand) {
            foreach ($command->sensitiveFields() as $field => $masking) {
                if (\array_key_exists($field, $data)) {
                    $data[$field] = self::mask($data[$field], $masking);
                }
            }
        }

        return $data;
    }

    private static function mask(mixed $value, Masking $masking): string
    {
        if (Masking::KeepFirstThree === $masking && \is_string($value) && mb_strlen($value) > self::KEPT_CHARACTERS) {
            return mb_substr($value, 0, self::KEPT_CHARACTERS).self::MASK;
        }

        return self::MASK;
    }
}
