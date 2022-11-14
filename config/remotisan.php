<?php

return [
    "url" => env('REMOTISAN_URL', 'remotisan'),
    "allowance_rules" => [
        "roles" => ["superadmin"]
    ],
    "commands" => [
        "allowed" => [
            "migrate:status" => ["roles" => []],
            "migrate" => ["roles" => []],
        ],json_decode(env('REMOTISAN_ALLOWED_COMMANDS', '{"*"}'), true)
    ],
    "logger" => [
        "path" => env('REMOTISAN_LOG_PATH', storage_path('temp/')),
    ],
    "authentication_exception_class" => \PayMe\Remotisan\Exceptions\UnauthenticatedException::class
];

