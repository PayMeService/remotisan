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
        $this->remotisan = new Remotisan(new CommandsRepository(), new ProcessExecutor());
        parent::setUp();
    }

    public function testGetInstanceUuid()
    {
        $definedInstanceUuid = "abc_instance_key";
        cache()->driver("file")->forget(FileManager::SERVER_UUID_FILE_NAME);
        cache()->driver("file")->rememberForever(FileManager::SERVER_UUID_FILE_NAME, fn() => "abc_instance_key");
        $this->assertEquals($definedInstanceUuid, FileManager::getServerUuid());

        cache()->driver("file")->forget(FileManager::SERVER_UUID_FILE_NAME);
    }
}
