# Getting Started

This guide walks through setting up Paperpulse for local development, explaining each component and its role in the system.

## System Requirements

Before installing Paperpulse, ensure your development environment meets these requirements:

- **PHP 8.2+** with extensions: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- **Composer** for managing PHP dependencies
- **Node.js 18+** and npm for building the Vue.js frontend
- **PostgreSQL 14+**, MySQL 8.0+, or SQLite for data storage
- **Redis 6.0+** for queue management and caching
- **Meilisearch 1.0+** for full-text search capabilities
- Optional: **AWS S3** or compatible object storage for cloud file storage

## Understanding the Architecture

### Core Services

**Redis** - The Message Broker
Redis acts as the central nervous system for background processing. When users upload files, the main application quickly stores them and creates a "job" in Redis. Worker processes monitor Redis for new jobs and process files asynchronously, ensuring the web interface remains responsive even during heavy processing loads.

**Meilisearch** - Search Engine
Meilisearch provides lightning-fast full-text search across all documents. When documents are processed, their content and metadata are indexed in Meilisearch, allowing users to find documents instantly by searching for any text within them.

**Laravel Queues** - Background Processing
The queue system handles all heavy lifting in the background:
- File processing and OCR extraction
- AI analysis for data extraction
- Image thumbnail generation
- Email notifications
- Cleanup tasks

## Installation Process

### 1. Clone the Repository

```bash
git clone https://github.com/pondi/paperpulse.git
cd paperpulse
```

This creates your local development copy of the Paperpulse codebase.

### 2. Install PHP Dependencies

```bash
composer install
```

Composer reads the `composer.json` file and installs all PHP packages required by Laravel and Paperpulse. This includes the Laravel framework, database drivers, queue libraries, and third-party integrations.

### 3. Install Frontend Dependencies

```bash
npm install
```

NPM installs Vue.js, Inertia.js, and all JavaScript libraries needed for the user interface. These packages enable the reactive, single-page application experience.

### 4. Configure Your Environment

```bash
cp .env.example .env
```

The `.env` file contains all configuration settings. This file is never committed to version control as it contains sensitive information. Key settings to configure:

#### Database Configuration
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=paperpulse
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

The database stores all application data including users, documents, extracted data, and system configuration. PostgreSQL is recommended for production use.

#### Redis Configuration
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis
```

Redis must be running for the application to function properly. It manages:
- Background job queues
- Application cache
- Session storage (optional)
- Real-time broadcasting (if enabled)

#### Queue Configuration
```env
QUEUE_CONNECTION=redis
```

This tells Laravel to use Redis for queue management. Jobs are pushed to Redis and processed by worker processes.

#### Storage Configuration
```env
FILESYSTEM_DISK=local
```

For production, use S3:
```env
FILESYSTEM_DISK=s3
S3_KEY=your-s3-access-key
S3_SECRET=your-s3-secret-key
S3_REGION=us-east-1
AWS_BUCKET=paperpulse-storage
AWS_INCOMING_BUCKET=paperpulse-incoming
S3_URL=
S3_ENDPOINT=
S3_USE_PATH_STYLE_ENDPOINT=false
```

Files are stored with generated GUIDs to prevent naming conflicts and improve security.

#### Search Configuration
```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=masterKey
```

Meilisearch must be running and accessible. The master key secures admin operations.

### 5. Generate Application Key

```bash
php artisan key:generate
```

This command generates a unique encryption key for your application. Laravel uses this key to encrypt sessions, cookies, and other sensitive data. The key is automatically added to your `.env` file. Never share this key or commit it to version control.

### 6. Initialize the Database

```bash
php artisan migrate
```

Migrations create all database tables and relationships. This command:
- Creates user tables for authentication
- Sets up document and receipt storage tables
- Creates tables for jobs and failed jobs
- Establishes indexes for performance
- Sets up relationship constraints

Each migration is versioned and tracked, allowing you to roll back changes if needed.

### 7. Optional: Load Sample Data

```bash
php artisan db:seed
```

Seeders populate the database with sample data for testing. This typically includes:
- Test user accounts
- Sample documents and receipts
- Configuration data
- Demo file shares

### 8. Build Frontend Assets

```bash
npm run build
```

This command compiles and optimizes all frontend code:
- Transpiles modern JavaScript for browser compatibility
- Compiles Vue.js components
- Processes CSS with PostCSS
- Minifies code for production
- Generates source maps for debugging

For active development with automatic recompilation:
```bash
npm run dev
```

This starts a development server that watches for file changes and automatically rebuilds assets, enabling hot module replacement for instant updates.

## Running the Application

### Start the Web Server

```bash
php artisan serve
```

This starts PHP's built-in development server on `http://localhost:8000`. The server:
- Handles HTTP requests
- Routes to appropriate controllers
- Serves static assets
- Manages sessions

