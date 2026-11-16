<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\EventListener;

use App\Shared\Application\Persistence\ConcurrentUpdateException;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\NegativeMoneyException;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Inventory\UnknownSelectorException;
use App\VendingMachine\Domain\Product\InvalidSelectorException;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Wallet\InsufficientCreditException;
use App\VendingMachine\Infrastructure\Notation\ForeignCoinException;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class JsonExceptionListenerTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{\Throwable, int}>
     */
    public static function refusals(): iterable
    {
        yield 'an unknown selector' => [UnknownSelectorException::forSelector(Selector::fromString('COFFEE')), 404];
        yield 'a sold out product' => [SoldOutException::forSelector(Selector::fromString('WATER')), 409];
        yield 'too little credit' => [InsufficientCreditException::forPrice(Money::fromCents(65), Money::fromCents(25)), 409];
        yield 'no exact change' => [NoExactChangeException::forAmount(Money::fromCents(35)), 409];
        yield 'a malformed selector' => [InvalidSelectorException::fromString('water'), 422];
        yield 'a foreign coin' => [ForeignCoinException::fromToken('0.50'), 422];
        yield 'a concurrent update' => [ConcurrentUpdateException::sinceLoaded('machine', 3), 409];
    }

    #[DataProvider('refusals')]
    public function testAnswersEachRefusalOfTheMachineWithItsStatusAndNeverLogsItAsAnError(\Throwable $refusal, int $status): void
    {
        $logs = self::captureRequestLogs();

        $response = self::answer($refusal);

        self::assertSame($status, $response->getStatusCode());
        self::assertFalse($logs->hasCriticalRecords(), 'A refusal is not worth paging');
        self::assertFalse($logs->hasErrorRecords(), 'A refusal is not an error');
    }

    public function testAnswersAConcurrentUpdateWithAMessageThatInvitesToRetry(): void
    {
        $response = self::answer(ConcurrentUpdateException::sinceLoaded('machine', 3));

        $message = self::message($response);
        self::assertStringContainsString('another request', $message);
        self::assertStringContainsString('retried', $message);
    }

    public function testAnswersABrokenInvariantWith500AndLogsItAsCritical(): void
    {
        $logs = self::captureRequestLogs();

        $response = self::answer(NegativeMoneyException::fromCents(-1));

        self::assertSame(500, $response->getStatusCode());
        self::assertTrue($logs->hasCriticalThatContains('NegativeMoneyException'));
    }

    // Reuses the booted kernel: booting again would build a new container and lose the handler
    // that captureRequestLogs() attached to its logger.
    private static function answer(\Throwable $exception): JsonResponse
    {
        $event = new ExceptionEvent(self::$kernel ?? self::bootKernel(), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);

        self::getContainer()->get(EventDispatcherInterface::class)->dispatch($event, KernelEvents::EXCEPTION);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);

        return $response;
    }

    private static function message(JsonResponse $response): string
    {
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['error']);
        self::assertIsString($body['error']['message']);

        return $body['error']['message'];
    }

    private static function captureRequestLogs(): TestHandler
    {
        self::bootKernel();
        $logger = self::getContainer()->get('monolog.logger.request');
        $logs = new TestHandler();
        $logger->pushHandler($logs);

        return $logs;
    }
}
