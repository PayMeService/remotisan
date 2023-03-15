<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\ProcessExecutor;
use PayMe\Remotisan\Remotisan;

class RemotisanTest extends Orchestra
{
    protected function setUp(): void
    {
        $this->remotisan = new Remotisan(new CommandsRepository(), new ProcessExecutor());
        parent::setUp();
    }

    public function testGetInstanceUuid()
    {
        $definedInstanceUuid = "abc_instance_key";
        cache()->driver("file")->forget(Remotisan::INSTANCE_UUID_FILE_NAME);
        cache()->driver("file")->rememberForever(Remotisan::INSTANCE_UUID_FILE_NAME, fn() => "abc_instance_key");
        $this->assertEquals($definedInstanceUuid, $this->remotisan->getInstanceUuid());

        cache()->driver("file")->forget(Remotisan::INSTANCE_UUID_FILE_NAME);
    }
}
