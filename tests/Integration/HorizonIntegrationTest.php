<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Integration;

use Digiloop\LaravelPausableBatch\Queue\HorizonPausableRedisQueue;
use Digiloop\LaravelPausableBatch\Queue\PausableRedisQueue;
use Digiloop\LaravelPausableBatch\Tests\TestCase;
use Illuminate\Queue\QueueManager;

class HorizonIntegrationTest extends TestCase
{
    public function test_it_uses_horizon_pausable_queue_when_horizon_is_installed(): void
    {
        if (! class_exists(\Laravel\Horizon\Connectors\RedisConnector::class)
            || ! class_exists(\Laravel\Horizon\RedisQueue::class)) {
            $this->markTestSkipped('laravel/horizon is not installed.');
        }

        /** @var QueueManager $queue */
        $queue = $this->app->make('queue');

        $connection = $queue->connection('redis');

        $this->assertInstanceOf(HorizonPausableRedisQueue::class, $connection);
        $this->assertNotInstanceOf(PausableRedisQueue::class, $connection);
    }
}
