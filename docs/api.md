# API (v1) Documentation

Minimal REST surface for external integrations. All file operations are scoped to the authenticated user and reuse the same processing pipeline as the web upload.

## Base URL
- `/api/v1`

## Authentication
- Tokens: Laravel Sanctum personal access token.
- Header: `Authorization: Bearer <token>`

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
- **409 Conflict**: Duplicate file detected (file with same SHA256 hash already exists)
- **422 Unprocessable Entity**: Validation failed
  - Invalid MIME type (only images and PDFs accepted)
  - File too large (>100MB)
  - Invalid `file_type` value
  - Missing required fields
- **429 Too Many Requests**: Rate limit exceeded (200 req/min)

#### Duplicate File Response (409)
When uploading a file that already exists (based on SHA256 hash), the API returns a 409 Conflict response with details about the existing file:

```json
{
  "status": "error",
  "message": "Duplicate file detected",
  "errors": {
    "duplicate": {
      "message": "Duplicate file detected",
      "file_hash": "82e15db53ee6b07becdfed5f196a87bf116f992835ce35f6d4bb4142df62fa4f",
      "existing_file": {
        "id": 123,
        "guid": "550e8400-e29b-41d4-a716-446655440000",
        "fileName": "receipt-2024-01-15.jpg",
        "fileType": "receipt",
        "uploaded_at": "2025-01-15T10:30:45.000000Z"
      }
    }
  },
  "timestamp": "2025-12-25T14:23:12.000000Z"
}
```

**Duplicate Response Fields:**
- `file_hash`: SHA-256 hash that matched the existing file
- `existing_file.id`: Database ID of the existing file
- `existing_file.guid`: Unique GUID/UUID of the existing file (use this for API operations)
- `existing_file.fileName`: Original filename of the existing file
- `existing_file.fileType`: Type of the existing file (`receipt` or `document`)
- `existing_file.uploaded_at`: When the existing file was originally uploaded

This allows clients to:
- Avoid re-uploading duplicate files
- Link to the existing file using its GUID
- Display information about when the file was first uploaded
- Implement client-side deduplication using SHA-256 hashing

