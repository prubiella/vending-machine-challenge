<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Messenger\Middleware;

use App\Shared\Domain\DomainException;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\LoggingCommandMiddleware;
use App\Shared\Infrastructure\Symfony\Messenger\Middleware\UnpackExceptionMiddleware;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class LoggingCommandMiddlewareTest extends TestCase
{
    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
    }

    public function testLogsAHandledCommandWithItsNameAndDataAtInfo(): void
    {
        $this->bus(static function (): void {
        })->dispatch(new InsertCoinCommand(25));

        self::assertSame([[Level::Info, 'Command InsertCoinCommand handled.', ['command' => InsertCoinCommand::class, 'data' => ['cents' => 25]]]], $this->records());
    }

    /**
     * @return iterable<string, array{LogInCommand, array<string, string>}>
     */
    public static function sensitiveCommands(): iterable
    {
        yield 'a secret hidden, personal data kept to its first three characters' => [
            new LogInCommand('pau@example.com', 'correct horse', 'T-1'),
            ['email' => 'pau***', 'password' => '***', 'terminal' => 'T-1'],
        ];
        yield 'personal data of three characters or fewer hidden' => [
            new LogInCommand('pau', 'pw', 'T-1'),
            ['email' => '***', 'password' => '***', 'terminal' => 'T-1'],
        ];
    }

    /**
     * @param array<string, string> $loggedData
     */
    #[DataProvider('sensitiveCommands')]
    public function testLogsEachSensitiveFieldOfACommandMaskedByItsRule(LogInCommand $command, array $loggedData): void
    {
        $this->bus(static function (): void {
        })->dispatch($command);

        self::assertSame([[Level::Info, 'Command LogInCommand handled.', ['command' => LogInCommand::class, 'data' => $loggedData]]], $this->records());
    }

    public function testLogsARefusedCommandAtNoticeAndRethrowsTheRefusal(): void
    {
        $refusal = new class('The coin is not accepted.') extends DomainException {
        };

        try {
            $this->bus(static fn () => throw $refusal)->dispatch(new InsertCoinCommand(50));
            self::fail('Expected the refusal.');
        } catch (DomainException $e) {
            self::assertSame($refusal, $e);
        }

        self::assertSame([[Level::Notice, 'Command InsertCoinCommand refused: The coin is not accepted.', ['command' => InsertCoinCommand::class, 'data' => ['cents' => 50]]]], $this->records());
    }

    public function testLogsAFailedCommandAtErrorWithTheExceptionAndRethrowsIt(): void
    {
        $failure = new \RuntimeException('The database went away.');

        try {
            $this->bus(static fn () => throw $failure)->dispatch(new InsertCoinCommand(5));
            self::fail('Expected the failure.');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }

        self::assertSame([[Level::Error, 'Command InsertCoinCommand failed: The database went away.', ['command' => InsertCoinCommand::class, 'data' => ['cents' => 5], 'exception' => $failure]]], $this->records());
    }

    /**
     * @return list<array{Level, string, array<mixed>}>
     */
    private function records(): array
    {
        return array_values(array_map(static fn (LogRecord $record): array => [$record->level, $record->message, $record->context], $this->logs->getRecords()));
    }

    private function bus(\Closure $handler): MessageBus
    {
        return new MessageBus([
            new LoggingCommandMiddleware(new Logger('test', [$this->logs])),
            new UnpackExceptionMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator([InsertCoinCommand::class => [$handler], LogInCommand::class => [$handler]])),
        ]);
    }
}
