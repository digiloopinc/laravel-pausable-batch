# Changelog

All notable changes to `laravel-pausable-batch` will be documented in this file.

## 0.0.1 - 2026-02-09

- Added `pausable-redis` queue connector and queue implementation to park paused-batch jobs.
- Added pause metadata Redis store with pause/resume/cleanup behavior.
- Added `PausableBatch` wrapper and repository decorator to expose `pause()`, `paused()`, and `resume()`.
- Added package service provider registration, config publish support, and package config.
- Replaced package skeleton docs/tests with batch pause/resume focused documentation and tests.

**Full Changelog**: https://github.com/digiloopinc/laravel-pausable-batch/commits/0.0.1

## 0.1.0 - 2026-02-08

- Added `redis` queue connector and queue implementation to park paused-batch jobs.
- Added pause metadata Redis store with pause/resume/cleanup behavior.
- Added `PausableBatch` wrapper and repository decorator to expose `pause()`, `paused()`, and `resume()`.
- Added package service provider registration, config publish support, and package config.
- Replaced package skeleton docs/tests with batch pause/resume focused documentation and tests.

## 0.2.0 - 2026-02-09

- Breaking: switched to redis-only driver behavior (removed separate `pausable-redis` path).
- Added Horizon-specific connector/queue integration path while preserving shared pause interception behavior.
- Refactored queue pause logic into a shared trait used by both Laravel and Horizon redis queue implementations.
- Updated docs and tests for redis-only configuration.
