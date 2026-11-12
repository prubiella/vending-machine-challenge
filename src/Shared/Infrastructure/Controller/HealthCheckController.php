<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckController extends BaseController
{
    public function __invoke(): Response
    {
        return $this->json($this->query(new HealthCheckQuery()));
    }
}
