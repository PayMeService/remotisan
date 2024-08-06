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
     * @param   string  $uuid
     */
    public static function addKillInstruction(string $uuid): void
    {
        static::storeKillUuids(static::getKillUuids()->push($uuid)->unique());
    }

    /**
     * Remove kill signal
     *
     * @param   string  $uuid
     * @return  string
     */
    public static function removeKillInstruction(string $uuid): string
    {
        $valuesCollection = static::getKillUuids();

        if (false !== ($key = $valuesCollection->search($uuid, true))) {
            $valuesCollection->forget($key);
            static::storeKillUuids($valuesCollection);
        }

        return $uuid;
    }

    /**
     * Check whether provided UUID is in killing list for THIS server.
     * and return boolean.
     *
     * @param   string $uuid
     * @return  bool
     */
    public static function hasKillInstruction(string $uuid): bool
    {
        return static::getKillUuids()->contains($uuid);
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
        return implode(":", [config("remotisan.kill_switch_key_prefix"), App::environment(), FileManager::getServerUuid()]);
    }
}
