<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Support\Facades\Storage;
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
        Storage::disk("local")->put(Remotisan::INSTANCE_UUID_FILE_NAME, $definedInstanceUuid);
        $this->assertEquals($definedInstanceUuid, $this->remotisan->getInstanceUuid());

        Storage::disk("local")->delete(Remotisan::INSTANCE_UUID_FILE_NAME);
    }
}
