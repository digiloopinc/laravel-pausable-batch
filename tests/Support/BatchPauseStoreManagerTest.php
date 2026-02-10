<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Support;

use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Redis\RedisManager;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class BatchPauseStoreManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_builds_a_store_from_queue_connection_options(): void
    {
        $redis = m::mock(RedisManager::class);
        $config = m::mock(ConfigRepository::class);

        $config->shouldReceive('get')
            ->once()
            ->with('queue.connections.redis', [])
            ->andReturn([
                'connection' => 'custom-redis',
                'options' => [
                    'pausable' => [
                        'redis_prefix' => 'batch-prefix',
                        'restore_chunk_size' => 25,
                    ],
                ],
            ]);

        $manager = new BatchPauseStoreManager($redis, $config);
        $store = $manager->forQueueConnection('redis');

        $connection = (fn () => $this->connection)->call($store);
        $prefix = (fn () => $this->prefix)->call($store);
        $restoreChunkSize = (fn () => $this->restoreChunkSize)->call($store);

        $this->assertSame('custom-redis', $connection);
        $this->assertSame('batch-prefix', $prefix);
        $this->assertSame(25, $restoreChunkSize);
    }

    public function test_it_uses_defaults_and_normalizes_invalid_values(): void
    {
        $redis = m::mock(RedisManager::class);
        $config = m::mock(ConfigRepository::class);

        $config->shouldReceive('get')
            ->once()
            ->with('queue.connections.redis', [])
            ->andReturn([
                'options' => [
                    'pausable' => [
                        'redis_prefix' => '',
                        'restore_chunk_size' => 0,
                    ],
                ],
            ]);

        $manager = new BatchPauseStoreManager($redis, $config);
        $store = $manager->forQueueConnection('redis');

        $connection = (fn () => $this->connection)->call($store);
        $prefix = (fn () => $this->prefix)->call($store);
        $restoreChunkSize = (fn () => $this->restoreChunkSize)->call($store);

        $this->assertSame('default', $connection);
        $this->assertSame('laravel-pausable-batch', $prefix);
        $this->assertSame(1, $restoreChunkSize);
    }

    public function test_it_caches_store_instances_by_queue_connection_name(): void
    {
        $redis = m::mock(RedisManager::class);
        $config = m::mock(ConfigRepository::class);

        $config->shouldReceive('get')
            ->twice()
            ->with('queue.connections.redis', [])
            ->andReturn([
                'connection' => 'default',
                'options' => [
                    'pausable' => [
                        'redis_prefix' => 'cached-prefix',
                        'restore_chunk_size' => 2,
                    ],
                ],
            ]);

        $manager = new BatchPauseStoreManager($redis, $config);

        $first = $manager->forQueueConnection('redis');
        $second = $manager->forQueueConnection('redis');

        $this->assertSame($first, $second);
    }

    public function test_it_returns_the_default_queue_connection_name(): void
    {
        $redis = m::mock(RedisManager::class);
        $config = m::mock(ConfigRepository::class);

        $config->shouldReceive('get')
            ->once()
            ->with('queue.default', 'default')
            ->andReturn('redis');

        $manager = new BatchPauseStoreManager($redis, $config);

        $this->assertSame('redis', $manager->defaultQueueConnection());
    }
}
