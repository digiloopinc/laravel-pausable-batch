<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Support;

use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Digiloop\LaravelPausableBatch\Tests\Concerns\InteractsWithRedis;
use Digiloop\LaravelPausableBatch\Tests\TestCase;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Redis\RedisManager;
use Mockery as m;
use ReflectionMethod;

class BatchPauseStoreTest extends TestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRedis();
    }

    protected function tearDown(): void
    {
        $this->tearDownRedis();

        parent::tearDown();
    }

    public function test_it_can_pause_and_report_paused_state(): void
    {
        $store = $this->app->make(BatchPauseStore::class);

        $this->assertFalse($store->paused('batch-1'));

        $store->pause('batch-1');

        $this->assertTrue($store->paused('batch-1'));
    }

    public function test_it_parks_jobs_and_resumes_them_in_order(): void
    {
        $store = $this->app->make(BatchPauseStore::class);
        $batchId = 'batch-1';
        $queueKey = 'queues:default';

        $store->pause($batchId);
        $store->parkJob($batchId, $queueKey, 'job-1');
        $store->parkJob($batchId, $queueKey, 'job-2');

        $store->resume($batchId);

        $restored = $this->redis()->lrange($queueKey, 0, -1);

        $this->assertSame(['job-1', 'job-2'], $restored);
        $this->assertSame(0, $this->redis()->exists($this->pauseKey($batchId)));
        $this->assertSame(0, $this->redis()->exists($this->queueIndexKey($batchId)));
    }

    public function test_it_restores_jobs_in_chunks_and_cleans_up(): void
    {
        $store = new BatchPauseStore(
            $this->app->make(RedisManager::class),
            'default',
            'test-laravel-pausable-batch',
            2,
        );

        $batchId = 'batch-2';
        $queueKey = 'queues:emails';

        foreach (range(1, 5) as $i) {
            $store->parkJob($batchId, $queueKey, 'job-'.$i);
        }

        $store->pause($batchId);
        $store->resume($batchId);

        $this->assertSame(['job-1', 'job-2', 'job-3', 'job-4', 'job-5'], $this->redis()->lrange($queueKey, 0, -1));
        $this->assertSame(0, $this->redis()->exists($this->pausedListKey($batchId, $queueKey)));
    }

    public function test_cleanup_deletes_all_paused_data_for_the_batch(): void
    {
        $store = $this->app->make(BatchPauseStore::class);
        $batchId = 'batch-3';

        $store->pause($batchId);
        $store->parkJob($batchId, 'queues:default', 'job-1');
        $store->parkJob($batchId, 'queues:emails', 'job-2');

        $store->cleanup($batchId);

        $this->assertSame(0, $this->redis()->exists($this->pauseKey($batchId)));
        $this->assertSame(0, $this->redis()->exists($this->queueIndexKey($batchId)));
        $this->assertSame(0, $this->redis()->exists($this->pausedListKey($batchId, 'queues:default')));
        $this->assertSame(0, $this->redis()->exists($this->pausedListKey($batchId, 'queues:emails')));
    }

    public function test_push_many_handles_empty_and_single_job_batches(): void
    {
        $store = new BatchPauseStore(m::mock(RedisManager::class), 'default', 'prefix', 1000);
        $method = new ReflectionMethod(BatchPauseStore::class, 'pushMany');
        $method->setAccessible(true);

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('rpush')->once()->with('queues:default', 'job-1');

        $method->invoke($store, $connection, 'queues:default', []);
        $method->invoke($store, $connection, 'queues:default', ['job-1']);

        $this->addToAssertionCount(1);
    }

    public function test_push_many_uses_command_for_phpredis_and_predis_connections(): void
    {
        $store = new BatchPauseStore(m::mock(RedisManager::class), 'default', 'prefix', 1000);
        $method = new ReflectionMethod(BatchPauseStore::class, 'pushMany');
        $method->setAccessible(true);

        $phpRedisConnection = m::mock(PhpRedisConnection::class);
        $phpRedisConnection->shouldReceive('command')->once()->with('rpush', ['queues:default', 'job-1', 'job-2']);
        $method->invoke($store, $phpRedisConnection, 'queues:default', ['job-1', 'job-2']);

        $predisConnection = m::mock(PredisConnection::class);
        $predisConnection->shouldReceive('command')->once()->with('rpush', ['queues:default', 'job-3', 'job-4']);
        $method->invoke($store, $predisConnection, 'queues:default', ['job-3', 'job-4']);

        $this->addToAssertionCount(1);
    }

    public function test_push_many_falls_back_to_iterative_rpush_for_generic_connections(): void
    {
        $store = new BatchPauseStore(m::mock(RedisManager::class), 'default', 'prefix', 1000);
        $method = new ReflectionMethod(BatchPauseStore::class, 'pushMany');
        $method->setAccessible(true);

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('rpush')->once()->with('queues:default', 'job-1');
        $connection->shouldReceive('rpush')->once()->with('queues:default', 'job-2');

        $method->invoke($store, $connection, 'queues:default', ['job-1', 'job-2']);

        $this->addToAssertionCount(1);
    }

    protected function redis(): Connection
    {
        return $this->app['redis']->connection('default');
    }

    protected function pauseKey(string $batchId): string
    {
        return "test-laravel-pausable-batch:batch:{$batchId}:paused";
    }

    protected function queueIndexKey(string $batchId): string
    {
        return "test-laravel-pausable-batch:batch:{$batchId}:queues";
    }

    protected function pausedListKey(string $batchId, string $queueKey): string
    {
        return "test-laravel-pausable-batch:batch:{$batchId}:paused_jobs:{$queueKey}";
    }
}
