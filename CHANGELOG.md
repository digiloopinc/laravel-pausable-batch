# Changelog

All notable changes to `laravel-pausable-batch` will be documented in this file.

## Unreleased

- Added `pausable-redis` queue connector and queue implementation to park paused-batch jobs.
- Added pause metadata Redis store with pause/resume/cleanup behavior.
- Added `PausableBatch` wrapper and repository decorator to expose `pause()`, `paused()`, and `resume()`.
- Added package service provider registration, config publish support, and package config.
- Replaced package skeleton docs/tests with batch pause/resume focused documentation and tests.
