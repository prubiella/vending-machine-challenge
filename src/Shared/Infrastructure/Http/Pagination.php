<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

final readonly class Pagination
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 100;

    private function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        return new self($page, $perPage);
    }

    public function offset(): int
    {
        return $this->page * $this->perPage;
    }

    public function limit(): int
    {
        return $this->perPage;
    }
}
