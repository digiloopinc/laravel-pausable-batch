<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Support;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Redis\RedisManager;

class BatchPauseStore
{
    public function __construct(
        protected RedisManager $redis,
        protected string $connection,
        protected string $prefix,
        protected int $restoreChunkSize,
    ) {}

    public function pause(string $batchId): void
    {
        $this->connection()->set($this->pauseKey($batchId), '1');
    }

    public function paused(string $batchId): bool
    {
        return (bool) $this->connection()->exists($this->pauseKey($batchId));
    }

    public function parkJob(string $batchId, string $queueKey, string $payload): void
    {
        $connection = $this->connection();

        $connection->sadd($this->queueIndexKey($batchId), $queueKey);
        $connection->rpush($this->pausedListKey($batchId, $queueKey), $payload);
    }

    public function resume(string $batchId): void
    {
        $connection = $this->connection();
        $queueKeys = $connection->smembers($this->queueIndexKey($batchId));

        foreach ($queueKeys as $queueKey) {
            $pausedListKey = $this->pausedListKey($batchId, (string) $queueKey);

            while (true) {
                $jobs = $connection->lrange($pausedListKey, 0, $this->restoreChunkSize - 1);

                if ($jobs === [] || $jobs === false) {
                    break;
                }

                $this->pushMany($connection, (string) $queueKey, $jobs);
                $connection->ltrim($pausedListKey, count($jobs), -1);
            }

            $connection->del($pausedListKey);
        }

        $connection->del($this->pauseKey($batchId));
        $connection->del($this->queueIndexKey($batchId));
    }

    public function cleanup(string $batchId): void
    {
        $connection = $this->connection();
        $queueKeys = $connection->smembers($this->queueIndexKey($batchId));

        foreach ($queueKeys as $queueKey) {
            $connection->del($this->pausedListKey($batchId, (string) $queueKey));
        }

        $connection->del($this->pauseKey($batchId));
        $connection->del($this->queueIndexKey($batchId));
    }

    protected function pauseKey(string $batchId): string
    {
        return "{$this->prefix}:batch:{$batchId}:paused";
    }

    protected function queueIndexKey(string $batchId): string
    {
        return "{$this->prefix}:batch:{$batchId}:queues";
    }

    protected function pausedListKey(string $batchId, string $queueKey): string
    {
        return "{$this->prefix}:batch:{$batchId}:paused_jobs:{$queueKey}";
    }

    /**
     * @param  list<string>  $jobs
     */
    protected function pushMany(Connection $connection, string $queueKey, array $jobs): void
    {
        if ($jobs === []) {
            return;
        }

        if (count($jobs) === 1) {
            $connection->rpush($queueKey, $jobs[0]);

            return;
        }

        if ($connection instanceof PhpRedisConnection) {
            $connection->command('rpush', array_merge([$queueKey], $jobs));

            return;
        }

        if ($connection instanceof PredisConnection) {
            $connection->command('rpush', array_merge([$queueKey], $jobs));

            return;
        }

        foreach ($jobs as $job) {
            $connection->rpush($queueKey, $job);
        }
    }

    protected function connection(): Connection
    {
        return $this->redis->connection($this->connection);
    }
}