### Start the Queue Worker

In a new terminal window:
```bash
php artisan queue:work
```

The queue worker is essential for file processing. It:
- Monitors Redis for new jobs
- Processes uploaded files through OCR
- Calls AI services for data extraction
- Updates search indexes
- Handles email notifications

Without the queue worker running, uploaded files will not be processed.

### Start the Task Scheduler

In another terminal window:
```bash
php artisan schedule:work
```

The scheduler runs periodic maintenance tasks:
- Cleans up expired file shares
- Removes old temporary files
- Updates search indexes
- Sends digest emails
- Performs system health checks

## Service Setup Details

### Setting Up Meilisearch

1. **Install Meilisearch** following their official documentation
2. **Start the service** with a master key for security
3. **Create indexes** by running:
   ```bash
   php artisan scout:reindex-all
   ```
   This creates search indexes for all document types

Meilisearch provides:
- Typo tolerance (finds "recipt" when searching "receipt")
- Instant search-as-you-type results
- Faceted search for filtering
- Relevancy ranking

### Setting Up Redis

1. **Install Redis** via your package manager
2. **Start Redis** service:
   ```bash
   redis-server
   ```
3. **Verify connection**:
   ```bash
   redis-cli ping
   ```
   Should return `PONG`

Redis stores jobs in lists and provides atomic operations for reliable job processing.

### Understanding Laravel Jobs

When a file is uploaded:

1. **Job Creation**: A job is created with file details and queued in Redis
2. **Worker Processing**: An idle worker picks up the job
3. **File Processing**: The worker:
   - Downloads the file from storage
   - Extracts text via OCR
   - Sends text to AI for analysis
   - Stores extracted data
   - Indexes content in Meilisearch
4. **Completion**: Job is marked complete and removed from queue

Failed jobs are moved to a failed jobs table for debugging and retry.

## Verifying Your Setup

### Test Infrastructure Connectivity

```bash
php artisan test:infrastructure
```

This command verifies:
- Database connection and permissions
- Redis availability and configuration
- S3 bucket access (if configured)
- Meilisearch connectivity
- Mail configuration

### Test File Processing

```bash
php artisan test:file-simple --user-id=1
```

This creates a test file and processes it through the entire pipeline, verifying:
- File upload to storage
- Job queue creation
- OCR processing
- AI integration
- Search indexing

### Monitor Queue Health

```bash
php artisan queue:health
```

Shows current queue status:
- Number of pending jobs
- Processing rate
- Failed job count
- Worker status

## Common Issues and Solutions

### Queue Jobs Not Processing

**Problem**: Files upload but never get processed

**Solution**: Ensure the queue worker is running:
```bash
php artisan queue:work --verbose
```

The `--verbose` flag shows detailed processing information.

### Search Not Working

**Problem**: Documents aren't searchable after processing

**Solution**: Rebuild search indexes:
```bash
php artisan scout:reindex-all --fresh
```

This deletes and recreates all indexes with current data.

### Storage Permission Errors

**Problem**: Cannot write to storage directories

**Solution**: Fix directory permissions:
```bash
chmod -R 775 storage bootstrap/cache
```

Laravel needs write access to store logs, cache, and temporary files.

### Database Connection Failed

**Problem**: Cannot connect to database

**Solution**: 
1. Verify database service is running
2. Check credentials in `.env`
3. Ensure database exists
4. Test connection: `php artisan tinker` then `DB::connection()->getPdo();`

### Memory Errors During Processing

**Problem**: Large files cause out-of-memory errors

**Solution**: Increase PHP memory limit in `php.ini`:
```ini
memory_limit = 256M
```

Or configure queue workers with limited memory:
```bash
php artisan queue:work --memory=128
```
