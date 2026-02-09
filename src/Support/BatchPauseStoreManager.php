<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Redis\RedisManager;

class BatchPauseStoreManager
{
    /**
     * @var array<string, BatchPauseStore>
     */
    protected array $stores = [];

    public function __construct(
        protected RedisManager $redis,
        protected ConfigRepository $config,
    ) {}

    public function forQueueConnection(string $queueConnection): BatchPauseStore
    {
        $queueConfig = $this->config->get("queue.connections.{$queueConnection}", []);

        return $this->forQueueConfig(
            is_array($queueConfig) ? $queueConfig : [],
            $queueConnection,
        );
    }

    /**
     * @param  array<string, mixed>  $queueConfig
     */
    public function forQueueConfig(array $queueConfig, ?string $cacheKey = null): BatchPauseStore
    {
        $cacheKey ??= 'queue-config:'.md5(serialize($queueConfig));

        if (isset($this->stores[$cacheKey])) {
            return $this->stores[$cacheKey];
        }

        $pausable = $queueConfig['options']['pausable'] ?? [];
        $pausable = is_array($pausable) ? $pausable : [];

        $redisConnection = (string) ($queueConfig['connection'] ?? 'default');
        $redisPrefix = (string) ($pausable['redis_prefix'] ?? 'laravel-pausable-batch');
        $restoreChunkSize = (int) ($pausable['restore_chunk_size'] ?? 1000);

        if ($redisPrefix === '') {
            $redisPrefix = 'laravel-pausable-batch';
        }

        if ($restoreChunkSize < 1) {
            $restoreChunkSize = 1;
        }

        return $this->stores[$cacheKey] = new BatchPauseStore(
            $this->redis,
            $redisConnection,
            $redisPrefix,
            $restoreChunkSize,
        );
    }

    public function defaultQueueConnection(): string
    {
        return (string) $this->config->get('queue.default', 'default');
    }
}
