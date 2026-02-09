<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Bus;

use Carbon\CarbonImmutable;
use Digiloop\LaravelPausableBatch\Bus\PausableBatch;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class PausableBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_delegates_pause_operations_to_the_pause_store(): void
    {
        $pauseStore = m::mock(BatchPauseStore::class);
        $pauseStore->shouldReceive('pause')->once()->with('batch-1');
        $pauseStore->shouldReceive('paused')->once()->with('batch-1')->andReturnTrue();
        $pauseStore->shouldReceive('resume')->once()->with('batch-1');

        $batch = $this->makePausableBatch($pauseStore);

        $batch->pause();
        $this->assertTrue($batch->paused());
        $batch->resume();
    }

    public function test_it_can_wrap_a_regular_batch(): void
    {
        $pauseStore = m::mock(BatchPauseStore::class);
        $queue = m::mock(QueueFactory::class);
        $repository = m::mock(BatchRepository::class);

        $batch = new Batch(
            $queue,
            $repository,
            'batch-1',
            'My Batch',
            10,
            9,
            1,
            ['failed-1'],
            ['foo' => 'bar'],
            CarbonImmutable::parse('2025-01-01 00:00:00'),
            null,
            null,
        );

        $wrapped = PausableBatch::fromBatch($batch, $pauseStore);

        $this->assertSame('batch-1', $wrapped->id);
        $this->assertSame('My Batch', $wrapped->name);
        $this->assertSame(10, $wrapped->totalJobs);
        $this->assertSame(9, $wrapped->pendingJobs);
        $this->assertSame(1, $wrapped->failedJobs);
        $this->assertSame(['failed-1'], $wrapped->failedJobIds);
        $this->assertSame(['foo' => 'bar'], $wrapped->options);

        $queueProperty = (fn () => $this->queue)->call($wrapped);
        $repositoryProperty = (fn () => $this->repository)->call($wrapped);

        $this->assertSame($queue, $queueProperty);
        $this->assertSame($repository, $repositoryProperty);
    }

    protected function makePausableBatch(BatchPauseStore $pauseStore): PausableBatch
    {
        return new PausableBatch(
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
    }
}
