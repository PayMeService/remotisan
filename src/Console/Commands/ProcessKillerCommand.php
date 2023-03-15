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
    protected $signature = "remotisan:process-killer";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remotisan's job killer";

    public function handle(Remotisan $remotisan)
    {
        $cacheKey = $remotisan->makeCacheKey();
        $jobsUuidList = Cache::get($cacheKey) ?? [];

        foreach ($jobsUuidList as $k => $uuid) {
            $remotisan->killProcess($uuid);
            usleep(500000);
        }
    }
}
