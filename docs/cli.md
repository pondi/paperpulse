# Console Commands

Paperpulse provides a comprehensive set of Artisan commands for system management, maintenance, and troubleshooting. All commands support the `--help` flag for detailed usage information.

## User Management

### Promote User to Administrator
**Command:** `user:promote-admin`

Grants administrative privileges to a regular user account. Administrators can access system settings, manage other users, and perform maintenance tasks.

```bash
php artisan user:promote-admin user@example.com
```

### Remove Administrator Privileges
**Command:** `user:demote-admin`

Revokes administrative privileges, converting an admin back to a regular user.

```bash
php artisan user:demote-admin admin@example.com
```

## Beta Access Management

### Review Beta Requests
**Command:** `beta:list`

Displays all beta access requests with filtering options. Useful for reviewing who has requested access to the system.

```bash
# Show pending requests
php artisan beta:list --pending

# Show approved users
php artisan beta:list --invited

# Show rejected requests
php artisan beta:list --rejected
```

### Process Beta Request
**Command:** `beta:approve`

Approves or rejects a beta access request. Approved users receive an invitation email with registration instructions.

```bash
# Approve and send invitation
php artisan beta:approve user@example.com

# Approve with specific inviter
php artisan beta:approve user@example.com --invited-by=42

# Reject request
php artisan beta:approve user@example.com --reject
```

## Invitation System

### View All Invitations
**Command:** `invite:list`

Lists all system invitations with their current status (pending, accepted, expired).

```bash
# All invitations
php artisan invite:list

# Only pending invitations
php artisan invite:list --pending
```

### Send New Invitation
**Command:** `invite:send`

Sends a registration invitation to a new user. The invitation includes a secure token valid for a limited time.

```bash
php artisan invite:send user@example.com --invited-by=42
```

## Document Processing

### Reprocess All Files
**Command:** `files:reprocess-all`

Re-runs the processing pipeline for existing files. Useful when updating AI models or fixing processing issues.

```bash
# Preview what would be processed
php artisan files:reprocess-all --dry-run

# Process only failed files
php artisan files:reprocess-all --failed

# Process specific user's files
php artisan files:reprocess-all --user=42 --limit=50

# Process only receipts
php artisan files:reprocess-all --type=receipt
```

Options:
- `--type`: Filter by file type (receipt, document)
- `--user`: Process files for specific user ID
- `--limit`: Maximum number of files to process (default: 100)
- `--failed`: Only reprocess previously failed files
- `--dry-run`: Preview without making changes

### Regenerate PDF Previews
**Command:** `receipts:regenerate-previews`

Creates new preview images for PDF receipts. These previews are used for quick viewing without downloading the full PDF.

```bash
# Regenerate all previews
php artisan receipts:regenerate-previews

# Limit processing and force regeneration
php artisan receipts:regenerate-previews --limit=100 --force
```

### Retry Failed Processing Jobs
**Command:** `receipts:retry-failed`

Retries receipt processing jobs that previously failed due to temporary issues like API timeouts or service unavailability.

```bash
# Retry all failed jobs
php artisan receipts:retry-failed --all

# Retry specific job
php artisan receipts:retry-failed --job-id=abc-123

# Clear successful jobs from failed queue
php artisan receipts:retry-failed --all --clear
```

## Data Maintenance

### Remove Duplicate Receipts
**Command:** `receipts:cleanup-duplicates`

Identifies and removes duplicate receipt records that may have been created due to processing errors or multiple uploads.

```bash
# Preview duplicates without removing
php artisan receipts:cleanup-duplicates --dry-run

# Clean duplicates for specific file
php artisan receipts:cleanup-duplicates --file-id=123
```

### Clean Expired Shares
**Command:** `shares:cleanup`

Removes expired file sharing links to maintain database hygiene and ensure security.

```bash
php artisan shares:cleanup
```

## Search Index Management

