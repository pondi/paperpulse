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

- PHP 8.4 with the extensions checked by `forge:preflight` (including ZIP)
- Composer
- Node.js 20.19+ or 22.12+ (Vite 8)
- PostgreSQL 17
- Meilisearch (indexes configured by the application)
- ImageMagick, Ghostscript, LibreOffice, Bubblewrap and fonts (native Ubuntu installer)

## External Services Required

- Gemini for the recommended file extraction pipeline
- AWS Textract and OpenAI when selecting the legacy `textract+openai` pipeline
- S3-compatible storage

## Installation

1. From a repository checkout, install dependencies:
```bash
cd paperpulse
composer install
npm ci
```

2. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

3. Setup database and search:
```bash
php artisan migrate:safe --no-interaction
php artisan meilisearch:configure --no-interaction
php artisan scout:reindex-all --no-interaction
```

4. Build assets and start:
```bash
npm run build
npm run dev
php artisan queue:work database --queue=default,receipts,documents,conversions,files,exports --timeout=3660
```

Native production installation, FPM/CLI checks, worker settings and scheduler setup are described in [Getting Started](docs/getting-started.md). See [Developer Guide](docs/developer.md) for the tested package baseline and manual checks.

## Required Environment Variables

Configure these variables in your `.env` file:

### Application
```
APP_NAME=PaperPulse
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://paperpulse.test
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
MEILISEARCH_KEY=LARAVEL-HERD
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

Laravel Herd serves the local site. Start assets and workers with `npm run dev` and `php artisan queue:work database --queue=default,receipts,documents,conversions,files,exports --timeout=3660`. Access the web interface to upload and manage receipts. The system automatically processes documents using OCR and AI extraction.

## License

MIT License
