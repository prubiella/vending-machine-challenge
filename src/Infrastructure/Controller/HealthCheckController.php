<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Query\HealthCheck\HealthCheckQuery;
use App\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckController extends BaseController
{
    public function __invoke(): Response
    {
        return $this->json($this->query(new HealthCheckQuery()));
    }
}
