# Developer Guide

This guide covers the installed package baseline, development practices and native deployment. Composer and npm lockfiles define exact versions; runtime setup uses PHP 8.5 and PostgreSQL 17.

| Component | Supported baseline |
| --- | --- |
| PHP | 8.5; ZIP and other extensions checked by `runtime:check` |
| Laravel | 13 |
| Inertia server/client | 3 |
| Vue | 3.5 |
| Vite / Vue plugin | 8 / 6 |
| Node.js | 20.19+ or 22.12+ |
| Tailwind CSS | 3 |
| Pest / PHPUnit | 4 / 12 |
| Larastan | 3 |
| PostgreSQL | 17 |

Use `docker compose up --build -d` for shared development and testing. The application image installs locked Composer/npm dependencies, builds assets, and bundles local services and processing tools; PostgreSQL 17 and Garage S3 storage run in the two separate service containers. See [Getting Started](getting-started.md) for tests, live editing and native Ubuntu production.

## Code Organization

### Directory Structure

```
paperpulse/
├── app/                    # Application code
│   ├── Console/           # Console commands
│   ├── Http/              # Controllers, middleware, requests
│   ├── Jobs/              # Background jobs
│   ├── Models/            # Eloquent models
│   ├── Services/          # Business logic services
│   └── Traits/            # Reusable traits
├── database/              # Database files
│   ├── migrations/        # Schema migrations
│   └── seeders/           # Data seeders
├── resources/             # Frontend resources
│   ├── js/               # Vue components and JavaScript
│   └── views/            # Blade templates
├── routes/               # Route definitions
├── storage/              # Storage directory
└── tests/                # Test files
```

### Key Design Patterns

**Service Layer Pattern**
Business logic is encapsulated in service classes under `app/Services/`. Controllers remain thin, delegating complex operations to services.

**Repository Pattern** 
Data access logic is abstracted when needed, though Eloquent models handle most database interactions directly.

**Job Pattern**
Long-running processes are handled by queued jobs to maintain application responsiveness.

## Core Concepts

### User Scoping with BelongsToUser Trait

All user-owned models must use the `BelongsToUser` trait for automatic scoping:

```php
class Receipt extends Model
{
    use BelongsToUser;
    
    // Automatically scoped to authenticated user
}
```

This trait:
- Adds global scope filtering by user_id
- Applies only during authenticated HTTP requests
- Requires explicit `user_id` filtering in queue jobs and console commands; policies and owned validation rules enforce access
- Automatically sets user_id on creation

### File Processing Pipeline

1. `FileProcessingService` validates uploads and stores the original in private source storage.
2. `FileJobChainDispatcher` persists the selected processing plan and queues preprocessing.
3. Office inputs cross an asynchronous database-queue conversion barrier using isolated LibreOffice.
4. Gemini classifies and extracts supported entities; the legacy path uses Textract OCR and OpenAI text extraction.
5. Entity factories persist owned records and junctions transactionally; replacement processing preserves the prior entities until it succeeds.
6. Search reindexing follows committed changes. Stage metadata, coverage and owner review remain on the file.

### AI Service Abstraction

Resolve the existing text-extraction contract when working on the legacy pipeline:

```php
$aiService = app(AIService::class);
$result = $aiService->analyzeReceipt($text);
```

`AIService` is bound to the OpenAI implementation. File pipeline selection uses `FILE_PROCESSING_PROVIDER`; text analysis uses `TEXT_ANALYSIS_PROVIDER`. Use the existing provider and extractor factories for their respective tasks.

Run PHP, Composer and Node commands in the application container: `docker compose exec app sh`. The examples below assume that shell unless they start with `docker compose`.

## Development Workflow

### Setting Up Development Environment

1. Fork and clone the repository
2. Create feature branch from `main`
3. Start the shared runtime with `docker compose up --build -d`
4. Run tests to verify setup
5. Make changes with tests
6. Submit pull request

### Running Tests

```bash
# Run the directly affected test file
test.sh backend tests/Feature/ProcessingCapabilitiesTest.php

# Run one affected flow
test.sh backend --filter="preserves the last pipeline"
```

### Code Style

Follow PSR-12 coding standards. Use the provided formatter:

```bash
vendor/bin/pint app/Providers/AppServiceProvider.php
```

Pass your changed PHP files to Pint. In a native Git checkout, use `vendor/bin/pint --dirty`; Git metadata is excluded from the Docker image.

