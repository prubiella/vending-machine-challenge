<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Application\Command\CreateTechnician;

use App\Maintenance\Application\Command\CreateTechnician\CreateTechnicianCommand;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\LoggingCommandMiddleware;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class CreateTechnicianCommandTest extends TestCase
{
    public function testTheCommandBusLogsTheUsernameAndTheEmailMaskedAndNeverThePassword(): void
    {
        $logs = new TestHandler();
        $bus = new MessageBus([
            new LoggingCommandMiddleware(new Logger('test', [$logs])),
            new HandleMessageMiddleware(new HandlersLocator([CreateTechnicianCommand::class => [static function (): void {
            }]])),
        ]);

        $bus->dispatch(new CreateTechnicianCommand('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', 'ana.tech', 'ana@example.com', 'correct-horse'));

        $record = $logs->getRecords()[0] ?? self::fail('The command was not logged.');
        self::assertSame(
            ['id' => '0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', 'username' => 'ana***', 'email' => 'ana***', 'password' => '***'],
            $record->context['data'] ?? null,
        );
        self::assertStringNotContainsString('correct-horse', json_encode($record->toArray(), \JSON_THROW_ON_ERROR));
    }
}
