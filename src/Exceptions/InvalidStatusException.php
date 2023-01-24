<?php
namespace PayMe\Remotisan\Exceptions;

use Throwable;

class InvalidStatusException extends \RuntimeException
{
    public function __construct(
        $message = "Invalid status provided",
        $code = 0,
        Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
