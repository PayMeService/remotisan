<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {13/03/2023}
 * Time: {14:36}
 */

namespace PayMe\Remotisan\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\Remotisan;

class ProcessKillerJob implements ShouldQueue
{
    use Queueable;

    protected Remotisan $remotisan;

    public function __construct(Remotisan $remotisan)
    {
        $this->remotisan = $remotisan;
    }
    public function handle()
    {
        $cacheKey = $this->remotisan->makeCacheKey();
        $jobsUuidList = Cache::get($cacheKey);
        $killedJobs = [];

        try {
            foreach ($jobsUuidList as $k => $uuid) {
                $killedUuid = $this->remotisan->killProcess($uuid);
                if($killedUuid === $uuid) {
                    $killedJobs[] = $uuid;
                }
                usleep(500000);
            }
        } catch (RemotisanException $e) {
            // tumbled at some job.
        } finally {
            $refreshedUuids = Cache::get($cacheKey);
            $jobsToKill = array_diff($refreshedUuids, $killedJobs);
            Cache::put($cacheKey, $jobsToKill);
        }
    }
}
