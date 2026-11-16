<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Monolog;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

// Symfony logs a console command with its whole input (when it fails, and how it exited), so a
// --password option would reach the log in clear. It is logged as `***`, as the command bus hides a
// password.
#[AsMonologProcessor]
final class PasswordOptionMaskingProcessor
{
    private const string PASSWORD_OPTION = <<<'REGEX'
        /--password(?:=|\s+)(?:'(?:[^']|'\\'')*'|"(?:[^"\\]|\\.)*"|\S+)/
        REGEX;

    public function __invoke(LogRecord $record): LogRecord
    {
        $command = $record->context['command'] ?? null;
        if (!\is_string($command)) {
            return $record;
        }

        $masked = preg_replace(self::PASSWORD_OPTION, '--password=***', $command) ?? '--password=***';

        return $record->with(context: [...$record->context, 'command' => $masked]);
    }
}
