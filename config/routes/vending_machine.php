<?php

declare(strict_types=1);

use App\VendingMachine\Infrastructure\Controller\CollectTrayController;
use App\VendingMachine\Infrastructure\Controller\GetMachineController;
use App\VendingMachine\Infrastructure\Controller\GetTrayContentsController;
use App\VendingMachine\Infrastructure\Controller\InsertCoinController;
use App\VendingMachine\Infrastructure\Controller\ReturnCoinsController;
use App\VendingMachine\Infrastructure\Controller\SelectProductController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('get-machine', '/machine')
        ->controller(GetMachineController::class)
        ->methods(['GET'])
    ;

    $routes->add('insert-coin', '/machine/coins')
        ->controller(InsertCoinController::class)
        ->methods(['POST'])
    ;

    $routes->add('select-product', '/machine/selections/{selector}')
        ->controller(SelectProductController::class)
        ->methods(['POST'])
    ;

    $routes->add('return-coins', '/machine/coin-return')
        ->controller(ReturnCoinsController::class)
        ->methods(['POST'])
    ;

    $routes->add('get-tray-contents', '/machine/tray')
        ->controller(GetTrayContentsController::class)
        ->methods(['GET'])
    ;

    $routes->add('collect-tray', '/machine/tray/collect')
        ->controller(CollectTrayController::class)
        ->methods(['POST'])
    ;
};
