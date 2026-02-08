<?php

return [
    'redis_connection' => env('PAUSABLE_BATCH_REDIS_CONNECTION', 'default'),

    'redis_prefix' => env('PAUSABLE_BATCH_REDIS_PREFIX', 'laravel-pausable-batch'),

    'restore_chunk_size' => (int) env('PAUSABLE_BATCH_RESTORE_CHUNK_SIZE', 1000),
];
