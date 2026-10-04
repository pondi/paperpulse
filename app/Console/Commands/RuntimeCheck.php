<?php

namespace App\Console\Commands;

use App\Services\ReadinessCheck;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class RuntimeCheck extends Command
{
    protected $signature = 'runtime:check {--fpm-binary=php-fpm8.5} {--after-migrations : Check database tables, cache, queues and search}';

    protected $description = 'Check PHP, local conversion tools and configured application services';

    /** @var list<string> */
    public const EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'gd', 'imagick', 'intl', 'mbstring', 'openssl', 'pdo_pgsql', 'tokenizer', 'xml', 'zip'];

    public function handle(DatabaseManager $database): int
    {
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            $this->error('PaperPulse requires PostgreSQL. Set DB_CONNECTION=pgsql.');

            return self::FAILURE;
        }
        try {
            if (config('queue.default') !== 'database' || config('cache.default') !== 'database' || config('session.driver') !== 'database') {
                throw new RuntimeException('The supported runtime requires database queues, cache and sessions.');
            }
            foreach (['queue.connections.database.connection', 'cache.stores.database.connection', 'cache.stores.database.lock_connection', 'queue.failed.database', 'queue.batching.database'] as $key) {
                if ($database->connection(config($key))->getDriverName() !== 'pgsql') {
                    throw new RuntimeException('Queue and cache storage must use PostgreSQL.');
                }
            }
            if (config('reverb.servers.reverb.scaling.enabled') || config('broadcasting.default') === 'redis') {
                throw new RuntimeException('Disable Reverb scaling and Redis broadcasting for the supported runtime.');
            }
            if (! config('app.key') || config('app.debug')) {
                throw new RuntimeException('Set APP_KEY and APP_DEBUG=false before deployment.');
            }
            foreach (['paperpulse', 'pulsedav'] as $disk) {
                if (config('filesystems.disks.'.$disk.'.driver') !== 's3' || ! config('filesystems.disks.'.$disk.'.bucket')) {
                    throw new RuntimeException('Configure both private S3 storage buckets.');
                }
            }
            $provider = config('ai.file_processing_provider');
            if ($provider === 'gemini' && ! config('ai.providers.gemini.api_key')) {
                throw new RuntimeException('Set GEMINI_API_KEY for the selected processing provider.');
            }
            if ($provider === 'textract+openai' && (! config('ai.providers.openai.api_key') || ! config('ai.ocr.providers.textract.key') || ! config('ai.ocr.providers.textract.secret'))) {
                throw new RuntimeException('Configure OpenAI and Textract credentials for the selected processing provider.');
            }
            $fpm = Process::timeout(10)->run([$this->option('fpm-binary'), '-i']);
            if (! $fpm->successful() || ! preg_match('/PHP Version => (\d+\.\d+)\./', $fpm->output(), $version)
                || $version[1] !== PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION) {
                throw new RuntimeException('PHP CLI and FPM must use the same supported version.');
            }
            foreach (['pcntl', 'posix'] as $extension) {
                if (! extension_loaded($extension)) {
                    throw new RuntimeException('Enable ext-'.$extension.' for PHP CLI queue workers.');
                }
            }
            foreach (self::EXTENSIONS as $extension) {
                if (! extension_loaded($extension) || ! preg_match('/^'.preg_quote($extension, '/').'\s*$/m', $fpm->output())) {
                    throw new RuntimeException('Enable ext-'.$extension.' for both PHP CLI and FPM.');
                }
            }
            if (config('processing.conversion.driver') !== 'local' || ! is_executable(config('processing.conversion.local.binary')) || ! is_executable(config('processing.conversion.local.sandbox'))) {
                throw new RuntimeException('Install the local LibreOffice and Bubblewrap runtime.');
            }
            $sandbox = Process::timeout(10)->run([config('processing.conversion.local.sandbox'), '--unshare-all', '--die-with-parent',
                '--ro-bind', '/usr', '/usr', '--ro-bind', '/lib', '/lib', '--ro-bind', '/bin', '/bin', '--clearenv', '/usr/bin/true']);
            if (! $sandbox->successful()) {
                throw new RuntimeException('Bubblewrap requires unprivileged user namespaces for the application worker user.');
            }
            foreach ([storage_path('app/private'), storage_path('app/uploads'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
                File::ensureDirectoryExists($path, 0700);
                if (! is_writable($path)) {
                    throw new RuntimeException('Make storage and bootstrap/cache writable by the application user.');
                }
            }
            if ($this->option('after-migrations')) {
                config()->set('health.required', ['database', 'migrations', 'queue', 'meilisearch']);
                if (app(ReadinessCheck::class)->check()['status'] !== 'ok') {
                    throw new RuntimeException('Database migrations, queue or Meilisearch readiness failed.');
                }
                foreach (['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'] as $table) {
                    if (! $database->connection()->getSchemaBuilder()->hasTable($table)) {
                        throw new RuntimeException('Run migrations to create cache, queue and session tables.');
                    }
                }
                Cache::lock('runtime-check', 10)->block(1, fn () => Cache::put('runtime-check', 'ready', 10));
                if (Cache::pull('runtime-check') !== 'ready') {
                    throw new RuntimeException('Database cache verification failed.');
                }
            }
        } catch (Throwable $exception) {
            $this->error($exception instanceof RuntimeException ? $exception->getMessage() : 'Runtime validation failed. Check runtime configuration and service availability.');

            return self::FAILURE;
        }
        $this->info('Application runtime verified.');

        return self::SUCCESS;
    }
}
