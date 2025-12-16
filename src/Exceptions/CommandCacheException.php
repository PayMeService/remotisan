<?php

namespace PayMe\Remotisan\Exceptions;

/**
 * Exception thrown when there are issues with the commands cache file
 *
 * This includes:
 * - Invalid cache file format
 * - Failed command instantiation from cache
 * - Missing or corrupted cache data
 */
class CommandCacheException extends RemotisanException
{
    protected $message = "Failed to load command from cache";
}