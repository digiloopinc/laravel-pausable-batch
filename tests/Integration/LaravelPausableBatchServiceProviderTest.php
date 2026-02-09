<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Integration;

use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Queue\PausableRedisQueue;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Digiloop\LaravelPausableBatch\Tests\TestCase;
use Illuminate\Bus\BatchRepository;
use Mockery as m;

class LaravelPausableBatchServiceProviderTest extends TestCase
{
    public function test_it_resolves_batch_pause_store_manager_from_container(): void
    {
        $manager = $this->app->make(BatchPauseStoreManager::class);

        $this->assertInstanceOf(BatchPauseStoreManager::class, $manager);

        $store = $manager->forQueueConnection('redis-pausable');

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
