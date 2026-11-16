<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Bus;

use App\Shared\Application\Bus\Masking;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaskingTest extends TestCase
{
    /**
     * @return iterable<string, array{Masking, mixed, string}>
     */
    public static function maskedValues(): iterable
    {
        yield 'a secret hidden' => [Masking::Hide, 'correct horse', '***'];
        yield 'personal data kept to its first three characters' => [Masking::KeepFirstThree, 'pau@example.com', 'pau***'];
        yield 'multibyte personal data kept to its first three characters' => [Masking::KeepFirstThree, 'núria@example.com', 'núr***'];
        yield 'personal data of three characters hidden whole' => [Masking::KeepFirstThree, 'pau', '***'];
        yield 'personal data that is not a string hidden whole' => [Masking::KeepFirstThree, 12345, '***'];
    }

    #[DataProvider('maskedValues')]
    public function testMasksAValueByItsRule(Masking $masking, mixed $value, string $masked): void
    {
        self::assertSame($masked, $masking->mask($value));
    }
}
