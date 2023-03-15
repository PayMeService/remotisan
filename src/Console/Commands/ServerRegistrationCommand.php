<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {12/03/2023}
 * Time: {12:10}
 */

namespace PayMe\Remotisan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PayMe\Remotisan\Remotisan;

class ServerRegistrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "remotisan:register-instance";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remotisan's instance registration command.";

    static public function handle()
    {
        $uuid = Str::uuid()->toString();
        Storage::disk("local")->put(Remotisan::INSTANCE_UUID_FILE_NAME, $uuid);
        Cache::put(implode(":", [config("remotisan.kill_switch_key_prefix"), env('APP_ENV', 'development'), $uuid]), []);
    }
}
