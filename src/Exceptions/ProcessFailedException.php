<?php
namespace PayMe\Remotisan\Exceptions;

class ProcessFailedException extends RemotisanException
{
    protected $message = "Process execution failed";
    protected $code = 0;
}
