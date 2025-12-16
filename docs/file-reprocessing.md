# File Reprocessing

Reprocess files from S3 to retry failures or test code changes.

## Commands

### View Statistics

```bash
php artisan files:reprocess --stats
```

Shows count of files by status (failed, pending, processing).

### Reprocess Files

```bash
# Retry all failed files
php artisan files:reprocess --status=failed

# Retry specific file
php artisan files:reprocess --file-id=123

# Retry failed receipts only
php artisan files:reprocess --type=receipt --status=failed

# Preview before running (dry run)
php artisan files:reprocess --status=failed --dry-run

# Reprocess completed files (after code changes)
php artisan files:reprocess --file-id=123 --force

# Batch with limit
php artisan files:reprocess --status=failed --limit=10
```

## Options

| Option | What it does |
|--------|--------------|
| `--stats` | Show statistics and exit |
| `--file-id=ID` | Reprocess specific file (repeatable) |
| `--type=TYPE` | Filter by type: `receipt` or `document` |
| `--status=STATUS` | Filter by status (default: `failed`) |
| `--force` | Reprocess even if already completed |
| `--limit=N` | Limit number of files |
| `--dry-run` | Preview without executing |

## How It Works

**Queue Retry** (`queue:retry all`) - Retries failed job as-is. May fail if files gone.

**File Reprocessing** (`files:reprocess`) - Downloads fresh copy from S3, creates new job. Recommended.

## Troubleshooting

```bash
# "No files found"
php artisan files:reprocess --stats  # Check what's available

# "Already completed"
php artisan files:reprocess --file-id=123 --force

# "File not in S3"
# Check S3 credentials and connectivity
php artisan tinker
> Storage::disk('s3')->exists('path/to/file')
```
