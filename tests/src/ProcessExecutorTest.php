<?php

namespace PayMe\Remotisan\Tests\src;

use PayMe\Remotisan\ProcessExecutor;

class ProcessExecutorTest extends TestCase
{

    protected ProcessExecutor $processExecutor;
    protected function setUp(): void
    {
        parent::setUp();

        $this->processExecutor = new ProcessExecutor();
    }

    public function testEscapesParams()
    {
        $this->assertEquals(
            [
                0 => "firstArg",
                1 => "SecondArg",
                2 => "argWith=equalsSign",
                3 => "--emptyOpt=1",
                4 => "--firstOpt=first-value",
                5 => "--second=sec val",
                6 => [
                    "--arr='1'",
                    "--arr='2'",
                ],
                7 => ";",
                8 => "php",
                9 => "artisan",
                10 => "--version=1",
                11 => "||",
                12 => "php",
                13 => "artisan",
                14 => "migrate:status",
            ],
            $this->processExecutor->compileCmdAsEscapedArray('firstArg SecondArg argWith=equalsSign --emptyOpt --firstOpt=first-value --second="sec val" --arr=1 --arr=2 ; php artisan --version || php artisan migrate:status')
        );
    }
}
