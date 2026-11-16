<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use App\Shared\Infrastructure\HealthCheck\ReadinessRule;
use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

final class ReadinessCheckController extends BaseController
{
    public function __invoke(): Response
    {
        $health = $this->query(new HealthCheckQuery());
        \assert($health instanceof HealthCheckResponse);

        return $this->json($health, ReadinessRule::isReady($health) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
