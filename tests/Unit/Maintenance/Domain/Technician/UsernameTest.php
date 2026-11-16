<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Domain\Technician;

use App\Maintenance\Domain\Technician\InvalidUsernameException;
use App\Maintenance\Domain\Technician\Username;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    #[DataProvider('validUsernames')]
    public function testExposesItsValue(string $username): void
    {
        self::assertSame($username, Username::fromString($username)->value());
    }

    /** @return iterable<string, array{string}> */
    public static function validUsernames(): iterable
    {
        yield 'shortest' => ['ana'];
        yield 'longest' => [str_repeat('a', 32)];
        yield 'digits, dots, hyphens and underscores' => ['tech-1.night_shift'];
    }

    #[DataProvider('invalidUsernames')]
    public function testRejectsAnInvalidUsername(string $username): void
    {
        $this->expectException(InvalidUsernameException::class);

        Username::fromString($username);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUsernames(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['ab'];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'uppercase' => ['Ana'];
        yield 'a space' => ['ana maria'];
        yield 'surrounding whitespace' => [' ana'];
        yield 'a trailing newline' => ["ana\n"];
    }
}
