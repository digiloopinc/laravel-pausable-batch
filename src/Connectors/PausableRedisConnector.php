<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Connectors;

use Digiloop\LaravelPausableBatch\Queue\PausableRedisQueue;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Queue\Queue;

class PausableRedisConnector extends RedisConnector
{
    public function __construct($redis, protected BatchPauseStore $pauseStore)
    {
        parent::__construct($redis);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): Queue
    {
        return new PausableRedisQueue(
            $this->redis,
            $config['queue'] ?? 'default',
            $config['connection'] ?? $this->connection,
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
            $config['after_commit'] ?? null,
            $config['migration_batch_size'] ?? -1,
            $this->pauseStore,
        );
    }
}
