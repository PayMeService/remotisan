<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {24/01/2023}
 * Time: {19:04}
 */

namespace PayMe\Remotisan\Exceptions;

class RemotisanException extends \RuntimeException
{
    protected $message = 'Remotisan Generic Exception';
    protected $code = 500;
    public function __construct(
        $message = null,
        $code = null,
        \Throwable $previous = null
    )
    {
        $message = $message ?? $this->message;
        $code = $code ?? $this->code;
        parent::__construct($message, $code, $previous);
    }
}
