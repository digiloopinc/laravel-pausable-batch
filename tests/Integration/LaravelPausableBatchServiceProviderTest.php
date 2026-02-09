<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Integration;

use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Queue\PausableRedisQueue;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Digiloop\LaravelPausableBatch\Tests\TestCase;
use Illuminate\Bus\BatchRepository;
use Mockery as m;

class LaravelPausableBatchServiceProviderTest extends TestCase
{
    public function test_it_merges_default_configuration_values(): void
    {
        $this->assertSame('default', config('laravel-pausable-batch.redis_connection'));
        $this->assertSame('test-laravel-pausable-batch', config('laravel-pausable-batch.redis_prefix'));
        $this->assertSame(2, config('laravel-pausable-batch.restore_chunk_size'));
    }

    public function test_it_resolves_batch_pause_store_from_container(): void
    {
        $store = $this->app->make(BatchPauseStore::class);

        $this->assertInstanceOf(BatchPauseStore::class, $store);

        $connection = (fn () => $this->connection)->call($store);
        $prefix = (fn () => $this->prefix)->call($store);
        $restoreChunkSize = (fn () => $this->restoreChunkSize)->call($store);

        $this->assertSame('default', $connection);
        $this->assertSame('test-laravel-pausable-batch', $prefix);
        $this->assertSame(2, $restoreChunkSize);
    }

    public function test_it_extends_the_batch_repository_binding(): void
    {
        $underlying = m::mock(BatchRepository::class);

        $this->app->bind(BatchRepository::class, fn () => $underlying);

        $resolved = $this->app->make(BatchRepository::class);

        $this->assertInstanceOf(PausableBatchRepository::class, $resolved);

        $innerRepository = (fn () => $this->repository)->call($resolved);

        $this->assertSame($underlying, $innerRepository);
    }

    public function test_it_registers_the_pausable_redis_queue_connector(): void
    {
        $queue = $this->app['queue']->connection('redis-pausable');

        $this->assertInstanceOf(PausableRedisQueue::class, $queue);
    }
}
