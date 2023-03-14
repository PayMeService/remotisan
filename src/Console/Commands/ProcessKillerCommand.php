<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {13/03/2023}
 * Time: {14:36}
 */

namespace PayMe\Remotisan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\Remotisan;

class ProcessKillerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "remotisan:process_killer";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remotisan's job killer";

    protected Remotisan $remotisan;

    public function __construct(Remotisan $remotisan)
    {
        $this->remotisan = $remotisan;
        parent::__construct();
    }
    public function handle()
    {
        $cacheKey = $this->remotisan->makeCacheKey();
        $jobsUuidList = Cache::get($cacheKey) ?? [];
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
            $refreshedUuids = collect(Cache::get($cacheKey) ?? []);
            Cache::put($cacheKey, $refreshedUuids->diff($killedJobs)->all());
        }
    }
}
