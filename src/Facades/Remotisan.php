<?php

namespace PayMe\Remotisan\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \PayMe\Remotisan\Remotisan
 */
class Remotisan extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \PayMe\Remotisan\Remotisan::class;
    }
}
