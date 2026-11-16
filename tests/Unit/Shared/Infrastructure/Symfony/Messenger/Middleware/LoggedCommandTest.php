<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Application\Bus\Masking;
use App\Shared\Application\Bus\SensitiveCommand;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\LoggedCommand;
use PHPUnit\Framework\TestCase;

final class LoggedCommandTest extends TestCase
{
    public function testRefusesToLogACommandThatMasksAFieldItDoesNotHave(): void
    {
        $command = new readonly class('correct horse') implements SensitiveCommand {
            public function __construct(
                public string $password,
            ) {
            }

            public function sensitiveFields(): array
            {
                return ['pasword' => Masking::Hide];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('%s masks the field "pasword", which is not one of its public properties.', $command::class));

        LoggedCommand::context($command);
    }
}
