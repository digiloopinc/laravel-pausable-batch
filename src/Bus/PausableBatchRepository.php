<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Bus;

use Closure;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\UpdatedBatchJobCounts;

class PausableBatchRepository implements BatchRepository
{
    public function __construct(
        protected BatchRepository $repository,
        protected BatchPauseStore $pauseStore,
    ) {
    }

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
        $this->repository->markAsFinished($batchId);
        $this->pauseStore->cleanup($batchId);
    }

    public function cancel(string $batchId): void
    {
        $this->repository->cancel($batchId);
        $this->pauseStore->cleanup($batchId);
    }

    public function delete(string $batchId): void
    {
        $this->repository->delete($batchId);
        $this->pauseStore->cleanup($batchId);
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

        return PausableBatch::fromBatch($batch, $this->pauseStore);
    }
}
