<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Uuid;

use App\Shared\Infrastructure\Uuid\SymfonyUuidGenerator;
use PHPUnit\Framework\TestCase;

final class SymfonyUuidGeneratorTest extends TestCase
{
    public function testGeneratesALowercasedUuidVersion7(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            new SymfonyUuidGenerator()->generate(),
        );
    }

    public function testEachUuidSortsAfterThePreviousOne(): void
    {
        $generator = new SymfonyUuidGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertGreaterThan(0, strcmp($second, $first));
    }
}
