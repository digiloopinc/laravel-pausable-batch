<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch;

use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Connectors\PausableRedisConnector;
use Digiloop\LaravelPausableBatch\Support\BatchPauseStoreManager;
use Illuminate\Bus\BatchRepository;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

class LaravelPausableBatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BatchPauseStoreManager::class, function ($app): BatchPauseStoreManager {
            return new BatchPauseStoreManager(
                $app->make('redis'),
                $app['config'],
            );
        });

        $this->app->extend(BatchRepository::class, function (BatchRepository $repository, $app): BatchRepository {
            return new PausableBatchRepository(
                $repository,
                $app->make(BatchPauseStoreManager::class),
            );
        });
    }

    public function boot(): void
    {
        $this->app->afterResolving('queue', function (QueueManager $manager): void {
            $manager->addConnector('pausable-redis', function () {
                return new PausableRedisConnector(
                    $this->app->make('redis'),
                    $this->app->make(BatchPauseStoreManager::class),
                );
            });
        });
    }
}
