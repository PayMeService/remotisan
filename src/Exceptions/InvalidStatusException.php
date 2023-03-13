<?php
namespace PayMe\Remotisan\Exceptions;

class InvalidStatusException extends RemotisanException
{
    protected $message = "Invalid status provided";
    protected $code = 0;
}