### Rebuild Receipt Search Index
**Command:** `receipts:reindex`

Rebuilds the Meilisearch index for all receipts. Run this after bulk data changes or if search results seem incorrect.

```bash
php artisan receipts:reindex
```

### Rebuild All Search Indexes
**Command:** `scout:reindex-all`

Rebuilds search indexes for all searchable models (receipts, documents, etc.).

```bash
# Rebuild existing indexes
php artisan scout:reindex-all

# Delete and recreate indexes
php artisan scout:reindex-all --fresh
```

## Queue Management

### Check Queue Health
**Command:** `queue:health`

Monitors queue processing status and alerts on issues like stuck jobs or high failure rates.

```bash
# Display health status
php artisan queue:health

# Output as JSON for monitoring systems
php artisan queue:health --format=json

# Send alert if unhealthy
php artisan queue:health --alert
```

### Manage Batch Jobs
**Command:** `batch:manage`

Controls batch processing operations for bulk file imports and large-scale operations.

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

Actions:
- `list`: Display batch jobs
- `status`: Show detailed batch information
- `cancel`: Stop a running batch
- `cleanup`: Remove old batch records

## Database Operations

### Safe Database Migrations
**Command:** `migrate:safe`

Runs database migrations with distributed locking to prevent conflicts in multi-server deployments.

```bash
# Run migrations safely
php artisan migrate:safe

# Force in production
php artisan migrate:safe --force

# Include seeders
php artisan migrate:safe --force --seed

# Custom lock timeout
php artisan migrate:safe --lock-timeout=600
```

## Testing and Diagnostics

### Test Infrastructure Services
**Command:** `test:infrastructure`

Verifies all external services are properly configured and accessible (database, Redis, S3, Meilisearch).

```bash
php artisan test:infrastructure
```

### Diagnose File Processing
**Command:** `diagnose:file-processing`

Analyzes the file processing pipeline to identify configuration issues or bottlenecks.

```bash
# Check user's processing setup
php artisan diagnose:file-processing --user-id=42

# Test with sample file
php artisan diagnose:file-processing --test-file=storage/app/test.pdf
```

### Test File Processing Pipeline
**Command:** `test:file-processing`

Runs an end-to-end test of file processing with real or mocked services.

```bash
# Test with real services
php artisan test:file-processing --user-id=42

# Test with mocked external services
php artisan test:file-processing --user-id=42 --mock-services
```

### Simple Processing Test
**Command:** `test:file-simple`

Performs a basic file processing test to verify core functionality.

```bash
php artisan test:file-simple --user-id=42
```

## AI Service Management

### Test AI Providers
**Command:** `ai:test`

Validates AI service configuration and tests processing capabilities.

```bash
# Test receipt extraction
php artisan ai:test openai --receipt

# Test document analysis
php artisan ai:test openai --document
```

### Validate AI Output
**Command:** `ai:test-validation`

Checks AI response formatting and data extraction accuracy.

```bash
php artisan ai:test-validation --type=receipt --sample=storage/app/sample.txt
```

### Manage AI Prompts
**Command:** `prompts:manage`

Creates and maintains AI prompt templates for different processing scenarios.

```bash
# List all prompts
php artisan prompts:manage list

# Create new prompt (interactive)
php artisan prompts:manage create

# Create with parameters
php artisan prompts:manage create --name="Receipt Extract" --type=receipt

# Validate all prompts
php artisan prompts:manage validate
```

## Debug Utilities

### Test Logging System
**Command:** `debug:test-logging`

Verifies logging configuration for debugging AI processing issues.

```bash
php artisan debug:test-logging
```

## Command Tips

1. **Use `--dry-run` when available** to preview changes before execution
2. **Check logs** in `storage/logs/laravel.log` for detailed error information
3. **Run `queue:health` regularly** to monitor background job processing
4. **Use `--help` on any command** for detailed usage information
5. **Schedule maintenance commands** using Laravel's task scheduler for automation