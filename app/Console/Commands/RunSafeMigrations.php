<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
                                        {--lock-timeout=300 : Lock timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations with distributed locking to prevent concurrent execution';

    /**
     * Lock key for migrations
     */
    private const LOCK_KEY = 'migrations:lock';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->getLaravel()->environment('production') && ! $this->option('force')) {
            $this->error('Running migrations in production requires --force flag');

            return 1;
        }

        $lockTimeout = (int) $this->option('lock-timeout');
        $lockAcquired = false;

        try {
            // Try to acquire lock
            $lockAcquired = Cache::add(self::LOCK_KEY, gethostname().'-'.getmypid(), $lockTimeout);

            if (! $lockAcquired) {
                $this->warn('Another migration process is running. Waiting...');

                // Wait for lock to be released
                $maxWait = 60; // 5 minutes
                $waited = 0;

                while (Cache::has(self::LOCK_KEY) && $waited < $maxWait) {
                    sleep(5);
                    $waited++;
                    $this->info("Waiting for migration lock... ({$waited}/{$maxWait})");
                }

                if ($waited >= $maxWait) {
                    $this->error('Migration lock timeout. Consider checking for stuck migrations.');

                    return 1;
                }

                // Try to acquire lock again
                $lockAcquired = Cache::add(self::LOCK_KEY, gethostname().'-'.getmypid(), $lockTimeout);

                if (! $lockAcquired) {
                    $this->error('Failed to acquire migration lock after waiting.');

                    return 1;
                }
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
                Cache::forget(self::LOCK_KEY);
                $this->info('Migration lock released.');
            }
        }
    }
}
