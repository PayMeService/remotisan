<?php
/**
 * Created by PhpStorm.
 * User: omer
 * Date: 12/07/2023
 * Time: 21:45
 */

namespace PayMe\Remotisan\Events;

use PayMe\Remotisan\Models\Execution;

readonly class ExecutionFailed
{
    public function __construct(public Execution $execution, public string $message) 
    { }
}