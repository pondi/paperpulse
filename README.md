# PaperPulse

> [!WARNING]
> This project is under heavy development. It started as a personal need and has since evolved into an experimentation platform for AI-assisted coding and its capabilities. There are known issues, areas with improper code structure, features that will be deprecated, and minor security concerns that are being addressed. Source files are private; deletion and retention settings apply. The versioned integration API is under `/api/v1`.

Say goodbye to paper chaos forever. PaperPulse transforms your receipts and documents into organized, searchable intelligence.

## Why PaperPulse?

Stop losing receipts, missing tax deductions, and spending hours organizing paperwork. PaperPulse turns your document mess into organized, searchable intelligence that saves you time and money.

**Snap and Forget**: Just take a photo of your receipt or document. We'll read everything important and organize it automatically. No more lost paperwork or manual data entry.

**Find Anything Instantly**: Need that warranty from 2022? Or last month's coffee expense? Search by store, amount, date, or even what you bought. Everything is instantly searchable.

**Save Money at Tax Time**: Never miss another deduction. We track your spending patterns and help you spot opportunities to save money. Your accountant will thank you.

## Technical Overview

Built with Laravel 13, Inertia 3 and Vue.js 3.5, PaperPulse uses AI-powered OCR to extract structured data from documents. It provides full-text search, analytics, and multi-tenant user management.

## Requirements

Local development/testing needs only Docker with Compose. The three containers provide the application, PostgreSQL and Garage storage. Native Ubuntu production needs:

- PHP 8.5 CLI/FPM with PostgreSQL, ZIP, Imagick, GD, Intl, Mbstring, XML, cURL and BCMath extensions; CLI also needs PCNTL and POSIX
- Composer 2
- Node.js 20.19+ or 22.12+ (Vite 8)
- External PostgreSQL 17
- External Meilisearch (indexes configured by the application)
- ImageMagick, Ghostscript, LibreOffice, Bubblewrap and fonts

## External Services Required

- Gemini for the recommended file extraction pipeline
- AWS Textract and OpenAI when selecting the legacy `textract+openai` pipeline
- S3-compatible storage (Garage is included for development/testing)

## Installation

Install Docker with the Compose plugin, clone this repository, then run:

```bash
docker compose up --build -d
docker compose logs -f app
```

The image installs locked PHP/Node dependencies and builds the assets. Startup creates the application key, migrates PostgreSQL, creates private local S3 buckets and configures search. Development state lives in named volumes. No host PHP, Node, database, browser driver or service installation is needed. Open `http://localhost:8080` after the application becomes healthy.

```bash
docker compose exec app test.sh backend
docker compose exec app test.sh browser
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Tests use separate PostgreSQL databases and a restricted test role. Backend and browser tests fake external AI/OCR responses. Browser upload tests use real Garage storage, database queue workers, previews and Office conversion, including failures and retries. For real uploads outside the tests, supply provider credentials through an ignored `compose.override.yaml`. See [Getting Started](docs/getting-started.md).

### Native Ubuntu with external PostgreSQL and Meilisearch

With PHP 8.5 packages available in your configured APT repositories, install the local runtime:

```bash
sudo apt-get update
sudo apt-get install -y --no-install-recommends \
    nginx supervisor cron ca-certificates curl git unzip util-linux \
    php8.5-cli php8.5-fpm php8.5-pgsql php8.5-zip php8.5-mbstring \
    php8.5-xml php8.5-curl php8.5-bcmath php8.5-intl php8.5-gd php8.5-imagick \
    imagemagick ghostscript bubblewrap \
    libreoffice-writer libreoffice-calc libreoffice-impress \
    fonts-dejavu fonts-liberation
