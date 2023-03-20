<?php

namespace PayMe\Remotisan\Tests\src\Models;

use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\Models\Executions;
use PayMe\Remotisan\ProcessStatuses;

class AuditTest extends Orchestra
{
    protected $auditRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditRecord = (new Executions)->fill([
            "pid"           => 123123123,
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
        $this->assertEquals("instanceUuid", $this->auditRecord->server_uuid);
        $this->assertEquals("testableUuid", $this->auditRecord->job_uuid);
        $this->assertEquals(123123123, $this->auditRecord->pid);
        $this->assertEquals(ProcessStatuses::RUNNING, $this->auditRecord->process_status);
    }

    public function testSetStatuses()
    {
        $this->auditRecord->markKilled(false);
        $this->assertEquals(ProcessStatuses::KILLED, $this->auditRecord->process_status);

        $this->auditRecord->markFailed(false);
        $this->assertEquals(ProcessStatuses::FAILED, $this->auditRecord->process_status);

        $this->auditRecord->markCompleted(false);
        $this->assertEquals(ProcessStatuses::COMPLETED, $this->auditRecord->process_status);
    }
}
