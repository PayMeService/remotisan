<?php

namespace PayMe\Remotisan\Exceptions;

use Throwable;

class ParametersLengthException extends \UnexpectedValueException {

    public function __construct(Throwable $previous = null)
    {
        $paramsLength = config("remotisan.commands.max_params_chars_length");
        $message = "Parameters length exceeded {$paramsLength} chars";
        parent::__construct($message, 0, $previous);
    }
}