Static analysis with PHPStan:

```bash
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

The PHPStan baseline contains reviewed, exact-message, path and count entries for legacy inference debt. New findings still fail. Run analysis manually before committing relevant backend changes; do not regenerate the baseline blindly. Laravel 13 casts are parsed from `casts()` methods. This is a local manual check, without a new PR gate.

## Extending Functionality

### Adding New File Types

1. Check `FileValidationService`, upload configuration and `FileProcessingCapabilities`.
2. Extend conversion capabilities only when the selected provider cannot process the input natively.
3. For a new extraction kind, register its existing-style extractor and entity factory.
4. Add focused success and rejection tests with external services faked.

### Creating Custom AI Providers

1. Implement the applicable existing AI contract.
2. Add configuration to `config/ai.php`.
3. Register the implementation in `AppServiceProvider` or the existing extractor factory.
4. Add environment variables

### Adding Console Commands

1. Generate command: `php artisan make:command CommandName --no-interaction`
2. Implement logic in `handle()` method
3. Commands in `app/Console/Commands/` register automatically; schedule work in `routes/console.php`
4. Document in `docs/cli.md`

## Frontend Development

### Vue.js Components

Components live in `resources/js/Components/`. Follow conventions:

- Use Composition API for new components
- Implement proper TypeScript types
- Use Tailwind CSS for styling
- Follow single-file component structure

### Inertia.js Pages

Page components in `resources/js/Pages/` map to routes:

```php
return Inertia::render('Receipts/Index', [
    'receipts' => $receipts
]);
```

### Building Assets

Development build with hot reload:
```bash
npm run dev
```

Production build:
```bash
npm run build
```

## API Development

### Creating API Endpoints

1. Add route in `routes/api.php`
2. Create controller in `app/Http/Controllers/Api/`
3. Use API resources for responses
4. Add authentication middleware
5. Document endpoint

### API Authentication

API uses Sanctum for token authentication:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
});
```

## Database Management

### Creating Migrations

```bash
php artisan make:migration create_table_name
```

Always include:
- Proper indexes for query performance
- Foreign key constraints
- Soft deletes where appropriate

### Seeding Data

Create seeders for test data:

```bash
php artisan make:seeder NameSeeder
```

Run specific seeder:
```bash
php artisan db:seed --class=NameSeeder
```

## Performance Considerations

### Query Optimization

- Use eager loading to prevent N+1 queries
- Add database indexes for frequent queries
- Use query scopes for reusable filters
- Cache expensive queries with the database cache

### Job Optimization

- Chunk large datasets in jobs
- Use job batching for bulk operations
- Implement job timeouts
- Add retry logic with exponential backoff

### Storage Optimization

- Store files with GUID names
- Use appropriate storage disks
- Implement file cleanup jobs
- Compress large files before storage

## Security Best Practices

### Data Protection

- Authorize resource IDs with policies and owned validation rules
- Use UUIDs for public identifiers
- Sanitize all user input
- Implement rate limiting

### Authentication & Authorization

- Use Laravel policies for authorization
- Keep administrator-only routes behind the existing admin middleware
- Rotate API keys regularly
- Log authentication events

### File Handling

- Validate file types and sizes
- Detect uploaded content types and enforce processing limits
- Store files outside public directory
- Use signed URLs for temporary access

## Monitoring and Debugging

### Logging

Use Laravel's logging facade:

```php
Log::info('Processing file', ['file_id' => $file->id]);
```

### Debugging Tools

- Laravel Debugbar for query analysis
- Xdebug for step debugging

### Performance Monitoring

- Monitor queue depths
- Track job processing times
- Alert on high failure rates
- Log slow queries

## Documentation

### Building Documentation

The documentation uses MkDocs Material. Build it with the local tooling described below.

#### Local Installation

Install the documentation tooling locally:

```bash
# Using Homebrew on macOS
brew install mkdocs
pipx install mkdocs-material

# Or using pip in a virtual environment
python3 -m venv venv
source venv/bin/activate
pip install mkdocs-material
```

Then use standard MkDocs commands:

```bash
mkdocs build      # Build documentation
mkdocs serve      # Live preview
```

#### Documentation Structure

- `mkdocs.yml` - Configuration file with Material theme settings
- `docs/` - Markdown source files
- `public/docs/` - Built HTML output (git-ignored)
- `docs/assets/` - Images and other static files

