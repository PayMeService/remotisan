<?php
namespace PayMe\Remotisan\Exceptions;

use Throwable;

class InvalidStatusException extends \RuntimeException
{
    protected $message = "Invalid status provided";
    protected $code = 0;
}
