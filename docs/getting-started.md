# Getting Started

Install Composer and npm dependencies, copy `.env.example` to `.env`, set the PostgreSQL database and private S3 buckets, configure the extraction provider and Meilisearch, and generate the application key with `php artisan key:generate --no-interaction`.

Run `php artisan migrate:safe --no-interaction`, `php artisan meilisearch:configure --no-interaction`, and `php artisan scout:reindex-all --no-interaction`. Laravel Herd serves the local site; use `npm run dev` for assets and standard Laravel database workers for processing. On macOS, perform Office conversion acceptance checks in an Ubuntu runtime with LibreOffice and Bubblewrap.

## Native Forge production

Select PHP 8.4 for both the site's FPM pool and queue workers, PostgreSQL 17, and a local Meilisearch service. The supported baseline uses `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, and `SESSION_DRIVER=database`. Set `APP_ENV=production`, `APP_DEBUG=false`, the application key, private S3 buckets, and credentials for the selected extraction provider.

Run `sudo sh deploy/install-forge-runtime.sh` from the project root, then run `php8.4 artisan forge:preflight --no-interaction` as the Forge user. Bubblewrap must be able to create unprivileged user namespaces; retain its filesystem and network isolation. LibreOffice uses private profiles, disables macros and runs without network access. The installer enables only ImageMagick PDF reads for extraction coverage and previews.

Use `sh deploy/forge-deploy.sh` as the Forge deployment script after the repository checkout. It builds assets, validates runtime compatibility, applies locked migrations, caches configuration, verifies migrated services, and gracefully restarts workers. Back up the database and source storage before upgrading existing sites.

Create standard Forge queue workers on the `database` connection for `default,receipts,documents,conversions,files,exports`, with timeout 3660 seconds, three tries, and process-manager stop wait 3800 seconds. `deploy/forge-worker.conf` is the equivalent Supervisor configuration; replace its site path before installation. Queue reservations last at least 3720 seconds. Monitor worker processes in Forge and backlog/failed jobs with `php8.4 artisan queue:health`. Use `queue:failed` and `queue:retry` for failures; `queue:restart` allows active jobs to finish during deployment.

Configure a Forge scheduled task to run `php8.4 /home/forge/paperpulse/artisan schedule:run` every minute. Use the site's actual path. Recovery, retention, organization and notifications are registered in `routes/console.php`.

Reverb is optional. Start with `BROADCAST_CONNECTION=log`; browser notification polling remains available. Enable Reverb only after configuring its daemon, TLS proxy and browser environment. Keep `REVERB_SCALING_ENABLED=false`: scaling requires Redis and is rejected by the Forge preflight.

Manual smoke verification uses `tests/Feature/ForgeProcessingSmokeTest.php` with `PAPERPULSE_OFFICE_RUNTIME=1` and an isolated PostgreSQL database. It exercises real Office conversion, previews, database workers, cache locks, downloads and queued notifications; Gemini and object storage are faked. Live API credentials and production bucket access must be checked on the deployed site.

ZIP exports require `ext-zip` in both PHP 8.4 CLI and FPM. The native installer enables `php8.4-zip`; restart the site's FPM service after installing extensions. `forge:preflight` checks both runtimes. Empty selections are rejected; selected missing assets are omitted, and an all-missing download returns a valid empty ZIP.
