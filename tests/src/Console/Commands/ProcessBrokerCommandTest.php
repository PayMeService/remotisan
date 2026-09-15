<?php

namespace PayMe\Remotisan\Tests\src\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PayMe\Remotisan\Tests\src\TestCase;

class ProcessBrokerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set("database.default", "testing");
        config()->set("database.connections.testing", [
            "driver"   => "sqlite",
            "database" => ":memory:",
            "prefix"   => "",
        ]);
    }

    /**
     * A broker spawned for an execution that cannot be found has nothing to run and nothing to
     * report status on. It has to exit as a failure rather than raise a TypeError on the typed
     * execution record property.
     *
     * @return void
     */
    public function testAnUnknownExecutionFailsTheCommandInsteadOfFatalling()
    {
        $this->artisan("remotisan:broker", ["uuid" => "no-such-uuid"])
            ->expectsOutputToContain("Execution record not found for uuid no-such-uuid")
            ->assertExitCode(1);
    }
}
