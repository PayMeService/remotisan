<?php

namespace PayMe\Remotisan\Tests\src;

use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\RemotisanServiceProvider;

class RemotisanTest extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            RemotisanServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