#### Example Request
```bash
curl -X POST https://paperpulse.app/api/v1/files \
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

### View File Details
- `GET /files/{file_id}`
- Auth: required; only the owner can view.
- Returns file metadata with associated receipt or document data.
- To stream the file content, use `/files/{file_id}/content`.

#### Response for Receipt File (200):
```json
{
  "status": "success",
  "message": "File details retrieved successfully",
  "data": {
    "file": {
      "id": 123,
      "guid": "uuid",
      "name": "receipt.jpg",
      "extension": "jpg",
      "mime_type": "image/jpeg",
      "file_type": "receipt",
      "processing_type": "receipt",
      "size": 245678,
      "status": "completed",
      "uploaded_at": "2025-02-12T10:00:00Z",
      "has_image_preview": true
    },
    "receipt": {
      "id": 456,
      "merchant": {
        "id": 789,
        "name": "Whole Foods Market"
      },
      "total_amount": "45.67",
      "tax_amount": "3.42",
      "currency": "USD",
      "receipt_date": "2025-02-12T00:00:00Z",
      "summary": "Groceries including organic produce and dairy products",
      "note": "Weekly grocery shopping",
      "receipt_description": "Grocery receipt from Whole Foods",
      "category": {
        "id": 10,
        "name": "Groceries"
      },
      "tags": [
        {
          "id": 1,
          "name": "personal"
        }
      ],
      "line_items": [
        {
          "id": 101,
          "description": "Organic Milk",
          "amount": "5.99",
          "quantity": "1.00",
          "unit_price": "5.99"
        },
        {
          "id": 102,
          "description": "Whole Wheat Bread",
          "amount": "6.98",
          "quantity": "2.00",
          "unit_price": "3.49"
        }
      ]
    }
  }
}
```

#### Response for Document File (200):
```json
{
  "status": "success",
  "message": "File details retrieved successfully",
  "data": {
    "file": {
      "id": 124,
      "guid": "uuid",
      "name": "contract.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf",
      "file_type": "document",
      "processing_type": "document",
      "size": 567890,
      "status": "completed",
      "uploaded_at": "2025-02-12T11:00:00Z",
      "has_image_preview": false
    },
    "document": {
      "id": 457,
      "title": "Service Agreement - Vendor ABC",
      "description": "Annual service contract with Vendor ABC",
      "summary": "Contract covering maintenance services for 2025",
      "note": "Renewal contract - expires Dec 2025",
      "document_type": "contract",
      "document_date": "2025-01-01T00:00:00Z",
      "entities": null,
      "ai_entities": {
        "people": ["John Doe", "Jane Smith"],
        "organizations": ["Vendor ABC Inc."],
        "dates": ["2025-01-01", "2025-12-31"]
      },
      "metadata": {
        "contract_value": "50000",
        "department": "IT"
      },
      "language": "en",
      "category": {
        "id": 5,
        "name": "Contracts"
      },
      "tags": [
        {
          "id": 3,
          "name": "important"
        }
      ]
    }
  }
}
```

#### Response for Processing File (200):
When the file is still processing (`status: "pending"` or `"processing"`), only file metadata is returned:
```json
{
  "status": "success",
  "message": "File details retrieved successfully",
  "data": {
    "file": {
      "id": 125,
      "guid": "uuid",
      "name": "receipt.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf",
      "file_type": "receipt",
      "processing_type": "receipt",
      "size": 123456,
      "status": "processing",
      "uploaded_at": "2025-02-12T12:00:00Z",
      "has_image_preview": false
    }
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
  "https://paperpulse.app/api/v1/files/123/content"

# Fetch the preview image (when available)
curl -L -H "Authorization: Bearer YOUR_TOKEN" \
  "https://paperpulse.app/api/v1/files/123/content?variant=preview"

# Fetch the PDF (converted when available)
curl -L -H "Authorization: Bearer YOUR_TOKEN" \
  "https://paperpulse.app/api/v1/files/123/content?variant=archive"
```

### List Files
- `GET /files`
- Auth: required
- Returns paginated list of files owned by the authenticated user, with **list-view-friendly metadata** derived from the associated Receipt/Document when available.

#### Query Parameters
- `file_type` (optional): Filter by type
  - `receipt` - Only receipt files
  - `document` - Only document files
  - Omit to return all files
- `status` (optional): Filter by processing status
  - `pending`, `processing`, `completed`, `failed`
- `page` (optional): Page number (default: 1, must be positive integer)
- `per_page` (optional): Results per page (default: 15, max: 100)

#### Example Requests
```bash
# Get all receipts
GET /files?file_type=receipt

# Get all documents
GET /files?file_type=document

# Get completed receipts only
GET /files?file_type=receipt&status=completed

# Get all files (no filter)
GET /files

# Pagination examples
GET /files?per_page=20                        # 20 results per page
GET /files?page=2&per_page=20                 # Page 2 with 20 results
GET /files?file_type=receipt&page=3&per_page=10  # Filtered and paginated
```

#### Success Response (200):
```json
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "guid": "uuid",
      "checksum_sha256": "82e15db53ee6b07becdfed5f196a87bf116f992835ce35f6d4bb4142df62fa4f",
      "file_type": "document",
      "processing_type": "document",
      "status": "completed",
      "name": "invoice.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf",
      "size": 34567,
      "uploaded_at": "2025-02-12T10:00:00Z",
      "has_image_preview": false,
      "has_archive_pdf": true,
      "title": "Invoice May",
      "snippet": "Monthly invoice…",
      "date": "2025-05-04T00:00:00Z",
      "document_type": "invoice",
      "page_count": 2,
      "document": {
        "id": 457,
        "title": "Invoice May",
        "category": { "id": 5, "name": "Finance", "color": "#EF4444" }
      },
      "receipt": null,
      "links": {
        "content": "https://paperpulse.app/api/v1/files/123/content",
        "preview": null,
        "pdf": "https://paperpulse.app/api/v1/files/123/content?variant=archive"
      }
    },
    {
      "id": 124,
      "guid": "uuid2",
      "checksum_sha256": "4cf49cc9c7954cc5bd65f0af3d0b92e29e6e616648bba9c496bddf2f0f0cc62d",
      "file_type": "receipt",
      "processing_type": "receipt",
      "status": "completed",
      "name": "receipt.jpg",
      "extension": "jpg",
      "mime_type": "image/jpeg",
      "size": 245678,
      "uploaded_at": "2025-02-12T09:30:00Z",
      "has_image_preview": true,
      "has_archive_pdf": false,
      "title": "Whole Foods Market",
      "snippet": "Groceries including organic produce…",
      "date": "2025-02-12T00:00:00Z",
      "total": "45.67",
      "currency": "USD",
      "receipt": {
        "id": 456,
        "merchant": { "id": 789, "name": "Whole Foods Market" },
        "category": { "id": 10, "name": "Groceries", "color": "#10B981" }
      },
      "document": null,
      "links": {
        "content": "https://paperpulse.app/api/v1/files/124/content",
        "preview": "https://paperpulse.app/api/v1/files/124/content?variant=preview",
        "pdf": null
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 2,
    "from": 1,
    "to": 2
  },
  "links": {
    "first": "https://paperpulse.app/api/v1/files?page=1",
    "last": "https://paperpulse.app/api/v1/files?page=1",
    "prev": null,
    "next": null
  },
  "timestamp": "2025-12-30T14:23:12.000000Z"
}
```

#### Pagination Navigation

**Recommended Approach:** Use the `links` object from the response to navigate between pages:

```bash
# 1. Get first page
GET /files?per_page=10

# 2. Use links.next from the response for the next page
# Response will include: "links": { "next": "https://paperpulse.app/api/v1/files?page=2&per_page=10" }

# 3. Follow the next link
GET https://paperpulse.app/api/v1/files?page=2&per_page=10

# 4. Continue following links.next until it's null (last page reached)
```

**Pagination Response Fields:**
- `pagination.current_page`: Current page number
- `pagination.last_page`: Total number of pages
- `pagination.per_page`: Results per page
- `pagination.total`: Total number of results across all pages
- `pagination.from`: Index of first result on current page (1-based)
- `pagination.to`: Index of last result on current page (1-based)
- `links.first`: URL to first page
- `links.last`: URL to last page
- `links.prev`: URL to previous page (null if on first page)
- `links.next`: URL to next page (null if on last page)

#### Notes
- `title`/`snippet`/`date` are computed for display in mobile list views.
- `receipt` and `document` are lightweight; for full details use `GET /files/{file_id}`.

#### Errors
- **401 Unauthorized**: Missing or invalid authentication token
- **422 Unprocessable Entity**: Invalid parameter values (e.g., `page=0`, `page=-1`, `per_page=200`)
- **429 Too Many Requests**: Rate limit exceeded (200 req/min)

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
          "content": "https://paperpulse.app/api/v1/files/123/content",
          "preview": null,
          "pdf": "https://paperpulse.app/api/v1/files/123/content?variant=archive"
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
- `/api/webdav/auth`: PulseDav auth (used by sync feature).
- `/api/documents/{id}/shares`, `/api/receipts/{id}/shares`, `/api/batch/*`: internal/web-facing APIs; keep intact for web app and batch processing. External clients should ignore these in favor of `/api/v1/files`.

Receipt and Document payloads exposed by these internal APIs and the web app include a `note` field (nullable string) representing the user-authored "Document Note" attached to each record.
