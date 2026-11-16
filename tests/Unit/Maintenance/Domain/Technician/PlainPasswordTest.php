<?php

declare(strict_types=1);

namespace App\Tests\Unit\Maintenance\Domain\Technician;

use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Domain\Technician\TooShortPasswordException;
use PHPUnit\Framework\TestCase;

final class PlainPasswordTest extends TestCase
{
    public function testExposesAPasswordOfEightCharactersOrMore(): void
    {
        self::assertSame('12345678', PlainPassword::fromString('12345678')->value());
    }

    public function testCountsCharactersRatherThanBytes(): void
    {
        $this->expectException(TooShortPasswordException::class);

        PlainPassword::fromString('ñandú12');
    }

    public function testRejectsAPasswordShorterThanEightCharacters(): void
    {
        $this->expectException(TooShortPasswordException::class);

        PlainPassword::fromString('1234567');
    }

    public function testDoesNotRevealThePasswordWhenRejectingIt(): void
    {
        try {
            PlainPassword::fromString('secret');
            self::fail('A short password was accepted.');
        } catch (TooShortPasswordException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }
}