```

Install Composer 2 and Node.js 20.19+ or 22.12+ separately if they are not already available. Use PHP 8.5 for Composer, the web site's FPM pool and queue workers. This command installs no PostgreSQL or Meilisearch server and no browser/testing tools. LibreOffice runs headless; Bubblewrap must be allowed to create unprivileged user namespaces as the application user.

Allow ImageMagick to read PDFs for previews and extraction, then restart FPM after installing extensions:

```bash
sudo sed -i 's/rights="none" pattern="PDF"/rights="read" pattern="PDF"/' /etc/ImageMagick-*/policy.xml
sudo systemctl restart php8.5-fpm
```

Configure `.env` with `APP_ENV=production`, `APP_DEBUG=false`, an application key, the external `DB_HOST`/credentials and `MEILISEARCH_HOST`/key, both private S3 buckets, and extraction/mail credentials. Keep `QUEUE_CONNECTION=database`, `CACHE_STORE=database` and `SESSION_DRIVER=database`. Point Nginx at `public/` and make `storage/` and `bootstrap/cache/` writable by the application user. `php8.5 artisan uploads:limits --no-interaction` prints the PHP/Nginx upload limits to apply.

From the application directory, install locked dependencies and initialize database/search:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php8.5 artisan migrate:safe --force --no-interaction
php8.5 artisan meilisearch:configure --no-interaction
php8.5 artisan scout:reindex-all --no-interaction
```

The optional `php8.5 artisan runtime:check --after-migrations --no-interaction` checks PHP CLI/FPM, Office sandbox support, writable directories and database/search readiness. Installing packages does not verify live AI credentials or S3 access.

### One queue worker and one scheduler cron entry

One worker handles all six named queues, including scheduled jobs, notifications, conversions and exports:

```bash
php8.5 artisan queue:work database --queue=default,receipts,documents,conversions,files,exports --sleep=3 --tries=3 --timeout=3660 --max-time=3600 --no-interaction
```

Run one copy under Supervisor (or your existing process manager) with automatic restart and a stop wait of 3800 seconds. It processes jobs one at a time; a long conversion or export delays other queued work. More workers are an optional throughput improvement. Queue names are checked in the listed priority order.

Add one cron entry as the application user, replacing `/path/to/paperpulse` with the actual path:

```cron
* * * * * cd /path/to/paperpulse && /usr/bin/php8.5 artisan schedule:run --no-interaction >> storage/logs/scheduler.log 2>&1
```

The scheduler decides when recovery, cleanup and notification tasks run. Scheduled jobs join the same queues; the worker executes them. Keeping scheduling separate lets it run while the worker is busy. No additional scheduler daemon or worker per queue is needed. On code updates, run `php8.5 artisan queue:restart --no-interaction` so the process manager restarts the worker with the new code.

## Required Environment Variables

Compose supplies local defaults. Configure these variables in your `.env` file for native deployments:

### Application
```
APP_NAME=PaperPulse
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
```

### Database
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=paperpulse
DB_USERNAME=root
DB_PASSWORD=
```

### Cache and queues
```
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

### Search
```
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=
```

### AI Services
```
FILE_PROCESSING_PROVIDER=gemini
GEMINI_API_KEY=your-gemini-api-key
TEXT_ANALYSIS_PROVIDER=gemini
AI_DEFAULT_PROVIDER=openai
# OPENAI_API_KEY is needed when selecting OpenAI text analysis or legacy extraction.
```

### Storage and optional legacy OCR
```
TEXTRACT_KEY=your-textract-key
TEXTRACT_SECRET=your-textract-secret
TEXTRACT_REGION=eu-central-1
TEXTRACT_BUCKET=your-textract-bucket
AWS_BUCKET=paperpulse-storage
AWS_INCOMING_BUCKET=paperpulse-incoming
S3_KEY=your-s3-access-key
S3_SECRET=your-s3-secret-key
S3_REGION=us-east-1
S3_URL=
S3_ENDPOINT=
S3_USE_PATH_STYLE_ENDPOINT=false
```

### Mail
```
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_FROM_ADDRESS="hello@example.com"
```

## Usage

Start the complete local environment with `docker compose up --build -d`. Open `http://localhost:8080`; the application container runs PHP 8.5, Nginx, database queue workers, scheduler, Meilisearch, Reverb, Chromium and isolated LibreOffice. PostgreSQL 17 runs in the second container. See [Getting Started](docs/getting-started.md) for tests and configuration.

## License

MIT License
