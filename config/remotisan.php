<?php

return [
    "url" => env('REMOTISAN_URL', 'remotisan'),
    "commands" => [
        "allowed" => [
            "migrate:status" => ["super", "semi"]
        ],json_decode(env('REMOTISAN_ALLOWED_COMMANDS', '{"*"}'), true)
    ],
    "logger" => [
        "path" => env('REMOTISAN_LOG_PATH', storage_path('temp/')),
    ]
];
