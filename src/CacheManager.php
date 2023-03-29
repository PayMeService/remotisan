<?php
namespace PayMe\Remotisan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class CacheManager
{
    /**
     * add kill signal to the cache storage.
     *
     * @param string      $uuid
     * @param string|null $killerName
     */
    public static function addKillInstruction(string $uuid, ?string $killerName): void
    {
        $killList = static::getKillUuids();
        $killList->offsetSet($uuid, [
            "name" => $killerName,
            "time" => time()
        ]);
        static::storeKillUuids($killList);
    }

    /**
     * Remove kill signal
     *
     * @param   string  $uuid
     * @return  void
     */
    public static function removeKillInstruction(string $uuid): void
    {
        $killList = static::getKillUuids();

        if ($killList->offsetExists($uuid)) {
            $killList->offsetUnset($uuid);
            static::storeKillUuids($killList);
        }
    }

    /**
     * Check whether provided UUID is in killing list for THIS server.
     * and return array.
     *
     * @param   string $uuid
     * @return  ?array
     */
    public static function getKillInstruction(string $uuid): ?array
    {
        return static::getKillUuids()->offsetExists($uuid) ? static::getKillUuids()->offsetGet($uuid) : null;
    }

    /**
     * Get Killing UUIDs from redis.
     *
     * @return  Collection
     */
    public static function getKillUuids(): Collection
    {
        return collect(Cache::get(static::makeCacheKey()) ?? []);
    }

    /**
     * Store killing UUIDs in redis.
     *
     * @param   Collection  $uuids
     * @return  void
     */
    public static function storeKillUuids(Collection $uuids): void
    {
        Cache::put(static::makeCacheKey(), $uuids->all());
    }

    /**
     * Emptying cached kill ids for THIS server.
     * Made for special cases when you want to clear the stack.
     *
     * @return  void
     */
    public static function emptyKillUuids():void
    {
        Cache::put(static::makeCacheKey(), []);
    }

    /**
     * Compose cache killing key
     *
     * @return  string
     */
    public static function makeCacheKey(): string
    {
        return implode(":", [config("remotisan.kill_switch_key_prefix"), App::environment(), Remotisan::getServerUuid()]);
    }
}
