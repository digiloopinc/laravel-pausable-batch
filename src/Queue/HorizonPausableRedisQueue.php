<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Queue;

use Digiloop\LaravelPausableBatch\Queue\Concerns\PausesBatchJobs;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Laravel\Horizon\RedisQueue as HorizonRedisQueue;

class HorizonPausableRedisQueue extends HorizonRedisQueue
{
    use PausesBatchJobs;

    public function __construct(
        $redis,
        BatchPauseStore $pauseStore,
        $default = 'default',
        $connection = null,
        $retryAfter = 60,
        $blockFor = null,
        $dispatchAfterCommit = false,
        $migrationBatchSize = -1,
    ) {
        parent::__construct(
            $redis,
            $default,
            $connection,
            $retryAfter,
            $blockFor,
            $dispatchAfterCommit,
            $migrationBatchSize,
        );

        $this->pauseStore = $pauseStore;
    }
}
