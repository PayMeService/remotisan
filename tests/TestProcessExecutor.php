<?php

namespace PayMe\Remotisan\Tests;

use PayMe\Remotisan\ProcessExecutor;

class TestProcessExecutor extends TestCase
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
            "'firstArg' 'SecondArg' argWith='equalsSign' --emptyOpt='1' --firstOpt='first-value' --second='sec val' --arr='1' --arr='2' ';' 'php' 'artisan' --version='1' '||' 'php' 'artisan' 'migrate:status'",
            $this->processExecutor->escapeParamsString('firstArg SecondArg argWith=equalsSign --emptyOpt --firstOpt=first-value --second="sec val" --arr=1 --arr=2 ; php artisan --version || php artisan migrate:status')
        );
    }
}