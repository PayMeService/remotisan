<?php
namespace PayMe\Remotisan\Exceptions;

use Throwable;

class ProcessFailedException extends \RuntimeException
{
    public function __construct(
        $message = "Process execution failed",
        $code = 0,
        Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
