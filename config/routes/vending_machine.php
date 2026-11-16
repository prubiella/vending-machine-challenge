<?php

declare(strict_types=1);

use App\VendingMachine\Infrastructure\Controller\GetMachineController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('get-machine', '/machine')
        ->controller(GetMachineController::class)
        ->methods(['GET'])
    ;
};
