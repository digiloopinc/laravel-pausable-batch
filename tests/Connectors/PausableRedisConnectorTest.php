<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Connectors;

use Digiloop\LaravelPausableBatch\Connectors\PausableRedisConnector;
use Digiloop\LaravelPausableBatch\Queue\PausableRedisQueue;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class PausableRedisConnectorTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_creates_a_pausable_redis_queue_using_config_values(): void
    {
        $redis = m::mock(RedisFactory::class);
        $pauseStoreManager = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);
        $pauseStoreManager->shouldReceive('forQueueConfig')->once()->andReturn($pauseStore);

        $connector = new PausableRedisConnector($redis, $pauseStoreManager);

        $queue = $connector->connect([
            'driver' => 'redis',
            'queue' => 'emails',
            'connection' => 'default',
            'retry_after' => 120,
            'block_for' => 5,
            'after_commit' => true,
            'migration_batch_size' => 25,
        ]);

        $this->assertInstanceOf(PausableRedisQueue::class, $queue);

        $this->assertSame('queues:emails', $queue->getQueue('emails'));
        $pauseStoreProperty = (fn () => $this->pauseStore)->call($queue);
        $this->assertSame($pauseStore, $pauseStoreProperty);
    }

    public function test_it_uses_defaults_when_optional_config_is_missing(): void
    {
        $redis = m::mock(RedisFactory::class);
        $pauseStoreManager = m::mock(BatchPauseStoreManager::class);
        $pauseStore = m::mock(BatchPauseStore::class);
        $pauseStoreManager->shouldReceive('forQueueConfig')->once()->andReturn($pauseStore);

        $connector = new PausableRedisConnector($redis, $pauseStoreManager);

        $queue = $connector->connect([
            'driver' => 'redis',
            'queue' => 'default',
        ]);

        $this->assertInstanceOf(PausableRedisQueue::class, $queue);
        $this->assertSame('queues:default', $queue->getQueue(null));
    }
}
