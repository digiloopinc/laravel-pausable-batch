# Laravel Pausable Batch

Pause and resume Laravel job batches running on Redis queues.

When a batch is paused, workers using the `pausable-redis` queue driver will not execute jobs that belong to that batch. Paused jobs are parked in Redis and restored when the batch resumes.

## Installation

```bash
composer require digiloopinc/laravel-pausable-batch
```

## Queue Connection Setup

Add a queue connection using the custom driver:

```php
// config/queue.php
'connections' => [
    'redis-pausable' => [
        'driver' => 'pausable-redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],
```

Then run workers against that connection:

```bash
php artisan queue:work redis-pausable
```

Horizon can also use this connection by referencing it in Horizon queue configuration.

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

Publish config:

```bash
php artisan vendor:publish --tag=laravel-pausable-batch-config
```

Config file: `config/laravel-pausable-batch.php`

- `redis_connection`: Redis connection used for pause metadata.
- `redis_prefix`: Redis key prefix for pause metadata.
- `restore_chunk_size`: Number of paused jobs restored per chunk on resume.

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
