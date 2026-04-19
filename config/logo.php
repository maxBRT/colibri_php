<?php

return [
    'connect_timeout' => env('LOGO_CONNECT_TIMEOUT', 5),
    'timeout' => env('LOGO_TIMEOUT', 10),
    'retry_times' => env('LOGO_RETRY_TIMES', 1),
    'retry_sleep_ms' => env('LOGO_RETRY_SLEEP_MS', 0),
    'max_size_bytes' => env('LOGO_MAX_SIZE_BYTES', 1024 * 1024),
    'user_agent' => env('LOGO_USER_AGENT', 'ColibriLogo/1.0'),
];
