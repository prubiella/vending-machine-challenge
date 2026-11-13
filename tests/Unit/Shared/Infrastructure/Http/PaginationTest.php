<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PaginationTest extends TestCase
{
    public function testUsesTheDefaultsWithoutQueryParameters(): void
    {
        $pagination = Pagination::fromRequest(new Request());

        self::assertSame(1, $pagination->page);
        self::assertSame(Pagination::DEFAULT_PER_PAGE, $pagination->limit());
    }

    public function testCapsThePageSize(): void
    {
        $pagination = Pagination::fromRequest(new Request(['per_page' => '500']));

        self::assertSame(Pagination::MAX_PER_PAGE, $pagination->limit());
    }

    public function testNeverGoesBelowTheFirstPage(): void
    {
        $pagination = Pagination::fromRequest(new Request(['page' => '-3']));

        self::assertSame(1, $pagination->page);
    }
}
