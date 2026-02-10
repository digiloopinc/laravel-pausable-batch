<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Connectors;

use Digiloop\LaravelPausableBatch\Queue\HorizonPausableRedisQueue;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Queue\Queue;
use Laravel\Horizon\Connectors\RedisConnector as HorizonRedisConnector;

class HorizonPausableRedisConnector extends HorizonRedisConnector
{
    public function __construct(
        RedisFactory $redis,
        protected BatchPauseStoreManager $pauseStores,
    ) {
        parent::__construct($redis);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): Queue
    {
        return new HorizonPausableRedisQueue(
            $this->redis,
            $this->pauseStores->forQueueConfig($config),
            $config['queue'] ?? 'default',
            $config['connection'] ?? $this->connection,
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
            $config['after_commit'] ?? null,
            $config['migration_batch_size'] ?? -1,
        );
    }
}
