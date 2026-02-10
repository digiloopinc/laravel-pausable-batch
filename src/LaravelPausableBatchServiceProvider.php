<?php

declare(strict_types=1);

namespace Digiloop\LaravelPausableBatch;

use Digiloop\LaravelPausableBatch\Bus\PausableBatchRepository;
use Digiloop\LaravelPausableBatch\Connectors\HorizonPausableRedisConnector;
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
        $registerRedisConnector = function (QueueManager $manager): void {
            $manager->addConnector('redis', function () {
                $horizonConnectorAvailable = class_exists(\Laravel\Horizon\Connectors\RedisConnector::class)
                    && class_exists(\Laravel\Horizon\RedisQueue::class);

                if ($horizonConnectorAvailable) {
                    return new HorizonPausableRedisConnector(
                        $this->app->make('redis'),
                        $this->app->make(BatchPauseStoreManager::class),
                    );
                }

                return new PausableRedisConnector(
                    $this->app->make('redis'),
                    $this->app->make(BatchPauseStoreManager::class),
                );
            });
        };

        if ($this->app->resolved('queue')) {
            $registerRedisConnector($this->app->make('queue'));
        }

        $this->app->afterResolving('queue', $registerRedisConnector);
    }
}
