<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\ProcessExecutor;
use PayMe\Remotisan\Remotisan;

class RemotisanTest extends Orchestra
{
    protected function setUp(): void
    {
        $this->remotisan = app()->make(Remotisan::class);
        parent::setUp();
    }

    public function testGetInstanceUuid()
    {
        $definedInstanceUuid = "abc_instance_key";
        cache()->driver("file")->forget(Remotisan::SERVER_UUID_FILE_NAME);
        cache()->driver("file")->rememberForever(Remotisan::SERVER_UUID_FILE_NAME, fn() => $definedInstanceUuid);
        $this->assertEquals($definedInstanceUuid, Remotisan::getServerUuid());

        cache()->driver("file")->forget(Remotisan::SERVER_UUID_FILE_NAME);
    }
}
