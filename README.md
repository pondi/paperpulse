# PaperPulse

Say goodbye to paper chaos forever. PaperPulse transforms your receipts and documents into organized, searchable intelligence.

## Why PaperPulse?

Stop losing receipts, missing tax deductions, and spending hours organizing paperwork. PaperPulse turns your document mess into organized, searchable intelligence that saves you time and money.

**Snap and Forget**: Just take a photo of your receipt or document. We'll read everything important and organize it automatically. No more lost paperwork or manual data entry.

**Find Anything Instantly**: Need that warranty from 2022? Or last month's coffee expense? Search by store, amount, date, or even what you bought. Everything is instantly searchable.

**Save Money at Tax Time**: Never miss another deduction. We track your spending patterns and help you spot opportunities to save money. Your accountant will thank you.

## Technical Overview

Built with Laravel 11 and Vue.js 3, PaperPulse uses AI-powered OCR to extract structured data from documents. It provides full-text search, analytics, and multi-tenant user management.

## Requirements

- PHP >= 8.2
- Composer
- Node.js >= 18.x
- PostgreSQL >= 14
- Redis >= 6.x
- Meilisearch >= 1.0
- ImageMagick (for PDF processing)

## External Services Required

- AWS Textract (for OCR)
- OpenAI API (for data extraction)
- S3-compatible storage

## Installation

1. Clone and install dependencies:
```bash
git clone https://github.com/yourusername/paperpulse.git
cd paperpulse
composer install
npm install
```

2. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

3. Setup database and search:
```bash
php artisan migrate
php artisan scout:import "App\Models\Receipt"
php artisan scout:import "App\Models\LineItem"
```

4. Build assets and start:
```bash
npm run build
php artisan serve
php artisan horizon
```

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

### Redis
```
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### Search
```
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=LARAVEL-HERD
```

### AI Services
```
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...your-openai-api-key
```

### AWS/OCR
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

Start the application with `php artisan serve` and `php artisan horizon`. Access the web interface to upload and manage receipts. The system automatically processes documents using OCR and AI extraction.

## License

MIT License
