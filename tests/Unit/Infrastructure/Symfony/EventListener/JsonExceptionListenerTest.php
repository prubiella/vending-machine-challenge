<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Symfony\EventListener;

use App\Infrastructure\Symfony\EventListener\JsonExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class JsonExceptionListenerTest extends TestCase
{
    public function testKeepsTheStatusAndHeadersOfHttpExceptions(): void
    {
        $response = $this->handle(new MethodNotAllowedHttpException(['GET'], 'Method not allowed'), debug: false);

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertSame('GET', $response->headers->get('Allow'));
        self::assertSame(['error' => ['code' => 405, 'message' => 'Method not allowed']], $this->body($response));
    }

    public function testTurnsAnyOtherExceptionIntoA500(): void
    {
        $response = $this->handle(new \RuntimeException('Database is down'), debug: true);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame(['error' => ['code' => 500, 'message' => 'Database is down']], $this->body($response));
    }

    public function testHidesTheMessageOfServerErrorsOutsideDebug(): void
    {
        $response = $this->handle(new \RuntimeException('Database is down'), debug: false);

        self::assertSame(['error' => ['code' => 500, 'message' => 'Internal server error']], $this->body($response));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function debugModes(): iterable
    {
        yield 'debug' => [true];
        yield 'no debug' => [false];
    }

    #[DataProvider('debugModes')]
    public function testShowsTheMessageOfClientErrors(bool $debug): void
    {
        $response = $this->handle(new NotFoundHttpException('No route found'), $debug);

        self::assertSame(['error' => ['code' => 404, 'message' => 'No route found']], $this->body($response));
    }

    private function handle(\Throwable $exception, bool $debug): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        new JsonExceptionListener($debug)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function body(Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
