<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch\Tests\Concerns;

use Throwable;

trait InteractsWithRedis
{
    protected static bool $connectionFailedOnceWithDefaultsSkip = false;

    protected bool $redisAvailable = false;

    protected function setUpRedis(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed.');
        }

        if (static::$connectionFailedOnceWithDefaultsSkip) {
            $this->markTestSkipped('Trying default host/port failed, please set REDIS_HOST & REDIS_PORT.');
        }

        $host = (string) env('REDIS_HOST', '127.0.0.1');
        $port = (int) env('REDIS_PORT', 6379);

        try {
            $connection = $this->app['redis']->connection('default');
            $connection->ping();
            $connection->flushdb();
            $this->redisAvailable = true;
        } catch (Throwable $e) {
            if ($host === '127.0.0.1' && $port === 6379 && env('REDIS_HOST') === null) {
                static::$connectionFailedOnceWithDefaultsSkip = true;
            }

            $this->markTestSkipped('Redis is unavailable: '.$e->getMessage());
        }
    }

    protected function tearDownRedis(): void
    {
        if (! $this->redisAvailable || static::$connectionFailedOnceWithDefaultsSkip) {
            return;
        }

        try {
            $connection = $this->app['redis']->connection('default');
            $connection->flushdb();
            $connection->disconnect();
        } catch (Throwable) {
            // Best effort cleanup.
        }
    }
}
