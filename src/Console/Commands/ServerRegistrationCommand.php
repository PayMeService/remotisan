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
        Storage::disk("local")->put("remotisan_server_guid", $uuid);
        Cache::put(implode(":", [config("remotisan.kill_switch_key_prefix"), $uuid]), []);
    }
}
