<?php

use Illuminate\Container\Container;

return [
    "url" => env('REMOTISAN_URL', 'remotisan'),
    "commands" => [
        "allowed" => array_merge([
        ], json_decode(env('REMOTISAN_ALLOWED_COMMANDS', '[]'), true)),
    ],
    "logger" => [
        "path" => env('REMOTISAN_LOG_PATH', storage_path('temp/')),
    ],
    "show_history_records_num"  => 50,
    "kill_switch_key_prefix"    => "remotisan:killing",
    "allow_process_kill"        => true,
    "super_users"               => []
];
