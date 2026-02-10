<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Integration;

use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Queue\HorizonPausableRedisQueue;
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

        $store = $manager->forQueueConnection('redis');

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

    public function test_it_registers_the_redis_queue_connector_with_pausable_queue(): void
    {
        $queue = $this->app['queue']->connection('redis');

        $this->assertResolvedPausableQueueType($queue);
    }

    public function test_it_registers_pausable_behavior_for_named_redis_connections(): void
    {
        $queue = $this->app['queue']->connection('redis');

        $this->assertResolvedPausableQueueType($queue);
    }

    protected function assertResolvedPausableQueueType(object $queue): void
    {
        $horizonClassesAvailable = class_exists(\Laravel\Horizon\Connectors\RedisConnector::class)
            && class_exists(\Laravel\Horizon\RedisQueue::class);

        if ($horizonClassesAvailable) {
            $this->assertInstanceOf(HorizonPausableRedisQueue::class, $queue);

            return;
        }

        $this->assertInstanceOf(PausableRedisQueue::class, $queue);
    }
}
