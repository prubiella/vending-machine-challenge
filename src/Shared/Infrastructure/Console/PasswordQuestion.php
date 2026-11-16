<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Symfony\Component\Console\Question\Question;

final class PasswordQuestion
{
    // A password is never trimmed, at a prompt as in the --password option, so the spaces typed are
    // kept; only the line ending that closes the answer goes.
    public static function hidden(string $question): Question
    {
        return new Question($question)
            ->setHidden(true)
            ->setTrimmable(false)
            ->setNormalizer(static fn (mixed $answer): mixed => \is_string($answer) ? rtrim($answer, "\r\n") : $answer)
        ;
    }
}
