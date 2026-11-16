<?php

declare(strict_types=1);

use App\Maintenance\Infrastructure\Controller\LoginController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('login', '/auth/login')
        ->controller(LoginController::class)
        ->methods(['POST'])
    ;
};
