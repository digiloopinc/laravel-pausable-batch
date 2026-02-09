# Laravel Pausable Batch

Pause and resume Laravel job batches running on Redis queues.

> [!WARNING]
> This package is currently under active development and is not yet considered production-stable.

When a batch is paused, workers using the `redis` queue driver will not execute jobs that belong to that batch. Paused jobs are parked in Redis and restored when the batch resumes.

## Installation

```bash
composer require digiloopinc/laravel-pausable-batch
```

## Queue Connection Setup

Use your standard `redis` queue connection:

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
        'options' => [
            'pausable' => [
                'redis_prefix' => env('PAUSABLE_BATCH_REDIS_PREFIX', 'laravel-pausable-batch'),
                'restore_chunk_size' => (int) env('PAUSABLE_BATCH_RESTORE_CHUNK_SIZE', 1000),
            ],
        ],
    ],
],
```

Then run workers against that connection:

```bash
php artisan queue:work redis
```

Horizon uses the same `redis` connection and is automatically supported.

## Batch API

Batches returned from the repository are wrapped as `PausableBatch` and expose:

- `pause(): void`
- `paused(): bool`
- `resume(): void`

```php
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new \App\Jobs\FirstJob(),
    new \App\Jobs\SecondJob(),
])->dispatch();

$batch->pause();

if ($batch->paused()) {
    $batch->resume();
}
```

## Configuration

Configure pausable behavior directly on each queue connection under `options.pausable`:

- `redis_prefix`: Redis key prefix for pause metadata.
- `restore_chunk_size`: Number of paused jobs restored per chunk on resume.

Pause metadata uses the same Redis connection as the queue connection's `connection` setting.

## Behavior Notes

- Only `Batchable` jobs are considered pausable.
- Pause is enforced at worker pop time.
- Paused jobs are moved off the active queue into per-batch paused lists.
- Resume restores paused jobs to the tail of their original queue.
- Pause metadata and parked jobs are cleaned on resume, cancellation, and finish.

## Testing

```bash
composer test
```
