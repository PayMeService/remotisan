<?php

namespace PayMe\Remotisan\Tests\src\Models;

use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\ProcessStatuses;

class ProcessStatusesTest extends Orchestra
{
    public function testProcessStatuses()
    {
        $this->assertEquals(1, ProcessStatuses::RUNNING);
        $this->assertEquals(2, ProcessStatuses::COMPLETED);
        $this->assertEquals(3, ProcessStatuses::FAILED);
        $this->assertEquals(4, ProcessStatuses::KILLED);

        $this->assertNotContains(ProcessStatuses::RUNNING, ProcessStatuses::getNotRunningStatusesArray());
    }
}
