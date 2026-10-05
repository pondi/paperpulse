# Getting Started

The lockfiles use Laravel 13, Inertia 3, Vue 3.5, Vite 8, Tailwind 3 and Pest 4/PHPUnit 12. Use PHP 8.5 and Node.js 20.19+ or 22.12+. See [Developer Guide](developer.md) for manual checks.

## Docker development and testing

Install Docker with Compose. From a clean clone:

```bash
docker compose up --build -d
docker compose logs -f app
```

Three containers run. `app` bundles PHP 8.5/FPM, Nginx, Node 22, Composer, Meilisearch, Reverb, queue workers, scheduler, Chromium/ChromeDriver, LibreOffice, Bubblewrap, ImageMagick and Ghostscript. `postgres` supplies PostgreSQL 17, and `garage` supplies private S3-compatible storage using Garage 2.3 with LMDB metadata. Garage grants bucket access to the configured key rather than using S3 ACL APIs; buckets are not exposed as public websites. The image installs locked dependencies and builds assets. Startup generates a persistent app key, migrates PostgreSQL, creates private local storage buckets and configures/reindexes search.

Open `http://localhost:8080` after `docker compose ps` shows the app healthy. Garage exposes its S3 API at `http://localhost:3900`. Nginx proxies this endpoint so signed links work in the browser and in containers. Garage uses a single local node; its admin/RPC ports stay on the Compose network; inspect it with `docker compose exec garage /garage status`. Development mail is written to application logs.

```bash
# Backend and browser suites
docker compose exec app test.sh backend
docker compose exec app test.sh browser
# A focused regression
docker compose exec app test.sh backend tests/Feature/PostgreSqlRuntimeTest.php
# Formatting, static analysis and assets
docker compose exec app vendor/bin/pint app/Providers/AppServiceProvider.php
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G --no-progress
docker compose exec app npm run build
# Stop while retaining local data
docker compose down
```

Development uses `paperpulse`; backend tests use `paperpulse_test`; browser tests use `paperpulse_browser_test`. A separate test role cannot connect to the development database. All environments use PostgreSQL; there is no SQLite fallback. Browser requests use a dedicated loopback server, without swapping the development environment or sharing its workers. Both test configurations force dummy provider credentials so development secrets cannot be inherited. Backend and browser tests fake external AI/OCR responses. Browser processing tests run by default against real Garage storage and database queues, including private previews, downloads, duplicate uploads, provider failures, retries and Office conversion. The browser suite drains its own queues with faked provider responses; it needs no separate worker process. Real Meilisearch and Office integration tests use the bundled services.

The image excludes Git metadata. Pass the PHP files you changed to Pint as above; `--dirty` is available only in a Git checkout.

Source is copied into the image. Rebuild after edits with `docker compose up --build -d`. For live editing, add an ignored `compose.override.yaml` mounting `./app`, `./resources`, `./routes` and `./tests` into the matching `/app` directories. Keep container dependencies and configuration intact. Run `docker compose exec app npm run dev -- --host=0.0.0.0` for hot reload; rebuild assets for browser tests. Provider credentials can be supplied through the override's `app.environment` when real extraction is needed.

