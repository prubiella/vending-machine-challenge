<?php

declare(strict_types=1);

use App\Tests\Behat\CustomerContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

return new Config()
    ->withProfile(
        new Profile('default')
            ->withSuite(
                new Suite('customer')
                    ->withPaths('features/customer')
                    ->withContexts(CustomerContext::class)
            )
            ->withExtension(
                new Extension(SymfonyExtension::class, [
                    'bootstrap' => 'tests/bootstrap.php',
                    'kernel' => ['environment' => 'test'],
                ])
            )
    );
