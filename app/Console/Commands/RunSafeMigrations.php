<?php

namespace App\Console\Commands;

use App\Services\MigrationLock;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunSafeMigrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:safe {--force : Force the operation to run in production}
                                        {--seed : Run seeders after migration}
                                        {--lock-timeout=300 : Retained for CLI compatibility; locks now last for the database session}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations with distributed locking to prevent concurrent execution';

    /**
     * Execute the console command.
     */
    public function handle(MigrationLock $lock): int
    {
        if ($this->getLaravel()->environment('production') && ! $this->option('force')) {
            $this->error('Running migrations in production requires --force flag');

            return 1;
        }

        $lockAcquired = false;

        try {
            $lockAcquired = $lock->acquire();
            if (! $lockAcquired) {
                $this->error('Another migration process owns the lock. Retry after it completes.');

                return 1;
            }

            $this->info('Migration lock acquired. Starting migrations...');

            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info('Database connection verified.');
            } catch (Exception $e) {
                $this->error('Database connection failed: '.$e->getMessage());

                return 1;
            }

            // Run migrations
            $exitCode = $this->call('migrate', [
                '--force' => $this->option('force'),
            ]);

            if ($exitCode !== 0) {
                $this->error('Migrations failed.');

                return $exitCode;
            }

            $this->info('Migrations completed successfully.');

            // Clear and rebuild caches
            $this->info('Clearing caches...');
            $this->call('cache:clear');
            $this->call('config:clear');
            $this->call('route:clear');
            $this->call('view:clear');

            // Only cache in production
            if ($this->getLaravel()->environment('production')) {
                $this->info('Rebuilding caches...');
                $this->call('config:cache');
                $this->call('route:cache');
                $this->call('view:cache');
            }

            // Run seeders if requested
            if ($this->option('seed')) {
                $this->info('Running seeders...');
                $exitCode = $this->call('db:seed', [
                    '--force' => $this->option('force'),
                ]);

                if ($exitCode !== 0) {
                    $this->error('Seeders failed.');

                    return $exitCode;
                }
            }

            $this->info('All migration tasks completed successfully.');

            return 0;

        } catch (Exception $e) {
            $this->error('Migration error: '.$e->getMessage());

            return 1;
        } finally {
            // Always release lock if we acquired it
            if ($lockAcquired) {
                $lock->release();
                $this->info('Migration lock released.');
            }
        }
    }
}
