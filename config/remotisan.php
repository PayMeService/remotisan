<?php

return [
    "url" => env('REMOTISAN_URL', 'remotisan'),
    "commands" => [
        "allowed" => array_merge([
        ], json_decode(env('REMOTISAN_ALLOWED_COMMANDS', '[]'), true)),
    ],
    "logger" => [
        "path" => env('REMOTISAN_LOG_PATH', storage_path('temp')),
    ],
    "history" => [
        "max_records"  => 50,
        "should-scope" => false,
    ],
    "kill_switch_key_prefix"    => "remotisan:killing",
    "allow_process_kill"        => true,
    "super_users"               => [],
    "max_params_chars_length" => 1000,
];
