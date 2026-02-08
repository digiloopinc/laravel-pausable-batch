<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch;

use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Connectors\PausableRedisConnector;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStore;
use Illuminate\Bus\BatchRepository;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\ServiceProvider;

class LaravelPausableBatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-pausable-batch.php', 'laravel-pausable-batch');

        $this->app->singleton(BatchPauseStore::class, function ($app): BatchPauseStore {
            $config = $app['config']->get('laravel-pausable-batch', []);

            return new BatchPauseStore(
                $app->make(RedisManager::class),
                (string) ($config['redis_connection'] ?? 'default'),
                (string) ($config['redis_prefix'] ?? 'laravel-pausable-batch'),
                (int) ($config['restore_chunk_size'] ?? 1000),
            );
        });

        $this->app->extend(BatchRepository::class, function (BatchRepository $repository, $app): BatchRepository {
            return new PausableBatchRepository(
                $repository,
                $app->make(BatchPauseStore::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-pausable-batch.php' => config_path('laravel-pausable-batch.php'),
        ], 'laravel-pausable-batch-config');

        $this->app->afterResolving('queue', function (QueueManager $manager): void {
            $manager->addConnector('pausable-redis', function () {
                return new PausableRedisConnector(
                    $this->app->make('redis'),
                    $this->app->make(BatchPauseStore::class),
                );
            });
        });
    }
}
