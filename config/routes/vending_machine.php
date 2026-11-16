<?php

declare(strict_types=1);

use App\VendingMachine\Infrastructure\Controller\GetMachineController;
use App\VendingMachine\Infrastructure\Controller\GetTrayContentsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('get-machine', '/machine')
        ->controller(GetMachineController::class)
        ->methods(['GET'])
    ;

    $routes->add('get-tray-contents', '/machine/tray')
        ->controller(GetTrayContentsController::class)
        ->methods(['GET'])
    ;
};
