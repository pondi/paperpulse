# Getting Started

The lockfiles use Laravel 13, Inertia 3, Vue 3.5, Vite 8, Tailwind 3 and Pest 4/PHPUnit 12. Use PHP 8.4 and Node.js 20.19+ or 22.12+. See [Developer Guide](developer.md) for manual checks.

Run `composer install` and `npm ci`, then copy `.env.example` to `.env`. Configure PostgreSQL, private S3 buckets and Meilisearch. For Gemini extraction, set `FILE_PROCESSING_PROVIDER=gemini` and `GEMINI_API_KEY`; choose `TEXT_ANALYSIS_PROVIDER` separately. Generate the application key with `php artisan key:generate --no-interaction`.

Run `php artisan migrate:safe --no-interaction`, `php artisan meilisearch:configure --no-interaction`, and `php artisan scout:reindex-all --no-interaction`. Laravel Herd serves the local site; use `npm run dev` for assets and standard Laravel database workers for processing. On macOS, perform Office conversion acceptance checks in an Ubuntu runtime with LibreOffice and Bubblewrap.

## Native Forge production

Select PHP 8.4 for both the site's FPM pool and queue workers, PostgreSQL 17, and a local Meilisearch service. The supported baseline uses `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, and `SESSION_DRIVER=database`. Set `APP_ENV=production`, `APP_DEBUG=false`, the application key, private S3 buckets, and credentials for the selected extraction provider.

Run `sudo sh deploy/install-forge-runtime.sh` from the project root, then run `php8.4 artisan forge:preflight --no-interaction` as the Forge user. Bubblewrap must be able to create unprivileged user namespaces; retain its filesystem and network isolation. LibreOffice uses private profiles, disables macros and runs without network access. The installer enables only ImageMagick PDF reads for extraction coverage and previews.

Use `sh deploy/forge-deploy.sh` as the Forge deployment script after the repository checkout. It builds assets, validates runtime compatibility, applies locked migrations, caches configuration, verifies migrated services, and gracefully restarts workers. Back up the database and source storage before upgrading existing sites.

Create standard Forge queue workers on the `database` connection for `default,receipts,documents,conversions,files,exports`, with timeout 3660 seconds, three tries, and process-manager stop wait 3800 seconds. `deploy/forge-worker.conf` is the equivalent Supervisor configuration; replace its site path before installation. Queue reservations last at least 3720 seconds. Monitor worker processes in Forge and backlog/failed jobs with `php8.4 artisan queue:health`. Use `queue:failed` and `queue:retry` for failures; `queue:restart` allows active jobs to finish during deployment.

Configure a Forge scheduled task to run `php8.4 /home/forge/paperpulse/artisan schedule:run` every minute. Use the site's actual path. Recovery, retention, organization and notifications are registered in `routes/console.php`.

Reverb is optional. Start with `BROADCAST_CONNECTION=log`; browser notification polling remains available. Enable Reverb only after configuring its daemon, TLS proxy and browser environment. Keep `REVERB_SCALING_ENABLED=false`: scaling requires Redis and is rejected by the Forge preflight.

Manual smoke verification uses `tests/Feature/ForgeProcessingSmokeTest.php` with `PAPERPULSE_OFFICE_RUNTIME=1` and an isolated PostgreSQL database. It exercises real Office conversion, previews, database workers, cache locks, downloads and queued notifications; Gemini and object storage are faked. Live API credentials and production bucket access must be checked on the deployed site.

Run deterministic provider acceptance with `docker compose exec app test.sh backend tests/Feature/OCR/TextractProviderTest.php tests/Feature/Files/GeminiProcessingTest.php tests/Feature/ForgeProcessingSmokeTest.php`. These cover Textract success, provider failure and invalid input, Gemini receipt/document processing and rejection, and an Office upload through database workers to owned downloads with external services mocked.

Live-provider acceptance is opt-in and separate from these test runners, which always force dummy credentials. Use a separately configured acceptance deployment with an isolated PostgreSQL database, private test buckets, a test account, credentials for the selected provider and its queue workers. Upload the receipt image/PDF and document PDF from `tests/Browser/fixtures`, require completed processing and the expected owned entity/download, then remove the test data. The browser `processing` group describes these live flows and remains excluded from the standard browser runner; enabling that group alone does not configure real credentials or workers. Live Textract/Gemini access and provider-specific storage permissions remain prerequisites, not outcomes of mocked tests.

ZIP exports require `ext-zip` in both PHP 8.4 CLI and FPM. The native installer enables `php8.4-zip`; restart the site's FPM service after installing extensions. `forge:preflight` checks both runtimes. Empty selections are rejected; selected missing assets are omitted, and an all-missing download returns a valid empty ZIP.

Large PDF and ZIP exports use the `exports` queue and private local artifacts. Ghostscript joins bounded PDF parts; `exports:cleanup` runs hourly through the scheduler. Monitor progress at `/exports`. Defaults are 50 immediate records, two active jobs per owner and a 24-hour artifact lifetime (`config/exports.php`).
