<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Symfony\EventListener;

use App\Shared\Domain\Money\Money;
use App\Shared\Infrastructure\Symfony\EventListener\JsonExceptionListener;
use App\VendingMachine\Domain\Change\NoExactChangeException;
use App\VendingMachine\Domain\Inventory\NegativeUnitsException;
use App\VendingMachine\Domain\Inventory\SoldOutException;
use App\VendingMachine\Domain\Inventory\UnknownSelectorException;
use App\VendingMachine\Domain\Product\InvalidSelectorException;
use App\VendingMachine\Domain\Product\Selector;
use App\VendingMachine\Domain\Wallet\InsufficientCreditException;
use App\VendingMachine\Infrastructure\Notation\ForeignCoinException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
        yield 'any other business rule' => [NegativeUnitsException::fromUnits(-1), 400];
    }

    #[DataProvider('refusals')]
    public function testAnswersEachRefusalOfTheMachineWithItsStatus(\Throwable $refusal, int $status): void
    {
        $event = new ExceptionEvent(self::bootKernel(), new Request(), HttpKernelInterface::MAIN_REQUEST, $refusal);

        self::getContainer()->get(JsonExceptionListener::class)($event);

        self::assertSame($status, $event->getResponse()?->getStatusCode());
    }
}
