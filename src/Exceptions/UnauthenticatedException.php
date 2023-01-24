<?php
namespace PayMe\Remotisan\Exceptions;

use Throwable;

class UnauthenticatedException extends RemotisanException
{
    protected $message = "User is not authenticated";
    protected $code = 401;
}
