<?php
namespace PayMe\Remotisan\Exceptions;

class RecordNotFoundException extends RemotisanException
{
    protected $message = "No record found.";
    protected $code = 0;
}
