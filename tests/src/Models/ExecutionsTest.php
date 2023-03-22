<?php

namespace PayMe\Remotisan\Tests\src\Models;

use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\ProcessStatuses;

class ExecutionsTest extends Orchestra
{
    protected $executionRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executionRecord = (new Execution)->fill([
            "job_uuid"      => "testableUuid",
            "server_uuid"   => "instanceUuid",
            "executed_at"   => "2023-03-03",
            "command"       => "migrate:status",
            "parameters"    => "",
            "user_identifier"=> "test user",
            "process_status"=> ProcessStatuses::RUNNING,
        ]);
    }

    public function testGetByUuid()
    {
        $this->assertEquals("instanceUuid", $this->executionRecord->server_uuid);
        $this->assertEquals("testableUuid", $this->executionRecord->job_uuid);
        $this->assertEquals(ProcessStatuses::RUNNING, $this->executionRecord->process_status);
    }

    public function testSetStatuses()
    {
        $this->executionRecord->markKilled(false);
        $this->assertEquals(ProcessStatuses::KILLED, $this->executionRecord->process_status);

        $this->executionRecord->markFailed(false);
        $this->assertEquals(ProcessStatuses::FAILED, $this->executionRecord->process_status);

        $this->executionRecord->markCompleted(false);
        $this->assertEquals(ProcessStatuses::COMPLETED, $this->executionRecord->process_status);
    }
}
