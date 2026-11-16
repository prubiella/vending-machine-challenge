<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\EventListener;

use App\Shared\Domain\DomainException;
use App\Shared\Infrastructure\Symfony\EventListener\JsonExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

final class JsonExceptionListenerTest extends TestCase
{
    public function testKeepsTheStatusAndHeadersOfHttpExceptions(): void
    {
        $response = $this->handle(new MethodNotAllowedHttpException(['GET'], 'Method not allowed'), debug: false);

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertSame('GET', $response->headers->get('Allow'));
        self::assertSame(['error' => ['code' => 405, 'message' => 'Method not allowed']], $this->body($response));
    }

    public function testTurnsAnyDomainExceptionIntoA500(): void
    {
        $exception = new class('A broken invariant') extends DomainException {};

        $response = $this->handle($exception, debug: false);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame(['error' => ['code' => 500, 'message' => 'Internal server error']], $this->body($response));
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

    public function testNamesTheFieldOfAWrongTypeWithoutTheClass(): void
    {
        $error = NotNormalizableValueException::createForUnexpectedDataType(
            'The type of the "coin" attribute for class "App\\Some\\Request" must be one of "string" ("float" given).',
            0.25,
            ['string'],
            'coin',
            true,
        );

        $response = $this->handle($this->unprocessable($error), debug: false);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(['error' => ['code' => 422, 'message' => 'The "coin" field must be a string, got float.']], $this->body($response));
    }

    public function testNamesTheMissingField(): void
    {
        $error = NotNormalizableValueException::createForUnexpectedDataType(
            'Failed to create object because the class misses the "coin" property.',
            null,
            ['string'],
            'coin',
            true,
        );

        $response = $this->handle($this->unprocessable($error), debug: false);

        self::assertSame(['error' => ['code' => 422, 'message' => 'The "coin" field is required.']], $this->body($response));
    }

    public function testReportsEveryFieldThatCannotBeBound(): void
    {
        $missing = NotNormalizableValueException::createForUnexpectedDataType('x', null, ['string'], 'username', true);
        $wrongType = NotNormalizableValueException::createForUnexpectedDataType('x', 1, ['string'], 'password', true);

        $response = $this->handle($this->unprocessable($missing, $wrongType), debug: false);

        self::assertSame(
            ['error' => ['code' => 422, 'message' => 'The "username" field is required. The "password" field must be a string, got int.']],
            $this->body($response),
        );
    }

    private function unprocessable(NotNormalizableValueException ...$errors): UnprocessableEntityHttpException
    {
        return new UnprocessableEntityHttpException('serializer message', new PartialDenormalizationException([], array_values($errors)));
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