#### Material Theme Features

The Material theme provides:

- **Dark/Light Mode Toggle** - Automatic theme switching
- **Search** - Built-in search with highlighting
- **Navigation** - Tabs, sections, and breadcrumbs
- **Code Blocks** - Syntax highlighting with copy button
- **Admonitions** - Note, warning, and info boxes
- **Mobile Responsive** - Optimized for all devices

#### Writing Documentation

##### Admonitions

Use admonitions for important information:

```markdown
!!! note "Important Note"
    This is a note with a custom title.

!!! warning
    This is a warning without a custom title.

!!! tip
    This is a helpful tip.

!!! danger
    This indicates danger or destructive actions.
```

##### Code Blocks

Add syntax highlighting and line numbers:

```markdown
``` php linenums="1"
class Receipt extends Model
{
    use BelongsToUser;
}
```
```

##### Tabs

Group related content:

```markdown
=== "PHP"

    ``` php
    $user = User::find(1);
    ```

=== "JavaScript"

    ``` javascript
    const user = await api.getUser(1);
    ```
```

##### Task Lists

```markdown
- [x] Completed task
- [ ] Pending task
- [ ] Another pending task
```

#### Documentation Best Practices

1. **Keep it Current** - Update docs with every feature change
2. **Use Examples** - Include real-world code examples
3. **Add Context** - Explain why, not just how
4. **Test Commands** - Verify all commands work as documented
5. **Use Visuals** - Add diagrams for complex concepts
6. **Cross-Reference** - Link between related topics
7. **Version Notes** - Document version-specific features

## Deployment

### Production Checklist

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Configure proper database
- Configure PostgreSQL database cache and queues
- Configure S3 storage
- Set up queue workers
- Configure SSL certificates
- Set up monitoring
- Configure backups

### Environment Variables

Critical production variables:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
REVERB_SCALING_ENABLED=false
```

`FILESYSTEM_DISK` covers framework-local storage; application originals use the private `paperpulse` disk. Scanner input uses `pulsedav`, and desktop bulk input uses `uplink`, both backed by the incoming S3 bucket. Set `S3_KEY`, `S3_SECRET`, `S3_REGION`, `AWS_BUCKET` and `AWS_INCOMING_BUCKET`; custom S3 endpoints retain TLS verification.

### Queue Worker Configuration

Use the [README Ubuntu setup](../README.md#native-ubuntu-with-external-postgresql-and-meilisearch) for local binaries and external database/search configuration. One database worker can process all supported queues: `default,receipts,documents,conversions,files,exports`. A single cron entry runs the scheduler every minute. Operation timeout must remain below job timeout, worker timeout, and queue retry window. Use `queue:restart` for graceful worker restarts.

## Troubleshooting Development

### Common Issues

**Composer dependency conflicts**
```bash
composer install
```

**NPM build failures**
```bash
npm ci
npm run build
```

**Test database migration issues**
```bash
test.sh backend tests/Feature/PostgreSqlRuntimeTest.php
```

The test runner selects the isolated test database. Apply development migrations with `php artisan migrate:safe --no-interaction`; do not reset development data to diagnose tests.

**Cache problems**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Contributing

### Pull Request Process

1. Create feature branch
2. Write tests for new features
3. Ensure all tests pass
4. Update documentation
5. Submit PR with clear description
6. Address review feedback

### Code Review Criteria

- Follows coding standards
- Includes appropriate tests
- Updates documentation
- Maintains backward compatibility
- Implements proper error handling
- Uses existing patterns

### Commit Messages

Follow conventional commits:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `style:` Formatting
- `refactor:` Code restructuring
- `test:` Test additions
- `chore:` Maintenance
## Populated PostgreSQL upgrades

Back up PostgreSQL and stored source files before `php artisan migrate:safe --force --no-interaction`. Tenant reference backfills process bounded batches; PostgreSQL migration transactions roll back failed backfills so they can be rerun. The forward tenant repair migration also corrects foreign merchant/vendor references in already upgraded archives.

Tag migration uses SQL to merge entity memberships into owned file memberships without loading the pivot into PHP. Orphan references and foreign-owner tags are excluded. Merging tags and tenant copies is irreversible: restore the pre-upgrade backup if a rollback is required. Previously discarded memberships cannot be reconstructed from the migrated table.
