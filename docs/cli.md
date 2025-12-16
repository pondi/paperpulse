# Console Commands

PaperPulse provides Artisan commands for system management and maintenance. All commands support `--help` for detailed usage.

## User Management

### Promote User to Administrator
```bash
php artisan user:promote-admin user@example.com
```

### Remove Administrator Privileges
```bash
php artisan user:demote-admin admin@example.com
```

## Invitation System

### View All Invitations
```bash
# All invitations
php artisan invite:list

# Only pending invitations
php artisan invite:list --pending
```

### Send New Invitation
```bash
php artisan invite:send user@example.com
```

## File Processing

### Reprocess Files
Reprocess files from S3 to retry failures or test code changes.

```bash
# View statistics
php artisan files:reprocess --stats

# Retry all failed files
php artisan files:reprocess --status=failed

# Retry failed receipts
php artisan files:reprocess --type=receipt --status=failed

# Retry specific file
php artisan files:reprocess --file-id=123

# Reprocess completed files (after code improvements)
php artisan files:reprocess --status=completed --force --limit=10
```

See `docs/file-reprocessing.md` for full documentation.

### Regenerate File Previews
Create new preview images for PDF files (receipts and documents).

```bash
# Regenerate all previews
php artisan files:regenerate-previews

# Limit processing
php artisan files:regenerate-previews --limit=100 --force

# Specific file type
php artisan files:regenerate-previews --type=receipt
```

### Retry Failed Conversions
Retry failed Office file to PDF conversions.

```bash
# Retry failed conversions
php artisan conversions:retry-failed

# Limit retries
php artisan conversions:retry-failed --limit=10

# Preview without retrying
php artisan conversions:retry-failed --dry-run
```

## Search Index Management

### Reindex Receipts
```bash
php artisan reindex:receipts
```

### Reindex Documents
```bash
php artisan reindex:documents
```

### Reindex All Models
```bash
# Rebuild existing indexes
php artisan scout:reindex-all

# Delete and recreate indexes
php artisan scout:reindex-all --fresh
```

## Queue Management

### Check Queue Health
```bash
# Display health status
php artisan queue:health

# Output as JSON
php artisan queue:health --format=json

# Send alert if unhealthy
php artisan queue:health --alert
```

### Manage Batch Jobs
```bash
# List all batch jobs
php artisan batch:manage list

# Filter by status
php artisan batch:manage list --status=running

# Show batch details
php artisan batch:manage status --id=123

# Cancel running batch
php artisan batch:manage cancel --id=123

# Clean old completed batches
php artisan batch:manage cleanup --days=7
```

## Database Operations

### Safe Database Migrations
Run migrations with distributed locking to prevent conflicts in multi-server deployments.

```bash
# Run migrations safely
php artisan migrate:safe

# Force in production
php artisan migrate:safe --force

# Include seeders
php artisan migrate:safe --force --seed
```

## Meilisearch Configuration

### Configure Meilisearch Indexes
```bash
php artisan meilisearch:configure
```
