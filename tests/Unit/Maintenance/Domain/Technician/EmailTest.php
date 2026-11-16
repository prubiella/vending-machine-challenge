<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Domain\Technician;

use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\InvalidEmailException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testExposesItsValue(): void
    {
        self::assertSame('ana@example.com', Email::fromString('ana@example.com')->value());
    }

    public function testLowercasesTheAddress(): void
    {
        self::assertSame('ana.tech@example.com', Email::fromString('Ana.Tech@Example.COM')->value());
    }

    #[DataProvider('invalidEmails')]
    public function testRejectsAnInvalidAddress(string $email): void
    {
        $this->expectException(InvalidEmailException::class);

        Email::fromString($email);
    }

    public function testDoesNotRevealTheAddressWhenRejectingIt(): void
    {
        try {
            Email::fromString('ana.example.com');
            self::fail('An invalid email was accepted.');
        } catch (InvalidEmailException $exception) {
            self::assertStringNotContainsString('ana.example.com', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['ana.example.com'];
        yield 'no domain' => ['ana@'];
        yield 'a space' => ['ana maria@example.com'];
        yield 'surrounding whitespace' => [' ana@example.com'];
        yield 'longer than 254 characters' => [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.com'];
    }
}
