<?php

namespace PayMe\Remotisan\Tests\src\Models;

use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\Models\Audit;
use PayMe\Remotisan\Models\ProcessStatuses;
use PayMe\Remotisan\RemotisanServiceProvider;

class AuditTest extends Orchestra
{
    protected $auditRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditRecord = (new Audit)->fill([
            "pid"           => 123123123,
            "uuid"          => "testableUuid",
            "instance_uuid" => "instanceUuid",
            "executed_at"   => "2023-03-03",
            "command"       => "migrate:status",
            "parameters"    => "",
            "user_identifier"=> "test user",
            "process_status"=> ProcessStatuses::RUNNING,
        ]);
    }

    public function testGetByUuid()
    {
        $this->assertEquals("instanceUuid", $this->auditRecord->getInstanceUuid());
        $this->assertEquals("testableUuid", $this->auditRecord->getUuid());
        $this->assertEquals(123123123, $this->auditRecord->getPid());
        $this->assertEquals(ProcessStatuses::RUNNING, $this->auditRecord->getProcessStatus());
    }

    public function testSetStatuses()
    {
        $this->auditRecord->markKilled(false);
        $this->assertEquals(ProcessStatuses::KILLED, $this->auditRecord->getProcessStatus());

        $this->auditRecord->markFailed(false);
        $this->assertEquals(ProcessStatuses::FAILED, $this->auditRecord->getProcessStatus());

        $this->auditRecord->markCompleted(false);
        $this->assertEquals(ProcessStatuses::COMPLETED, $this->auditRecord->getProcessStatus());
    }
}
