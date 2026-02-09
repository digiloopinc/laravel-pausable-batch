<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Bus;

use Carbon\CarbonImmutable;
use Digiloop\LaravelPausableBatch\Bus\PausableBatch;
use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class PausableBatchRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_store_wraps_the_returned_batch(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);
        $pending = m::mock(PendingBatch::class);
        $batch = $this->makeBatch();

        $repository->shouldReceive('store')->once()->with($pending)->andReturn($batch);
        $pauseStores->shouldReceive('forQueueConnection')->once()->with('redis-pausable')->andReturn($pauseStore);

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $stored = $pausable->store($pending);

        $this->assertInstanceOf(PausableBatch::class, $stored);
        $this->assertSame($batch->id, $stored->id);
    }

    public function test_find_wraps_batches_and_returns_null_when_missing(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);

        $repository->shouldReceive('find')->once()->with('batch-1')->andReturn($this->makeBatch('batch-1'));
        $repository->shouldReceive('find')->once()->with('missing')->andReturnNull();
        $pauseStores->shouldReceive('forQueueConnection')->once()->with('redis-pausable')->andReturn($pauseStore);

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $this->assertInstanceOf(PausableBatch::class, $pausable->find('batch-1'));
        $this->assertNull($pausable->find('missing'));
    }

    public function test_get_wraps_all_batches(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);

        $repository->shouldReceive('get')->once()->with(2, null)->andReturn([
            $this->makeBatch('batch-1'),
            $this->makeBatch('batch-2'),
        ]);
        $pauseStores->shouldReceive('forQueueConnection')->twice()->with('redis-pausable')->andReturn($pauseStore);

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $batches = $pausable->get(2, null);

        $this->assertCount(2, $batches);
        $this->assertInstanceOf(PausableBatch::class, $batches[0]);
        $this->assertInstanceOf(PausableBatch::class, $batches[1]);
    }

    public function test_cleanup_is_called_for_lifecycle_methods_using_batch_connection_store(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);

        $repository->shouldReceive('find')->times(3)->with('batch-1')->andReturn($this->makeBatch('batch-1'));
        $repository->shouldReceive('markAsFinished')->once()->with('batch-1');
        $repository->shouldReceive('cancel')->once()->with('batch-1');
        $repository->shouldReceive('delete')->once()->with('batch-1');
        $pauseStores->shouldReceive('forQueueConnection')->times(3)->with('redis-pausable')->andReturn($pauseStore);
        $pauseStore->shouldReceive('cleanup')->times(3)->with('batch-1');

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $pausable->markAsFinished('batch-1');
        $pausable->cancel('batch-1');
        $pausable->delete('batch-1');

        $this->addToAssertionCount(1);
    }

    public function test_cleanup_falls_back_to_default_queue_connection_when_batch_not_found(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);

        $repository->shouldReceive('find')->once()->with('batch-1')->andReturnNull();
        $repository->shouldReceive('delete')->once()->with('batch-1');
        $pauseStores->shouldReceive('defaultQueueConnection')->once()->andReturn('redis-pausable');
        $pauseStores->shouldReceive('forQueueConnection')->once()->with('redis-pausable')->andReturn($pauseStore);
        $pauseStore->shouldReceive('cleanup')->once()->with('batch-1');

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $pausable->delete('batch-1');

        $this->addToAssertionCount(1);
    }

    public function test_it_passes_through_repository_methods(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $counts = new UpdatedBatchJobCounts(4, 1);

        $repository->shouldReceive('incrementTotalJobs')->once()->with('batch-1', 2);
        $repository->shouldReceive('decrementPendingJobs')->once()->with('batch-1', 'job-1')->andReturn($counts);
        $repository->shouldReceive('incrementFailedJobs')->once()->with('batch-1', 'job-2')->andReturn($counts);
        $repository->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        $repository->shouldReceive('rollBack')->once();
        $repository->shouldReceive('customMethod')->once()->with('arg')->andReturn('ok');

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $pausable->incrementTotalJobs('batch-1', 2);
        $this->assertSame($counts, $pausable->decrementPendingJobs('batch-1', 'job-1'));
        $this->assertSame($counts, $pausable->incrementFailedJobs('batch-1', 'job-2'));
        $this->assertSame('transaction-result', $pausable->transaction(fn () => 'transaction-result'));
        $pausable->rollBack();
        $this->assertSame('ok', $pausable->customMethod('arg'));
    }

    public function test_wrap_is_idempotent_for_existing_pausable_batch_instances(): void
    {
        $repository = m::mock(BatchRepository::class);
        $pauseStores = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);
        $existing = new PausableBatch(
            m::mock(QueueFactory::class),
            m::mock(BatchRepository::class),
            'batch-1',
            'Batch',
            1,
            1,
            0,
            [],
            [],
            CarbonImmutable::parse('2025-01-01 00:00:00'),
            null,
            null,
            $pauseStore,
        );

        $repository->shouldReceive('find')->once()->with('batch-1')->andReturn($existing);

        $pausable = new PausableBatchRepository($repository, $pauseStores);

        $this->assertSame($existing, $pausable->find('batch-1'));
    }

    protected function makeBatch(string $id = 'batch-1'): Batch
    {
        return new Batch(
            m::mock(QueueFactory::class),
            m::mock(BatchRepository::class),
            $id,
            'Batch',
            10,
            9,
            1,
            ['failed-1'],
            ['queue' => 'default', 'connection' => 'redis-pausable'],
            CarbonImmutable::parse('2025-01-01 00:00:00'),
            null,
            null,
        );
    }
}
