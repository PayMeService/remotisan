<?php
namespace PayMe\Remotisan\Exceptions;

class RecordNotFoundException extends RemotisanException
{
    protected $message = "Not Found";
    protected $code = 404;
}
