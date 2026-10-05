# File Reprocessing

Reprocess stored originals to retry failures or refresh the archive with the latest AI features.

## Commands

### Refresh the Entire Archive

```bash
# Preview the selection without changing files, caches, or jobs
docker compose exec app php artisan files:reprocess --all --dry-run

# Queue a refresh using the currently configured AI pipeline
docker compose exec app php artisan files:reprocess --all --no-interaction
```

`--all` includes completed, failed, pending, and review files across all owners. It uses the current `ai.file_processing_provider` configuration instead of each file's previous pipeline and clears that file's cached processing stages. Use `--provider=gemini` to select Gemini explicitly. CSV bank statements continue through the CSV import pipeline.

Only files with a stored original path are selected. Deleted files and active processing jobs are excluded; `--force` explicitly restarts active jobs. Missing originals and queue failures are reported, other files continue, and the command exits unsuccessfully if any handoff fails.

Files are read in chunks. Use `--user=123`, `--type=document`, or `--limit=100` to narrow a run. The command reports the last visited file ID; continue with `--after-id=1234`, preserving the same filters, and retry failed file IDs separately.

The command finishes after queueing work. Keep queue workers running for `default,receipts,documents,conversions,files,exports`; monitor with `php artisan queue:health`. Successful extraction refreshes search records and organization evidence through the existing processing flow, respecting organization preferences. Prior extracted records remain available until a replacement succeeds. AI/OCR calls may incur charges and remain subject to the configured processing budgets.

### View Statistics

```bash
php artisan files:reprocess --stats
```

Shows count of stored files by status (failed, pending, processing, completed, needs review).

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
| `--all` | Refresh all eligible stored files with the current pipeline and fresh processing results |
| `--stats` | Show statistics and exit |
| `--file-id=ID` | Reprocess specific file (repeatable) |
| `--user=ID` | Restrict to one file owner |
| `--type=TYPE` | Filter by type: `receipt` or `document` |
| `--status=STATUS` | Filter by status (default: `failed`); `all` is an alias for `--all` |
| `--provider=PROVIDER` | Override the pipeline: `gemini`, `textract+openai`, or `ocr-only` |
| `--fresh` | Clear cached processing stages for selected files |
| `--force` | Reprocess completed files, restart active jobs, and skip confirmation |
| `--limit=N` | Limit number of files |
| `--after-id=ID` | Continue after this file ID |
| `--chunk=N` | Files loaded per chunk (default: 100, maximum: 1000) |
| `--dry-run` | Preview without executing |

Without `--all` or a provider override, retries preserve the previous pipeline. An explicit `--file-id` selects that file regardless of its status; completed files still require `--force` or `--all`.

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
