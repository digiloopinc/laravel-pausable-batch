<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Queue\Concerns;

use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Illuminate\Queue\Jobs\RedisJob;

trait PausesBatchJobs
{
    protected BatchPauseStore $pauseStore;

    public function pop($queue = null, $index = 0)
    {
        while (true) {
            $result = parent::pop($queue, $index);

            if (! $result instanceof RedisJob) {
                return $result;
            }

            $batchId = $this->extractBatchId($result->getRawBody());

            if ($batchId === null || ! $this->pauseStore->paused($batchId)) {
                return $result;
            }

            $this->pauseStore->parkJob(
                $batchId,
                $this->getQueue($result->getQueue()),
                $result->getRawBody(),
            );

            $this->deleteReserved($result->getQueue(), $result);
        }
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

        // @todo: Consider handling encrypted commands.
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
