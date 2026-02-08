<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Bus;

use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Illuminate\Bus\Batch;

class PausableBatch extends Batch
{
    public function __construct(
        $queue,
        $repository,
        string $id,
        string $name,
        int $totalJobs,
        int $pendingJobs,
        int $failedJobs,
        array $failedJobIds,
        array $options,
        $createdAt,
        $cancelledAt,
        $finishedAt,
        protected BatchPauseStore $pauseStore,
    ) {
        parent::__construct(
            $queue,
            $repository,
            $id,
            $name,
            $totalJobs,
            $pendingJobs,
            $failedJobs,
            $failedJobIds,
            $options,
            $createdAt,
            $cancelledAt,
            $finishedAt,
        );
    }

    public function pause(): void
    {
        $this->pauseStore->pause($this->id);
    }

    public function paused(): bool
    {
        return $this->pauseStore->paused($this->id);
    }

    public function resume(): void
    {
        $this->pauseStore->resume($this->id);
    }

    public static function fromBatch(Batch $batch, BatchPauseStore $pauseStore): self
    {
        $queue = static::readProperty($batch, 'queue');
        $repository = static::readProperty($batch, 'repository');

        return new self(
            $queue,
            $repository,
            $batch->id,
            $batch->name,
            $batch->totalJobs,
            $batch->pendingJobs,
            $batch->failedJobs,
            $batch->failedJobIds,
            $batch->options,
            $batch->createdAt,
            $batch->cancelledAt,
            $batch->finishedAt,
            $pauseStore,
        );
    }

    protected static function readProperty(Batch $batch, string $property): mixed
    {
        return (fn () => $this->{$property})->call($batch);
    }
}
