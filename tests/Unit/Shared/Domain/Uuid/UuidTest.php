<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Uuid;

use App\Shared\Domain\Uuid\InvalidUuidException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function uuidsOfEachVersion(): iterable
    {
        yield 'version 1' => ['c232ab00-9414-11ec-b3c8-9f6bdeced846'];
        yield 'version 4' => ['919108f7-52d1-4320-9bac-f847db4148a8'];
        yield 'version 7' => ['019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c'];
    }

    #[DataProvider('uuidsOfEachVersion')]
    public function testKeepsAUuidOfAnyVersion(string $uuid): void
    {
        self::assertSame($uuid, ExampleId::fromString($uuid)->value());
    }

    public function testKeepsTheUuidLowercased(): void
    {
        self::assertSame(
            '019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c',
            ExampleId::fromString('019A8A6E-2F4C-7B1D-9C3E-5A7F0B2D4E6C')->value(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notUuids(): iterable
    {
        yield 'empty' => [''];
        yield 'a number' => ['1'];
        yield 'without hyphens' => ['019a8a6e2f4c7b1d9c3e5a7f0b2d4e6c'];
        yield 'in braces' => ['{019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c}'];
        yield 'not hexadecimal' => ['019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6g'];
        yield 'one digit short' => ['019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6'];
        yield 'with a trailing newline' => ["019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c\n"];
        yield 'version 0' => ['019a8a6e-2f4c-0b1d-9c3e-5a7f0b2d4e6c'];
        yield 'not the RFC 4122 variant' => ['019a8a6e-2f4c-7b1d-cc3e-5a7f0b2d4e6c'];
        yield 'the nil UUID' => ['00000000-0000-0000-0000-000000000000'];
    }

    #[DataProvider('notUuids')]
    public function testRejectsWhatIsNotARfc4122Uuid(string $value): void
    {
        $this->expectException(InvalidUuidException::class);

        ExampleId::fromString($value);
    }

    public function testIsEqualToTheSameIdentifierWhateverItsCase(): void
    {
        self::assertTrue(
            ExampleId::fromString('019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c')
                ->equals(ExampleId::fromString('019A8A6E-2F4C-7B1D-9C3E-5A7F0B2D4E6C')),
        );
    }

    public function testIsNotEqualToAnotherIdentifier(): void
    {
        self::assertFalse(
            ExampleId::fromString('019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c')
                ->equals(ExampleId::fromString('919108f7-52d1-4320-9bac-f847db4148a8')),
        );
    }

    public function testIsNotEqualToAnIdentifierOfAnotherKindWithTheSameValue(): void
    {
        self::assertFalse(
            ExampleId::fromString('019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c')
                ->equals(OtherExampleId::fromString('019a8a6e-2f4c-7b1d-9c3e-5a7f0b2d4e6c')),
        );
    }
}
