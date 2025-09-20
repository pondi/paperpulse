# Developer Guide

This guide covers development practices, code organization, and how to extend Paperpulse functionality.

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
- Prevents cross-user data access
- Automatically sets user_id on creation

### File Processing Pipeline

1. **Upload Handler** receives file and creates `UploadedFile` record
2. **ProcessFileJob** queued for asynchronous processing
3. **FileProcessor** service orchestrates:
   - Storage service saves file to S3/local
   - OCR service extracts text
   - AI service analyzes content
   - Data service stores results
4. **IndexingJob** updates search indexes
5. **NotificationJob** alerts user of completion

### AI Service Abstraction

Never use AI providers directly. Always use the AIService factory:

```php
$aiService = app(AIService::class);
$result = $aiService->processReceipt($text);
```

This abstraction allows switching providers without code changes.

## Development Workflow

### Setting Up Development Environment

1. Fork and clone the repository
2. Create feature branch from `main`
3. Install dependencies and configure `.env`
4. Run tests to verify setup
5. Make changes with tests
6. Submit pull request

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Code Style

Follow PSR-12 coding standards. Use the provided formatter:

```bash
./vendor/bin/pint
```

Static analysis with PHPStan:

```bash
./vendor/bin/phpstan analyse
```

## Extending Functionality

### Adding New File Types

1. Create processor class implementing `FileProcessorInterface`
2. Register in `FileProcessorFactory`
3. Add MIME type mapping
4. Create tests

### Creating Custom AI Providers

1. Implement `AIProviderInterface`
2. Add configuration to `config/ai.php`
3. Register in `AIServiceProvider`
4. Add environment variables

### Adding Console Commands

1. Generate command: `php artisan make:command CommandName`
2. Implement logic in `handle()` method
3. Add to `app/Console/Kernel.php` if needed
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
- Cache expensive queries with Redis

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

- Never expose user IDs in URLs
- Use UUIDs for public identifiers
- Sanitize all user input
- Implement rate limiting

### Authentication & Authorization

- Use Laravel policies for authorization
- Implement 2FA for admin accounts
- Rotate API keys regularly
- Log authentication events

### File Handling

- Validate file types and sizes
- Scan uploads for malware
- Store files outside public directory
- Use signed URLs for temporary access

## Monitoring and Debugging

### Logging

Use Laravel's logging facade:

```php
Log::info('Processing file', ['file_id' => $file->id]);
```

### Debugging Tools

- Laravel Telescope for local debugging
- Laravel Debugbar for query analysis
- Xdebug for step debugging

### Performance Monitoring

- Monitor queue depths
- Track job processing times
- Alert on high failure rates
- Log slow queries

## Documentation

### Building Documentation

Paperpulse documentation uses MkDocs with the Material theme. We use Docker to avoid installing dependencies locally.

#### Using Docker (Recommended)

All documentation tasks can be run using the official MkDocs Material Docker image.

##### Build Documentation

Build the static documentation site:

```bash
docker run --rm -v "${PWD}:/docs" squidfunk/mkdocs-material build
```

This generates files in `public/docs/` which are served at `/docs` by Laravel.

##### Live Preview with Hot Reload

Start the development server with automatic rebuild on changes:

```bash
docker run --rm -it -p 8000:8000 -v "${PWD}:/docs" squidfunk/mkdocs-material
```

Access at `http://localhost:8000`. The server watches for changes and rebuilds automatically.

##### Create New Documentation Project

If starting fresh:

```bash
docker run --rm -it -v "${PWD}:/docs" squidfunk/mkdocs-material new .
```

##### Additional Docker Commands

Get help on available commands:

```bash
docker run --rm -it -v "${PWD}:/docs" squidfunk/mkdocs-material --help
```

Build with verbose output:

```bash
docker run --rm -v "${PWD}:/docs" squidfunk/mkdocs-material build --verbose
```

Build with strict mode (fails on warnings):

```bash
docker run --rm -v "${PWD}:/docs" squidfunk/mkdocs-material build --strict
```

Serve on a different port:

```bash
docker run --rm -it -p 8001:8000 -v "${PWD}:/docs" squidfunk/mkdocs-material serve --dev-addr=0.0.0.0:8000
```

#### Alternative: Local Installation

If you prefer local installation:

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
- Set up Redis cluster
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
APP_URL=https://paperpulse.test

DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

FILESYSTEM_DISK=s3
```

### Queue Worker Configuration

Supervisor configuration for queue workers:

```ini
[program:paperpulse-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

## Troubleshooting Development

### Common Issues

**Composer dependency conflicts**
```bash
composer update --with-dependencies
```

**NPM build failures**
```bash
rm -rf node_modules package-lock.json
npm install
```

**Migration rollback issues**
```bash
php artisan migrate:fresh --seed
```

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