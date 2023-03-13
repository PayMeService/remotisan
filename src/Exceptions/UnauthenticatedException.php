<?php
namespace PayMe\Remotisan\Exceptions;

class UnauthenticatedException extends RemotisanException
{
    protected $message = "User is not authenticated";
    protected $code = 401;
}
