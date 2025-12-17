# API (v1) Documentation

Minimal REST surface for external integrations. All file operations are scoped to the authenticated user and reuse the same processing pipeline as the web upload.

## Base URL
- `/api/v1`

## Authentication
- Tokens: Laravel Sanctum personal access token.
- Header: `Authorization: Bearer <token>`

### Register
- `POST /auth/register`
- Body: `{ "name": "Jane Doe", "email": "jane@example.com", "password": "secret123", "password_confirmation": "secret123" }`
- Response: `user`, `token` (201)

### Login
- `POST /auth/login`
- Body: `{ "email": "jane@example.com", "password": "secret123" }`
- Response: `user`, `token` (200)

### Logout
- `POST /auth/logout` (Bearer required)
- Response: success message (200)

### Me
- `GET /auth/me` (Bearer required)
- Response: current user profile (200)

## Files

### Upload File (Single-Step Processing)

Upload a file and automatically trigger processing to create a Receipt or Document record. This is a **one-step operation** - there is no separate endpoint to create documents. The file upload handles everything:

1. File is immediately stored to S3 (original preserved)
2. File record created in database with `status='pending'`
3. Processing job dispatched based on `file_type`
4. Receipt or Document record automatically created during processing
5. OCR extraction and AI analysis performed
6. User notes are preserved and indexed for search

**Important**: The original uploaded file is **always preserved in S3** regardless of processing success/failure, allowing for reprocessing later if needed.

#### Endpoint
- `POST /files`
- Auth: required
- Rate limit: 200 requests/minute

#### Request Body (`multipart/form-data`)
- `file` (required): Single file upload
  - Supported formats: jpg, jpeg, png, pdf, tiff, tif
  - Max size: 100MB
  - File is immediately uploaded to S3 for permanent storage
- `file_type` (required): `receipt` or `document`
  - `receipt`: Triggers receipt processing (OCR + merchant/amount extraction)
  - `document`: Triggers document processing (OCR + entity extraction)
- `note` (optional, max 1000 chars): User-provided note
  - Stored on the resulting Receipt/Document record
  - Preserved during processing (system may enrich but never overwrites)
  - Indexed for full-text search
  - Included in exports

#### Success Response (201)
```json
{
  "status": "success",
  "message": "File uploaded for processing",
  "data": {
    "file_id": 123,
    "file_guid": "a1b2c3d4-...",
    "job_id": "e5f6g7h8-...",
    "job_name": "job-2025-02-12-abc",
    "file_type": "receipt",
    "checksum_sha256": "a3c4..."
  }
}
```

**Response Fields:**
- `file_id`: Database ID of the File record
- `file_guid`: Unique GUID for file identification
- `job_id`: Job chain ID for tracking processing status
- `job_name`: Human-readable job name
- `file_type`: Type of processing initiated (`receipt` or `document`)
- `checksum_sha256`: SHA-256 hash of uploaded file for verification

#### Error Responses
- **401 Unauthorized**: Missing or invalid authentication token
- **422 Unprocessable Entity**: Validation failed
  - Invalid MIME type (only images and PDFs accepted)
  - File too large (>100MB)
  - Invalid `file_type` value
  - Missing required fields
- **429 Too Many Requests**: Rate limit exceeded (200 req/min)

#### Example Request
```bash
curl -X POST https://api.paperpulse.com/api/v1/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@receipt.pdf" \
  -F "file_type=receipt" \
  -F "note=Lunch with client - Project Alpha discussion"
```

#### Processing Flow
After upload returns successfully:
1. File is already stored in S3 (permanent)
2. Processing job is queued (async)
3. OCR extraction runs on the file
4. AI analysis extracts structured data
5. Receipt/Document record created automatically
6. User notes are preserved and indexed
7. Processing status can be checked via `/files/{id}`

**Note**: Processing is asynchronous. Check the `status` field via `GET /files/{id}` to monitor progress:
- `pending`: Waiting for processing
- `processing`: OCR/AI analysis in progress
- `completed`: Receipt/Document created successfully
- `failed`: Processing error (original file still preserved for retry)

