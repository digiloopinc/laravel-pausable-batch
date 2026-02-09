<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Queue;

use Digiloop\LaravelPausableBatch\Queue\PausableRedisQueue;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Digiloop\LaravelPausableBatch\Tests\Concerns\InteractsWithRedis;
use Digiloop\LaravelPausableBatch\Tests\TestCase;
use Illuminate\Queue\Jobs\RedisJob;
use Mockery as m;

class PausableRedisQueueTest extends TestCase
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

    public function test_constructor_signature_requires_a_pause_store_instance(): void
    {
        $constructor = new \ReflectionMethod(PausableRedisQueue::class, '__construct');
        $parameters = $constructor->getParameters();
        $pauseStoreParameter = $parameters[7];

        $this->assertFalse($pauseStoreParameter->allowsNull());
        $this->assertTrue($pauseStoreParameter->hasType());
        $type = $pauseStoreParameter->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(BatchPauseStore::class, $type->getName());
        $this->assertSame('pauseStore', $pauseStoreParameter->getName());
    }

    public function test_it_pops_jobs_for_unpaused_batches(): void
    {
        $queue = $this->app['queue']->connection('redis');

        $queue->pushRaw($this->payload('job-1', 'batch-1'), 'default');

        $job = $queue->pop('default');

        $this->assertInstanceOf(RedisJob::class, $job);
        $this->assertSame('job-1', json_decode($job->getRawBody(), true)['id']);

        $job->delete();
    }

    public function test_it_parks_jobs_for_paused_batches_and_moves_on_to_next_job(): void
    {
        /** @var BatchPauseStore $store */
        $store = $this->app->make(BatchPauseStoreManager::class)->forQueueConnection('redis');
        $queue = $this->app['queue']->connection('redis');

        $store->pause('batch-paused');

        $pausedPayload = $this->payload('job-paused', 'batch-paused');
        $readyPayload = $this->payload('job-ready', null);

        $queue->pushRaw($pausedPayload, 'default');
        $queue->pushRaw($readyPayload, 'default');

        $job = $queue->pop('default');

        $this->assertInstanceOf(RedisJob::class, $job);
        $this->assertSame('job-ready', json_decode($job->getRawBody(), true)['id']);
        $parkedPayloads = $this->app['redis']->connection('default')->lrange($this->pausedListKey('batch-paused', 'queues:default'), 0, -1);
        $this->assertCount(1, $parkedPayloads);
        $parkedPayload = json_decode($parkedPayloads[0], true);
        $this->assertIsArray($parkedPayload);
        $this->assertSame('job-paused', $parkedPayload['id'] ?? null);
        $this->assertSame('batch-paused', $parkedPayload['batchId'] ?? null);

        $job->delete();

        $store->resume('batch-paused');

        $restored = $queue->pop('default');

        $this->assertInstanceOf(RedisJob::class, $restored);
        $this->assertSame('job-paused', json_decode($restored->getRawBody(), true)['id']);

        $restored->delete();
    }

    public function test_it_returns_null_when_only_paused_jobs_exist(): void
    {
        /** @var BatchPauseStore $store */
        $store = $this->app->make(BatchPauseStoreManager::class)->forQueueConnection('redis');
        $queue = $this->app['queue']->connection('redis');

        $store->pause('batch-paused');

        $pausedPayload = $this->payload('job-paused', 'batch-paused');
        $queue->pushRaw($pausedPayload, 'default');

        $job = $queue->pop('default');

        $this->assertNull($job);
        $parkedPayloads = $this->app['redis']->connection('default')->lrange($this->pausedListKey('batch-paused', 'queues:default'), 0, -1);
        $this->assertCount(1, $parkedPayloads);
        $parkedPayload = json_decode($parkedPayloads[0], true);
        $this->assertIsArray($parkedPayload);
        $this->assertSame('job-paused', $parkedPayload['id'] ?? null);
        $this->assertSame('batch-paused', $parkedPayload['batchId'] ?? null);
    }

    public function test_extract_batch_id_from_direct_payload_keys(): void
    {
        $queue = $this->makeQueueForExtraction();

        $this->assertSame('batch-direct', $queue->extractBatchIdPublic(json_encode(['batchId' => 'batch-direct']) ?: ''));
        $this->assertSame('batch-nested', $queue->extractBatchIdPublic(json_encode(['data' => ['batchId' => 'batch-nested']]) ?: ''));
    }

    public function test_extract_batch_id_from_serialized_command(): void
    {
        $queue = $this->makeQueueForExtraction();

        $payload = json_encode([
            'data' => [
                'command' => serialize((object) ['batchId' => 'batch-from-command']),
            ],
        ]);

        $this->assertSame('batch-from-command', $queue->extractBatchIdPublic($payload ?: ''));
    }

    public function test_extract_batch_id_returns_null_for_invalid_payloads(): void
    {
        $queue = $this->makeQueueForExtraction();

        $this->assertNull($queue->extractBatchIdPublic('not-json'));
        $this->assertNull($queue->extractBatchIdPublic(json_encode(['batchId' => '']) ?: ''));
        $this->assertNull($queue->extractBatchIdPublic(json_encode(['data' => ['command' => 'not-serialized']]) ?: ''));
        $this->assertNull($queue->extractBatchIdPublic(json_encode(['data' => ['command' => serialize((object) ['foo' => 'bar'])]]) ?: ''));
    }

    protected function payload(string $id, ?string $batchId): string
    {
        $payload = [
            'uuid' => $id,
            'id' => $id,
            'attempts' => 0,
            'displayName' => 'TestJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'retryUntil' => null,
            'data' => [
                'commandName' => 'stdClass',
                'command' => serialize((object) ['name' => 'test']),
            ],
        ];

        if ($batchId !== null) {
            $payload['batchId'] = $batchId;
        }

        return json_encode($payload) ?: '{}';
    }

    protected function pausedListKey(string $batchId, string $queueKey): string
    {
        return "test-laravel-pausable-batch:batch:{$batchId}:paused_jobs:{$queueKey}";
    }

    protected function makeQueueForExtraction(): TestablePausableRedisQueue
    {
        return new TestablePausableRedisQueue(
            $this->app['redis'],
            m::mock(BatchPauseStore::class),
            'default',
            'default',
            60,
            null,
            false,
            -1,
        );
    }
}

class TestablePausableRedisQueue extends PausableRedisQueue
{
    public function extractBatchIdPublic(string $rawPayload): ?string
    {
        return $this->extractBatchId($rawPayload);
    }
}
