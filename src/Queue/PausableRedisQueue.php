<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Queue;

use InvalidArgumentException;
use Illuminate\Queue\RedisQueue;
use Illuminate\Queue\Jobs\RedisJob;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;

class PausableRedisQueue extends RedisQueue
{
    public function __construct(
        $redis,
        $default = 'default',
        $connection = null,
        $retryAfter = 60,
        $blockFor = null,
        $dispatchAfterCommit = false,
        $migrationBatchSize = -1,
        ?BatchPauseStore $pauseStore = null,
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

        if ($pauseStore === null) {
            throw new InvalidArgumentException('PausableRedisQueue requires a BatchPauseStore instance.');
        }

        $this->pauseStore = $pauseStore;
    }

    protected BatchPauseStore $pauseStore;

    public function pop($queue = null, $index = 0)
    {
        while (true) {
            $job = $this->popFromParent($queue, $index);

            if (! $job instanceof RedisJob) {
                return $job;
            }

            $batchId = $this->extractBatchId($job->getRawBody());

            if ($batchId === null || ! $this->pauseStore->paused($batchId)) {
                return $job;
            }

            $this->pauseStore->parkJob(
                $batchId,
                $this->getQueue($job->getQueue()),
                $job->getRawBody(),
            );

            $this->deleteReservedJob($job);
        }
    }

    protected function popFromParent($queue = null, $index = 0): mixed
    {
        return parent::pop($queue, $index);
    }

    protected function deleteReservedJob(RedisJob $job): void
    {
        $this->deleteReserved($job->getQueue(), $job);
    }

    protected function extractBatchId(string $rawPayload): ?string
    {
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return null;
        }

        $directBatchId = $payload['batchId'] ?? $payload['data']['batchId'] ?? null;

        if (is_string($directBatchId) && $directBatchId !== '') {
            return $directBatchId;
        }

        $serializedCommand = $payload['data']['command'] ?? null;

        // @todo: Consider handling encrypted commands

        if (! is_string($serializedCommand) || $serializedCommand === '') {
            return null;
        }

        $command = @unserialize($serializedCommand);

        if (! is_object($command) || ! property_exists($command, 'batchId')) {
            return null;
        }

        $batchId = $command->batchId;

        return is_string($batchId) && $batchId !== '' ? $batchId : null;
    }
}
