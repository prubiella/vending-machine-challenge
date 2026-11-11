<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Query\HealthCheck\HealthCheckQuery;
use App\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckController extends BaseController
{
    public function __invoke(): Response
    {
        /** @var array{php: string, symfony: string} $versions */
        $versions = $this->query(new HealthCheckQuery());

        return $this->json(['status' => 'ok', ...$versions]);
    }
}
