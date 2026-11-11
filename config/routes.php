<?php

declare(strict_types=1);

use App\Infrastructure\Controller\HealthCheckController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('health-check', '/health-check')
        ->controller(HealthCheckController::class)
        ->methods(['GET'])
    ;
};
