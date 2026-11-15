<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Range;

use App\Shared\Infrastructure\Range\IntervalMerger;
use PHPUnit\Framework\TestCase;

final class IntervalMergerTest extends TestCase
{
    public function testMergesOverlappingIntervals(): void
    {
        self::assertSame([[1, 6]], new IntervalMerger()->run([[1, 3], [2, 6]]));
    }

    public function testKeepsSeparateIntervalsApart(): void
    {
        self::assertSame([[1, 2], [4, 5]], new IntervalMerger()->run([[4, 5], [1, 2]]));
    }

    public function testAbsorbsAnIntervalInsideAnother(): void
    {
        self::assertSame([[1, 10]], new IntervalMerger()->run([[1, 10], [2, 3]]));
    }

    public function testMergesIntervalsThatTouch(): void
    {
        self::assertSame([[1, 4]], new IntervalMerger()->run([[1, 2], [2, 4]]));
    }
}
