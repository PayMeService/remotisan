<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {12/03/2023}
 * Time: {12:10}
 */

namespace PayMe\Remotisan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServerRegistration
{
    static public function register()
    {
        $uuid = Str::uuid()->toString();
        Storage::disk("local")->put("remotisan_server_guid", $uuid);
        Cache::put(implode(":", [config("remotisan.killing_key"), $uuid]), "");
    }
}
