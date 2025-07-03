<?php

namespace PayMe\Remotisan\Exceptions;

use PayMe\Remotisan\Contracts\ExceptionFactory;

class DefaultExceptionFactory implements ExceptionFactory
{

    /**
     * @inheritdoc
     */
    public function buildMaxParamsLengthException(int $paramsLength): \Throwable
    {
        return new \UnexpectedValueException("Parameters length exceeded {$paramsLength} chars");
    }
}
