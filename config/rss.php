<?php

return [
    'connect_timeout' => env('RSS_CONNECT_TIMEOUT', 5),
    'timeout' => env('RSS_TIMEOUT', 10),
    'retry_times' => env('RSS_RETRY_TIMES', 2),
    'retry_sleep_ms' => env('RSS_RETRY_SLEEP_MS', 200),
    'user_agent' => env('RSS_USER_AGENT', 'ColibriRSS/1.0'),
];
