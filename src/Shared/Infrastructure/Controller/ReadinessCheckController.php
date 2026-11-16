<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Shared\Application\Query\HealthCheck\HealthCheckQuery;
use App\Shared\Application\Query\HealthCheck\HealthCheckResponse;
use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

final class ReadinessCheckController extends BaseController
{
    public function __invoke(): Response
    {
        $health = $this->query(new HealthCheckQuery());
        \assert($health instanceof HealthCheckResponse);

        $ready = !\in_array(HealthCheckResponse::KO, $health->checks, true);

        return $this->json($health, $ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
