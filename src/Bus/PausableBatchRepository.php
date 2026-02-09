<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Bus;

use Closure;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\UpdatedBatchJobCounts;

class PausableBatchRepository implements BatchRepository
{
    public function __construct(
        protected BatchRepository $repository,
        protected BatchPauseStoreManager $pauseStores,
    ) {}

    public function store(PendingBatch $batch): Batch
    {
        return $this->wrap($this->repository->store($batch));
    }

    public function find(string $batchId): ?Batch
    {
        $batch = $this->repository->find($batchId);

        return $batch ? $this->wrap($batch) : null;
    }

    public function get($limit, $before)
    {
        return collect($this->repository->get($limit, $before))
            ->map(fn (Batch $batch) => $this->wrap($batch))
            ->all();
    }

    public function incrementTotalJobs(string $batchId, int $amount): void
    {
        $this->repository->incrementTotalJobs($batchId, $amount);
    }

    public function decrementPendingJobs(string $batchId, string $jobId): UpdatedBatchJobCounts
    {
        return $this->repository->decrementPendingJobs($batchId, $jobId);
    }

    public function incrementFailedJobs(string $batchId, string $jobId): UpdatedBatchJobCounts
    {
        return $this->repository->incrementFailedJobs($batchId, $jobId);
    }

    public function markAsFinished(string $batchId): void
    {
        $pauseStore = $this->pauseStoreForBatchId($batchId);

        $this->repository->markAsFinished($batchId);
        $pauseStore->cleanup($batchId);
    }

    public function cancel(string $batchId): void
    {
        $pauseStore = $this->pauseStoreForBatchId($batchId);

        $this->repository->cancel($batchId);
        $pauseStore->cleanup($batchId);
    }

    public function delete(string $batchId): void
    {
        $pauseStore = $this->pauseStoreForBatchId($batchId);

        $this->repository->delete($batchId);
        $pauseStore->cleanup($batchId);
    }

    public function transaction(Closure $callback): mixed
    {
        return $this->repository->transaction($callback);
    }

    public function rollBack(): void
    {
        $this->repository->rollBack();
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->repository->{$method}(...$arguments);
    }

    protected function wrap(Batch $batch): Batch
    {
        if ($batch instanceof PausableBatch) {
            return $batch;
        }

        return PausableBatch::fromBatch($batch, $this->pauseStoreForBatch($batch));
    }

    protected function pauseStoreForBatchId(string $batchId): BatchPauseStore
    {
        $batch = $this->repository->find($batchId);

        return $batch ? $this->pauseStoreForBatch($batch) : $this->pauseStores->forQueueConnection(
            $this->pauseStores->defaultQueueConnection(),
        );
    }

    protected function pauseStoreForBatch(Batch $batch): BatchPauseStore
    {
        $connection = $batch->options['connection'] ?? null;
        $connection = is_string($connection) && $connection !== ''
            ? $connection
            : $this->pauseStores->defaultQueueConnection();

        return $this->pauseStores->forQueueConnection($connection);
    }
}
