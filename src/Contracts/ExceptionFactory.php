<?php

namespace PayMe\Remotisan\Contracts;

use Throwable;

interface ExceptionFactory
{
    /**
     * Build error response for max params length failures
     *
     * @param int $paramsLength
     * @return Throwable
     */
    public function buildMaxParamsLengthException(int $paramsLength): Throwable;
}
