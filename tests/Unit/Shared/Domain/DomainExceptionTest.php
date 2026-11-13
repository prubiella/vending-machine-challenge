<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\DomainException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testIsARuntimeErrorAndNotAProgrammingError(): void
    {
        $parent = new \ReflectionClass(DomainException::class)->getParentClass();

        self::assertNotFalse($parent);
        self::assertSame(\RuntimeException::class, $parent->getName());
    }
}