The local Garage RPC/S3 credentials in `compose.yaml` are disposable development defaults. This single-node service has no replication and is not a production storage deployment. See [Garage single-node setup](https://garagehq.deuxfleurs.fr/documentation/quick-start/) and [S3 compatibility](https://garagehq.deuxfleurs.fr/documentation/reference-manual/s3-compatibility/).

The application container uses `seccomp=unconfined` to permit the Linux namespace syscalls needed by Bubblewrap and `systempaths=unconfined` so Docker's proc masks do not block a fresh proc mount in the sandbox's PID namespace. [Docker documents these security options](https://docs.docker.com/reference/cli/docker/container/run/#security-options). It does not use privileged mode, add capabilities or mount the Docker socket. Office runs as www-data and retains its isolated filesystem/network/processes, empty inherited environment, dropped capabilities and disabled macros. The Docker host must support unprivileged user namespaces. This Compose setup is for development/testing; native Ubuntu production is described below.

Named volumes preserve PostgreSQL, uploaded files, local object storage, search indexes and the app key. Avoid deleting volumes when retaining your local archive. Inspect managed processes with `docker compose exec app supervisorctl status`.

## Verified local acceptance

Verified on 2026-10-04 with PHP 8.5.11/PostgreSQL 17: readiness and Supervisor services, real Garage privacy/signed downloads, Meilisearch filters, isolated Office conversion and database workers, browser login/logout, and persistence across application recreation and a three-service restart. Development data stayed unchanged and the test role could not connect to it.

The 2026-10-04 acceptance run passed all 1,615 backend tests (8,236 assertions) and all 89 browser tests (232 assertions), with no failures or skipped tests. PHPStan and the frontend build also passed. A separate Compose stack initialized successfully with empty volumes, and its storage/privacy, WebSocket and browser upload tests passed. The populated file processing page's missing date formatter was fixed and covered by the browser upload flows. External provider responses remain faked in these results; live AI/OCR acceptance is separate.

## Native Ubuntu production

The [README Ubuntu setup](../README.md#native-ubuntu-with-external-postgresql-and-meilisearch) contains the APT command for local PHP and conversion binaries with external PostgreSQL 17 and Meilisearch. Use PHP 8.5 for both the site's FPM pool and queue worker. The supported baseline uses `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, and `SESSION_DRIVER=database`. Set `APP_ENV=production`, `APP_DEBUG=false`, the application key, private S3 buckets, and credentials for the selected extraction provider.

Bubblewrap must be able to create unprivileged user namespaces as the application user. LibreOffice uses private profiles, disables macros and runs without network access. Enable ImageMagick PDF reads as documented in the README. `runtime:check` is an optional diagnostic command; add `--after-migrations` to check database tables, cache locks and search readiness.

Install locked dependencies, build assets, run `migrate:safe --force`, configure/reindex search during initial setup, and restart the worker on code updates. Back up the database and source storage before upgrading existing sites.

Run one supervised worker on the `database` connection for `default,receipts,documents,conversions,files,exports`, with timeout 3660 seconds, three tries, and process-manager stop wait 3800 seconds. These are queue names, not six processes. Queue reservations last at least 3720 seconds. Monitor backlog/failed jobs with `php8.5 artisan queue:health`. Use `queue:failed` and `queue:retry` for failures; `queue:restart` allows active jobs to finish during deployment.

Use the single scheduler cron entry in the [README](../README.md#one-queue-worker-and-one-scheduler-cron-entry). Recovery, retention, organization and notifications are registered in `routes/console.php`. Scheduled jobs use the same worker; the cron entry keeps scheduling independent of long-running processing.

Reverb is optional. Start with `BROADCAST_CONNECTION=log`; browser notification polling remains available. Enable Reverb only after configuring its daemon, TLS proxy and browser environment. Keep `REVERB_SCALING_ENABLED=false`: scaling requires Redis and is outside the supported runtime.

Manual smoke verification uses `tests/Feature/ForgeProcessingSmokeTest.php` with `PAPERPULSE_OFFICE_RUNTIME=1` and an isolated PostgreSQL database. It exercises real Office conversion, previews, database workers, cache locks, downloads and queued notifications; Gemini and object storage are faked. Live API credentials and production bucket access must be checked on the deployed site.

Run deterministic provider acceptance with `docker compose exec app test.sh backend tests/Feature/OCR/TextractProviderTest.php tests/Feature/Files/GeminiProcessingTest.php tests/Feature/ForgeProcessingSmokeTest.php`. These cover Textract success, provider failure and invalid input, Gemini receipt/document processing and rejection, and an Office upload through database workers to owned downloads with external services mocked.

Live-provider acceptance is opt-in and separate from these test runners, which always force dummy credentials. Use a separately configured acceptance deployment with an isolated PostgreSQL database, private test buckets, a test account, credentials for the selected provider and its queue workers. Upload the receipt image/PDF and document PDF from `tests/Browser/fixtures`, require completed processing and the expected owned entity/download, then remove the test data. `test.sh browser` always fakes AI responses while exercising the local storage and processing pipeline. Live Textract/Gemini access and provider-specific storage permissions remain prerequisites, not outcomes of mocked tests.

ZIP exports require `ext-zip` in both PHP 8.5 CLI and FPM. The README APT command includes `php8.5-zip`; restart the site's FPM service after installing extensions. `runtime:check` checks both runtimes. Empty selections are rejected; selected missing assets are omitted, and an all-missing download returns a valid empty ZIP.

Large PDF and ZIP exports use the `exports` queue and private local artifacts. Ghostscript joins bounded PDF parts; `exports:cleanup` runs hourly through the scheduler. Monitor progress at `/exports`. Defaults are 50 immediate records, two active jobs per owner and a 24-hour artifact lifetime (`config/exports.php`).