### View File (metadata + temp download URL)
- `GET /files/{file_id}`
- Auth: required; only the owner can view.
- Response (200):
```json
{
  "status": "success",
  "message": "File URL generated successfully",
  "data": {
    "file": {
      "id": 123,
      "guid": "uuid",
      "name": "invoice.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf",
      "file_type": "document",
      "processing_type": "document",
      "size": 34567,
      "status": "completed",
      "uploaded_at": "2025-02-12T10:00:00Z",
      "s3_original_path": "s3://…",
      "has_image_preview": false
    },
    "download_url": "https://s3…signed",
    "expires_in_minutes": 60
  }
}
```
- Errors: 401 (auth), 404 (file not found or not owned by user), 429 (rate limit)

### Stream File Content (open/preview)
- `GET /files/{file_id}/content`
- Auth: required; **owner-only** (non-owners receive 404).
- Rate limit: 200 requests/minute
- Query:
  - `variant` (optional): `original` (default), `preview`, `archive`
    - `preview`: image preview when available (JPG). Intended for fast thumbnail/preview on slow connections.
    - `archive`: converted PDF when available (or original PDF if the original is a PDF).
  - `disposition` (optional): `inline` (default) or `attachment`
- Response (200): raw file stream with appropriate `Content-Type`.

#### Example Requests
```bash
# Open the original file inline
curl -L -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.paperpulse.com/api/v1/files/123/content"

# Fetch the preview image (when available)
curl -L -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.paperpulse.com/api/v1/files/123/content?variant=preview"

# Fetch the PDF (converted when available)
curl -L -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.paperpulse.com/api/v1/files/123/content?variant=archive"
```

### List Files
- `GET /files`
- Auth: required
- Query: `per_page` (default 15)
- Success (200):
```json
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "guid": "uuid",
      "name": "invoice.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf",
      "file_type": "document",
      "processing_type": "document",
      "size": 34567,
      "status": "pending",
      "uploaded_at": "2025-02-12T10:00:00Z",
      "s3_original_path": "s3://…",
      "has_image_preview": false
    }
  ],
  "pagination": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 },
  "links": { "first": "...", "last": "...", "prev": null, "next": null }
}
```
- Errors: 401 (auth), 429 (rate limit)

## Search

Instant search across receipts + documents (same underlying search implementation as the web admin), returning a lightweight payload designed for mobile.

### Search
- `GET /search`
- Auth: required
- Rate limit: 200 requests/minute
- Query:
  - `q` (or `query`): search string
  - `type`: `all` (default), `receipt`, `document`
  - `limit`: integer (default 20, max 50)
  - `date_from`, `date_to`: date filters
  - `amount_min`, `amount_max`: numeric filters (receipts)
  - `category`: category filter
  - `document_type`: document type filter (documents)
  - `tags`: CSV (`tags=a,b`) or array (`tags[]=a&tags[]=b`)

### Success Response (200)
```json
{
  "status": "success",
  "message": "Search results retrieved successfully",
  "data": {
    "query": "invoice",
    "filters": { "type": "all", "limit": 20 },
    "results": [
      {
        "id": 456,
        "type": "document",
        "title": "Invoice - ACME",
        "snippet": "…",
        "date": "2025-01-01",
        "filename": "invoice.pdf",
        "file": {
          "id": 123,
          "guid": "uuid",
          "extension": "pdf",
          "has_image_preview": false,
          "has_converted_pdf": true
        },
        "links": {
          "content": "https://api.paperpulse.com/api/v1/files/123/content",
          "preview": null,
          "pdf": "https://api.paperpulse.com/api/v1/files/123/content?variant=archive"
        }
      }
    ],
    "facets": { "total": 1, "receipts": 0, "documents": 1 }
  },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

## Other Routes Outside `/v1`
- `/api/health`: health check (no auth).
- `/api/beta-request`: beta signup (public).
- `/api/webdav/auth`: PulseDav auth (used by sync feature).
- `/api/documents/{id}/shares`, `/api/receipts/{id}/shares`, `/api/batch/*`: internal/web-facing APIs; keep intact for web app and batch processing. External clients should ignore these in favor of `/api/v1/files`.

Receipt and Document payloads exposed by these internal APIs and the web app include a `note` field (nullable string) representing the user-authored “Document Note” attached to each record.
