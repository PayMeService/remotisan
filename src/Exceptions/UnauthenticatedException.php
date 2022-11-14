<?php
namespace PayMe\Remotisan\Exceptions;

use Throwable;

class UnauthenticatedException extends \RuntimeException
{
    public function __construct(
        $message = "User is not authenticated",
        $code = 0,
        Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
