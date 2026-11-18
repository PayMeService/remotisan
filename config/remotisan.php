<?php

return [
    "url"                    => env('REMOTISAN_URL', 'remotisan'),
    "commands"               => [
        "allowed"                 => array_merge([], json_decode(env('REMOTISAN_ALLOWED_COMMANDS', '[]'), true)),
        "max_params_chars_length" => 5000
    ],
    "logger"                 => [
        "path" => env('REMOTISAN_LOG_PATH', storage_path('temp')),
        // Bytes a single log chunk request may read. Logs are paginated, never read in full.
        "max_read_bytes"  => (int)env('REMOTISAN_MAX_READ_BYTES', 1048576),
        // Lines the viewer asks for per chunk.
        "lines_per_chunk" => (int)env('REMOTISAN_LINES_PER_CHUNK', 200),
    ],
    "history"                => [
        "max_records"  => 50,
        "should-scope" => false,
    ],
    "kill_switch_key_prefix" => "remotisan:killing",
    "interactive"            => [
        // Cache key prefix for pending prompts and (encrypted, short-lived) answers.
        // Multi-instance deployments need a shared cache store, as for kill instructions.
        "key_prefix"        => "remotisan:input",
        // Longest answer accepted, in characters (a pasted bundle or certificate fits).
        "max_answer_length" => (int)env("REMOTISAN_MAX_ANSWER_LENGTH", 65536),
    ],
    "allow_process_kill"     => true,
    "super_users"            => []
];
