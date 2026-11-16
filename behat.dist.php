<?php

declare(strict_types=1);

use App\Tests\Behat\CustomerContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\Config\TesterOptions;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

return new Config()
    ->withProfile(
        new Profile('default')
            // An undefined or pending step fails the run: without it Behat exits 0 and the hook passes.
            ->withTesterOptions(new TesterOptions()->withStrictResultInterpretation())
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
