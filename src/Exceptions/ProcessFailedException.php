<?php
namespace PayMe\Remotisan\Exceptions;

use Throwable;

class ProcessFailedException extends \RuntimeException
{
    protected $message = "Process execution failed";
    protected $code = 0;
}
