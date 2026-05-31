# API (v1) Documentation

REST API for external integrations. All operations are scoped to the authenticated user. File processing reuses the same pipeline as the web upload.

## Base URL
- `/api/v1`

## Authentication
- Tokens: Laravel Sanctum personal access token (30-day expiry).
- Header: `Authorization: Bearer <token>`

### Login
- `POST /auth/login`
- Rate limit: 5 requests per 15 minutes
- Body: `{ "email": "jane@example.com", "password": "secret123" }`
- Response (200):
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "is_admin": false,
      "timezone": "UTC",
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    },
    "token": "1|abc123..."
  }
}
```

### Logout
- `POST /auth/logout` (Bearer required)
- Deletes the current access token.
- Response (200): success message

### Me
- `GET /auth/me` (Bearer required)
- Response (200): current user profile (same `user` object as login)

---

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
  - Receipt formats: jpg, jpeg, png, pdf, tiff, tif
  - Document formats: doc, docx, xls, xlsx, ppt, pptx, odt, ods, odp, pdf, rtf, txt, html, csv
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
- `collection_ids` (optional): Array of collection IDs to assign to the file
  - Example: `collection_ids[]=1&collection_ids[]=2`
  - Collections must belong to the authenticated user
- `tag_ids` (optional): Array of tag IDs to assign to the file
  - Example: `tag_ids[]=1&tag_ids[]=2`
  - Tags must belong to the authenticated user

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
  },
  "timestamp": "2025-02-12T10:00:00Z"
}
```

**Response Fields:**
- `file_id`: Database ID of the File record
- `file_guid`: Unique GUID for file identification
- `job_id`: Job chain ID for tracking processing status (use with `GET /jobs/{job_id}`)
- `job_name`: Human-readable job name
- `file_type`: Type of processing initiated (`receipt` or `document`)
- `checksum_sha256`: SHA-256 hash of uploaded file for verification

#### Error Responses
- **401 Unauthorized**: Missing or invalid authentication token
- **409 Conflict**: Duplicate file detected (file with same SHA256 hash already exists)
- **422 Unprocessable Entity**: Validation failed
  - Invalid MIME type
  - File too large (>100MB)
  - Invalid `file_type` value
  - Missing required fields
- **429 Too Many Requests**: Rate limit exceeded (200 req/min)

#### Duplicate File Response (409)
When uploading a file that already exists (based on SHA256 hash), the API returns a 409 Conflict response with details about the existing file:

```json
{
  "status": "error",
  "code": "DUPLICATE_FILE",
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

#### Example Requests
```bash
# Basic upload
curl -X POST https://paperpulse.app/api/v1/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@receipt.pdf" \
  -F "file_type=receipt" \
  -F "note=Lunch with client - Project Alpha discussion"

# Upload with collections and tags
curl -X POST https://paperpulse.app/api/v1/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@invoice.pdf" \
  -F "file_type=document" \
  -F "note=Q1 2025 invoice" \
  -F "collection_ids[]=1" \
  -F "collection_ids[]=2" \
  -F "tag_ids[]=5" \
  -F "tag_ids[]=8"
```

#### Processing Flow
After upload returns successfully:
1. File is already stored in S3 (permanent)
2. Processing job is queued (async)
3. OCR extraction runs on the file
4. AI analysis extracts structured data
5. Receipt/Document record created automatically
6. User notes are preserved and indexed
7. Processing status can be checked via `GET /files/{id}` or `GET /jobs/{job_id}`

**Note**: Processing is asynchronous. Check the `status` field via `GET /files/{id}` to monitor progress:
- `pending`: Waiting for processing
- `processing`: OCR/AI analysis in progress
- `completed`: Receipt/Document created successfully
- `failed`: Processing error (original file still preserved for retry)

### View File Details
- `GET /files/{file_id}`
- Auth: required; only the owner can view.
- Returns file metadata with associated receipt or document data, plus an `entity` pointer when an extracted entity exists.
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
        { "id": 1, "name": "personal" }
      ],
      "line_items": [
        {
          "id": 101,
          "description": "Organic Milk",
          "amount": "5.99",
          "quantity": "1.00",
          "unit_price": "5.99"
        }
      ]
    },
    "document": null,
    "entity": {
      "type": "invoice",
      "id": 12
    }
  },
  "timestamp": "2025-02-12T10:00:00Z"
}
```

**Note:** The `entity` field is present when the file has an extracted entity (invoice, contract, voucher, warranty, bank statement). It provides the entity type and ID so you can fetch full details from the corresponding entity endpoint.

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
    "receipt": null,
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
        { "id": 3, "name": "important" }
      ]
    },
    "entity": {
      "type": "contract",
      "id": 89
    }
  },
  "timestamp": "2025-02-12T11:00:00Z"
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
    },
    "receipt": null,
    "document": null,
    "entity": null
  },
  "timestamp": "2025-02-12T12:00:00Z"
}
```

- Errors: 401 (auth), 404 (file not found or not owned by user), 429 (rate limit)

### Update File
- `PATCH /files/{file_id}`
- Auth: required; owner only
- Update file metadata: note, category, tags, and collections.

#### Request Body (JSON)
```json
{
  "note": "Updated note text",
  "category_id": 5,
  "tag_ids": [1, 3, 5],
  "collection_ids": [2, 4]
}
```

All fields are optional. Only provided fields will be updated.

- `note` (optional, max 1000 chars): User-provided note
- `category_id` (optional): Category ID (must belong to the authenticated user, or `null` to clear)
- `tag_ids` (optional): Array of tag IDs to sync (replaces all current tags)
- `collection_ids` (optional): Array of collection IDs to sync (replaces all current collections)

#### Success Response (200)
Returns the full `FileDetailResource` (same structure as `GET /files/{file_id}`).

#### Error Responses
- **401 Unauthorized**: Missing or invalid authentication token
- **404 Not Found**: File not found or not owned by user
- **422 Unprocessable Entity**: Validation failed

### Delete File
- `DELETE /files/{file_id}`
- Auth: required; owner only
- Deletes the file record.

#### Success Response (204)
No content body.

#### Error Responses
- **401 Unauthorized**: Missing or invalid authentication token
- **404 Not Found**: File not found or not owned by user

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
- Returns paginated list of files owned by the authenticated user, with **list-view-friendly metadata** derived from the associated entity when available.

#### Query Parameters
- `file_type` (optional): Filter by type — `receipt` or `document`
- `status` (optional): Filter by processing status — `pending`, `processing`, `completed`, `failed`
- `page` (optional): Page number (default: 1, must be positive integer)
- `per_page` (optional): Results per page (default: 15, max: 100)

#### Example Requests
```bash
GET /files?file_type=receipt
GET /files?file_type=document
GET /files?file_type=receipt&status=completed
GET /files?per_page=20&page=2
```

#### Success Response (200):
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "id": 123,
      "guid": "uuid",
      "checksum_sha256": "82e15db5...",
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
      "total": null,
      "currency": null,
      "document_type": "invoice",
      "page_count": 2,
      "entity": {
        "id": 457,
        "type": "invoice",
        "vendor_name": "ACME Corp",
        "invoice_number": "INV-2025-042",
        "category": { "id": 5, "name": "Finance", "color": "#EF4444" }
      },
      "links": {
        "content": "https://paperpulse.app/api/v1/files/123/content",
        "preview": null,
        "pdf": "https://paperpulse.app/api/v1/files/123/content?variant=archive"
      }
    },
    {
      "id": 124,
      "guid": "uuid2",
      "checksum_sha256": "4cf49cc9...",
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
      "document_type": null,
      "page_count": null,
      "entity": {
        "id": 456,
        "type": "receipt",
        "merchant": { "id": 789, "name": "Whole Foods Market" },
        "category": { "id": 10, "name": "Groceries", "color": "#10B981" }
      },
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

**List Item Fields:**
- `title`/`snippet`/`date` are computed for display in list views.
- `total`/`currency` are present for receipts and invoices.
- `document_type`/`page_count` are present for documents.
- `entity` provides type-specific metadata from the primary entity (receipt, document, invoice, contract, etc.). Contains `type`, `id`, and type-specific fields like `merchant`, `category`, `vendor_name`, `invoice_number`, or `title`.

#### Pagination
Use the `links` object from the response to navigate between pages. Follow `links.next` until it is `null`.

**Pagination Response Fields:**
- `pagination.current_page`: Current page number
- `pagination.last_page`: Total number of pages
- `pagination.per_page`: Results per page
- `pagination.total`: Total number of results across all pages
- `pagination.from` / `pagination.to`: 1-based indices of first/last result on current page
- `links.first` / `links.last` / `links.prev` / `links.next`: Navigation URLs

#### Errors
- **401 Unauthorized**: Missing or invalid authentication token
- **422 Unprocessable Entity**: Invalid parameter values (e.g., `page=0`, `per_page=200`)
- **429 Too Many Requests**: Rate limit exceeded (200 req/min)

---

## Jobs

Track the status of asynchronous processing jobs (returned as `job_id` from file upload).

### View Job Status
- `GET /jobs/{job_id}`
- Auth: required; the job must be linked to a file owned by the authenticated user.
- Rate limit: 200 requests/minute

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Job status retrieved",
  "data": {
    "id": "e5f6g7h8-...",
    "name": "job-2025-02-12-abc",
    "status": "completed",
    "progress": 100,
    "current_step": null,
    "file_id": 123,
    "started_at": "2025-02-12T10:00:01Z",
    "completed_at": "2025-02-12T10:00:15Z",
    "error": null,
    "tasks": [
      {
        "name": "ProcessFileGemini",
        "status": "completed",
        "progress": 100,
        "started_at": "2025-02-12T10:00:01Z",
        "completed_at": "2025-02-12T10:00:15Z"
      }
    ]
  },
  "timestamp": "2025-02-12T10:01:00Z"
}
```

**Response Fields:**
- `id`: Job chain UUID (same as `job_id` from upload response)
- `name`: Human-readable job name
- `status`: Job status (`pending`, `processing`, `completed`, `failed`)
- `progress`: Integer 0–100
- `current_step`: Name of the currently running task (null if not processing)
- `file_id`: Associated file ID
- `started_at` / `completed_at`: ISO 8601 timestamps
- `error`: Error message if job failed, otherwise null
- `tasks`: Array of sub-tasks with individual status/progress

#### Error Responses
- **404 Not Found**: Job not found or not owned by user

---

## Search

Instant search across receipts, documents, and extracted entities, returning a lightweight payload designed for mobile.

### Search
- `GET /search`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `q` (or `query`): Search string (max 200 chars)
- `type`: `all` (default), `receipt`, `document`, `invoice`, `contract`, `voucher`, `warranty`, `return_policy`, `bank_statement`
- `limit`: Integer (default 20, max 50)
- `date_from`, `date_to`: Date filters (ISO date format)
- `amount_min`, `amount_max`: Numeric filters (receipts/invoices)
- `category`: Category name filter
- `document_type`: Document type filter
- `tags`: CSV (`tags=a,b`) or array (`tags[]=a&tags[]=b`)

#### Success Response (200)
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
        "total": "1250.00",
        "document_type": "invoice",
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
    "facets": {
      "total": 1,
      "receipts": 0,
      "documents": 1,
      "invoices": 0,
      "contracts": 0,
      "vouchers": 0,
      "warranties": 0,
      "return_policies": 0,
      "bank_statements": 0
    }
  },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

---

## Receipts

Read-only access to extracted receipt data.

### List Receipts
- `GET /receipts`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `merchant` (optional): Filter by merchant name (partial match)
- `date_from` (optional): Filter by receipt date (ISO date)
- `date_to` (optional): Filter by receipt date (ISO date)
- `currency` (optional): Filter by currency code (e.g., `USD`, max 3 chars)
- `sort` (optional): Sort field — `receipt_date`, `total_amount`, `created_at` (default: `created_at`)
- `direction` (optional): Sort direction — `asc` or `desc` (default: `desc`)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Results per page (default: 25, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Receipts retrieved",
  "data": [
    {
      "id": 456,
      "merchant": { "id": 789, "name": "Whole Foods Market" },
      "total_amount": "45.67",
      "tax_amount": "3.42",
      "currency": "USD",
      "receipt_date": "2025-02-12T00:00:00Z",
      "summary": "Groceries including organic produce",
      "note": "Weekly shopping",
      "receipt_description": "Grocery receipt",
      "category": { "id": 10, "name": "Groceries" },
      "tags": [{ "id": 1, "name": "personal" }],
      "line_items": [
        {
          "id": 101,
          "description": "Organic Milk",
          "amount": "5.99",
          "quantity": "1.00",
          "unit_price": "5.99"
        }
      ]
    }
  ],
  "pagination": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 1, "from": 1, "to": 1 },
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "timestamp": "2025-02-12T10:00:00Z"
}
```

### View Receipt
- `GET /receipts/{receipt_id}`
- Auth: required; owner only
- Returns a single receipt with all fields (same structure as list items).

---

## Invoices

Read-only access to extracted invoice data.

### List Invoices
- `GET /invoices`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `payment_status` (optional): Filter by payment status
- `date_from` (optional): Filter by invoice date (ISO date)
- `date_to` (optional): Filter by invoice date (ISO date)
- `sort` (optional): `invoice_date`, `due_date`, `total_amount`, `created_at` (default: `created_at`)
- `direction` (optional): `asc` or `desc` (default: `desc`)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Results per page (default: 25, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Invoices retrieved",
  "data": [
    {
      "id": 12,
      "file_id": 124,
      "invoice_number": "INV-2025-042",
      "invoice_type": "standard",
      "from_name": "ACME Corp",
      "from_address": "123 Main St",
      "from_vat_number": "VAT123",
      "from_email": "billing@acme.com",
      "from_phone": "+1-555-0100",
      "to_name": "Jane Doe",
      "to_address": "456 Oak Ave",
      "to_vat_number": null,
      "to_email": "jane@example.com",
      "to_phone": null,
      "invoice_date": "2025-02-01",
      "due_date": "2025-03-01",
      "delivery_date": null,
      "subtotal": "1000.00",
      "tax_amount": "250.00",
      "discount_amount": "0.00",
      "shipping_amount": "0.00",
      "total_amount": "1250.00",
      "amount_paid": "0.00",
      "amount_due": "1250.00",
      "currency": "USD",
      "payment_method": "bank_transfer",
      "payment_status": "unpaid",
      "payment_terms": "Net 30",
      "purchase_order_number": null,
      "reference_number": "REF-001",
      "notes": "Please pay within 30 days",
      "merchant": { "id": 10, "name": "ACME Corp" },
      "category": { "id": 5, "name": "Finance", "color": "#EF4444" },
      "line_items": [
        {
          "id": 50,
          "description": "Consulting services",
          "quantity": 10,
          "unit_price": "100.00",
          "total_amount": "1000.00"
        }
      ],
      "tags": [{ "id": 2, "name": "business" }],
      "created_at": "2025-02-12T10:00:00Z",
      "updated_at": "2025-02-12T10:00:00Z"
    }
  ],
  "pagination": { "..." : "..." },
  "links": { "..." : "..." },
  "timestamp": "2025-02-12T10:00:00Z"
}
```

### View Invoice
- `GET /invoices/{invoice_id}`
- Auth: required; owner only
- Returns a single invoice with all fields.

---

## Contracts

Read-only access to extracted contract data.

### List Contracts
- `GET /contracts`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `status` (optional): Filter by contract status
- `contract_type` (optional): Filter by contract type
- `date_from` (optional): Filter by effective date (ISO date)
- `date_to` (optional): Filter by effective date (ISO date)
- `sort` (optional): `effective_date`, `expiry_date`, `contract_value`, `created_at` (default: `created_at`)
- `direction` (optional): `asc` or `desc` (default: `desc`)
- `page` / `per_page`: Pagination (default: 25, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Contracts retrieved",
  "data": [
    {
      "id": 89,
      "file_id": 124,
      "contract_number": "CTR-2025-001",
      "contract_title": "Service Agreement - Vendor ABC",
      "contract_type": "service",
      "parties": ["Company X", "Vendor ABC"],
      "effective_date": "2025-01-01",
      "expiry_date": "2025-12-31",
      "signature_date": "2024-12-15",
      "duration": "12 months",
      "renewal_terms": "Auto-renew annually",
      "termination_conditions": "30-day written notice",
      "contract_value": "50000.00",
      "currency": "USD",
      "payment_schedule": "Monthly",
      "governing_law": "State of California",
      "jurisdiction": "San Francisco, CA",
      "status": "active",
      "key_terms": ["Non-compete", "Confidentiality"],
      "obligations": ["Monthly reports", "Quarterly reviews"],
      "summary": "Annual service contract for IT maintenance",
      "tags": [{ "id": 3, "name": "important" }],
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    }
  ],
  "pagination": { "..." : "..." },
  "links": { "..." : "..." },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### View Contract
- `GET /contracts/{contract_id}`
- Auth: required; owner only
- Returns a single contract with all fields.

---

## Bank Statements

Read-only access to extracted bank statement data with transactions.

### List Bank Statements
- `GET /bank-statements`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `bank_name` (optional): Filter by bank name (partial match, max 255 chars)
- `date_from` (optional): Filter by statement date (ISO date)
- `date_to` (optional): Filter by statement date (ISO date)
- `sort` (optional): `statement_date`, `opening_balance`, `closing_balance`, `created_at` (default: `created_at`)
- `direction` (optional): `asc` or `desc` (default: `desc`)
- `page` / `per_page`: Pagination (default: 25, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Bank statements retrieved",
  "data": [
    {
      "id": 5,
      "file_id": 200,
      "bank_name": "Chase Bank",
      "account_holder_name": "Jane Doe",
      "account_number": "****1234",
      "iban": null,
      "swift_code": null,
      "statement_date": "2025-01-31",
      "statement_period_start": "2025-01-01",
      "statement_period_end": "2025-01-31",
      "opening_balance": "5000.00",
      "closing_balance": "4250.00",
      "currency": "USD",
      "total_credits": "2000.00",
      "total_debits": "2750.00",
      "transaction_count": 25,
      "transactions": null,
      "tags": [{ "id": 4, "name": "monthly" }],
      "created_at": "2025-02-01T00:00:00Z",
      "updated_at": "2025-02-01T00:00:00Z"
    }
  ],
  "pagination": { "..." : "..." },
  "links": { "..." : "..." },
  "timestamp": "2025-02-01T00:00:00Z"
}
```

**Note:** `transactions` are only included when viewing a single bank statement (not in the list).

### View Bank Statement
- `GET /bank-statements/{bank_statement_id}`
- Auth: required; owner only
- Returns a single bank statement with full transaction list.

#### Transaction Object
```json
{
  "id": 100,
  "bank_statement_id": 5,
  "transaction_date": "2025-01-15",
  "posting_date": "2025-01-15",
  "description": "GROCERY STORE",
  "reference": "TXN-001",
  "transaction_type": "debit",
  "category": "Groceries",
  "category_group": "food_and_dining",
  "subcategory": "Supermarket",
  "amount": "-45.67",
  "balance_after": "4954.33",
  "currency": "USD",
  "counterparty_name": "Whole Foods",
  "counterparty_account": null
}
```

---

## Vouchers

Read-only access to extracted voucher/gift card data.

### List Vouchers
- `GET /vouchers`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `voucher_type` (optional): Filter by voucher type
- `is_redeemed` (optional): Filter by redeemed status (`true` or `false`)
- `sort` (optional): `expiry_date`, `original_value`, `current_value`, `created_at` (default: `created_at`)
- `direction` (optional): `asc` or `desc` (default: `desc`)
- `page` / `per_page`: Pagination (default: 25, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Vouchers retrieved",
  "data": [
    {
      "id": 3,
      "file_id": 150,
      "voucher_type": "gift_card",
      "code": "GIFT-2025-ABC",
      "barcode": "123456789",
      "issue_date": "2025-01-01",
      "expiry_date": "2026-01-01",
      "original_value": "100.00",
      "current_value": "75.00",
      "currency": "USD",
      "is_redeemed": false,
      "redeemed_at": null,
      "redemption_location": null,
      "terms_and_conditions": "Valid at any location",
      "restrictions": "Non-transferable",
      "merchant": { "id": 15, "name": "Amazon" },
      "tags": [],
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    }
  ],
  "pagination": { "..." : "..." },
  "links": { "..." : "..." },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### View Voucher
- `GET /vouchers/{voucher_id}`
- Auth: required; owner only

---

## Warranties

Read-only access to extracted warranty data.

### List Warranties
- `GET /warranties`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `warranty_type` (optional): Filter by warranty type
- `manufacturer` (optional): Filter by manufacturer name (partial match, max 255 chars)
- `sort` (optional): `warranty_end_date`, `purchase_date`, `created_at` (default: `created_at`)
- `direction` (optional): `asc` or `desc` (default: `desc`)
- `page` / `per_page`: Pagination (default: 25, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Warrantys retrieved",
  "data": [
    {
      "id": 7,
      "file_id": 180,
      "product_name": "MacBook Pro 16\"",
      "product_category": "Electronics",
      "manufacturer": "Apple",
      "model_number": "A2485",
      "serial_number": "C02XR1234567",
      "purchase_date": "2025-01-15",
      "warranty_start_date": "2025-01-15",
      "warranty_end_date": "2026-01-15",
      "warranty_duration": "12 months",
      "warranty_type": "manufacturer",
      "warranty_provider": "Apple Inc.",
      "warranty_number": "WRN-001",
      "coverage_type": "full",
      "coverage_description": "Hardware defects and manufacturing issues",
      "exclusions": "Accidental damage, water damage",
      "support_phone": "+1-800-275-2273",
      "support_email": "support@apple.com",
      "support_website": "https://support.apple.com",
      "tags": [],
      "created_at": "2025-01-15T00:00:00Z",
      "updated_at": "2025-01-15T00:00:00Z"
    }
  ],
  "pagination": { "..." : "..." },
  "links": { "..." : "..." },
  "timestamp": "2025-01-15T00:00:00Z"
}
```

### View Warranty
- `GET /warranties/{warranty_id}`
- Auth: required; owner only

---

## Tags

Manage user tags for organizing files.

### List Tags
- `GET /tags`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `search` (optional): Filter tags by name (partial match)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Results per page (default: 50, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Business",
      "slug": "business",
      "color": "#3B82F6",
      "usage_count": 15,
      "owner_id": 1,
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1,
    "from": 1,
    "to": 1
  },
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "timestamp": "2025-01-15T10:00:00Z"
}
```

### Create Tag
- `POST /tags`
- Auth: required

#### Request Body (JSON)
```json
{
  "name": "Business",
  "color": "#3B82F6"
}
```

- `name` (required): Tag name (max 255 chars)
- `color` (optional): Hex color code (max 7 chars, e.g., `#3B82F6`). Random color assigned if not provided.

#### Success Response (201)
```json
{
  "status": "success",
  "message": "Tag created successfully",
  "data": {
    "id": 1,
    "name": "Business",
    "slug": "business",
    "color": "#3B82F6",
    "owner_id": 1,
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-01-15T10:00:00Z"
  },
  "timestamp": "2025-01-15T10:00:00Z"
}
```

#### Error Response (409 Conflict)
Returned when a tag with the same name already exists for this user.

### Update Tag
- `PATCH /tags/{tag_id}`
- Auth: required; owner only (403 if not owner)

#### Request Body (JSON)
```json
{
  "name": "Work",
  "color": "#EF4444"
}
```

Both fields are optional.

#### Success Response (200)
Returns the updated tag (same structure as create).

### Delete Tag
- `DELETE /tags/{tag_id}`
- Auth: required; owner only (403 if not owner)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Tag deleted successfully",
  "data": null,
  "timestamp": "2025-01-15T12:00:00Z"
}
```

---

## Collections

Manage user collections for grouping files.

### List Collections
- `GET /collections`
- Auth: required
- Rate limit: 200 requests/minute

#### Query Parameters
- `search` (optional): Filter collections by name or description (partial match)
- `archived` (optional): Filter by archived status (`true` or `false`)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Results per page (default: 50, max: 100)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Tax Documents 2025",
      "slug": "tax-documents-2025",
      "description": "All tax-related documents for 2025",
      "icon": "folder",
      "color": "#3B82F6",
      "is_archived": false,
      "files_count": 12,
      "owner_id": 1,
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1,
    "from": 1,
    "to": 1
  },
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "timestamp": "2025-01-15T10:00:00Z"
}
```

### Create Collection
- `POST /collections`
- Auth: required

#### Request Body (JSON)
```json
{
  "name": "Tax Documents 2025",
  "description": "All tax-related documents for 2025",
  "icon": "folder",
  "color": "#3B82F6"
}
```

- `name` (required): Collection name (max 255 chars)
- `description` (optional): Description (max 1000 chars)
- `icon` (optional): Icon name from allowed list (default: `folder`)
  - Allowed icons: `folder`, `folder-open`, `document`, `document-text`, `receipt-refund`, `briefcase`, `shopping-bag`, `home`, `heart`, `star`, `tag`, `archive-box`, `building-office`, `credit-card`, `currency-dollar`, `calendar`, `clipboard`, `cog`, `cube`, `gift`, `key`, `truck`, `wrench`, `camera`, `book-open`
- `color` (optional): Hex color code. Random color assigned if not provided.

#### Success Response (201)
```json
{
  "status": "success",
  "message": "Collection created successfully",
  "data": {
    "id": 1,
    "name": "Tax Documents 2025",
    "slug": "tax-documents-2025",
    "description": "All tax-related documents for 2025",
    "icon": "folder",
    "color": "#3B82F6",
    "is_archived": false,
    "owner_id": 1,
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-01-15T10:00:00Z"
  },
  "timestamp": "2025-01-15T10:00:00Z"
}
```

#### Error Response (409 Conflict)
Returned when a collection with the same name already exists for this user.

### Update Collection
- `PATCH /collections/{collection_id}`
- Auth: required; owner only (403 if not owner)

#### Request Body (JSON)
```json
{
  "name": "Tax Documents 2025 (Final)",
  "description": "Updated description",
  "icon": "archive-box",
  "color": "#10B981",
  "is_archived": false
}
```

All fields are optional. Only provided fields will be updated.

#### Success Response (200)
Returns the updated collection (same structure as create, with `files_count`).

### Delete Collection
- `DELETE /collections/{collection_id}`
- Auth: required; owner only (403 if not owner)
- Note: Deleting a collection does not delete the files within it.

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Collection deleted successfully",
  "data": null,
  "timestamp": "2025-01-15T12:00:00Z"
}
```

---

## Bulk Upload (Uplink)

Multi-file upload using S3 presigned URLs. Designed for batch uploading large numbers of files efficiently.

### Flow Overview
1. **Create session** with a file manifest (filenames, sizes, hashes)
2. **Get presigned URLs** for each file
3. **Upload files directly to S3** using the presigned PUT URLs
4. **Confirm each file** after upload to trigger processing
5. **Monitor session status** for completion

### Create Bulk Upload Session
- `POST /bulk/sessions`
- Auth: required
- Rate limit: 10 requests/minute

#### Request Body (JSON)
```json
{
  "file_type": "receipt",
  "collection_ids": [1, 2],
  "tag_ids": [5],
  "note": "Batch import - January receipts",
  "files": [
    {
      "filename": "receipt-001.jpg",
      "path": "2025/January/receipt-001.jpg",
      "size": 245678,
      "hash": "sha256:82e15db53ee6b07becdfed5f196a87bf116f992835ce35f6d4bb4142df62fa4f",
      "extension": "jpg",
      "mime_type": "image/jpeg",
      "file_type": "receipt",
      "collection_ids": [3],
      "tag_ids": [6],
      "note": "Specific note for this file"
    }
  ]
}
```

**Session-level fields (applied as defaults to all files):**
- `file_type` (required): `receipt` or `document`
- `collection_ids` (optional): Default collection IDs
- `tag_ids` (optional): Default tag IDs
- `note` (optional, max 1000 chars): Default note

**Per-file fields:**
- `filename` (required): Original filename (max 255 chars)
- `path` (optional): Original file path for reference (max 1000 chars)
- `size` (required): File size in bytes (max 100MB)
- `hash` (required): SHA-256 hash, optionally prefixed with `sha256:`
- `extension` (required): File extension (must be a supported format)
- `mime_type` (required): MIME type (max 100 chars)
- `file_type` (optional): Override session-level `file_type`
- `collection_ids` / `tag_ids` / `note` (optional): Override session-level defaults

**Limits:** Max 10,000 files per session.

#### Success Response (201)
```json
{
  "status": "success",
  "message": "Bulk upload session created",
  "data": {
    "session_id": "uuid-session-id",
    "status": "pending",
    "total_files": 5,
    "duplicate_files": 1,
    "uploadable_files": 4,
    "expires_at": "2025-02-13T10:00:00+00:00",
    "files": [
      {
        "uuid": "uuid-file-1",
        "filename": "receipt-001.jpg",
        "status": "pending",
        "error_message": null
      },
      {
        "uuid": "uuid-file-2",
        "filename": "receipt-002.jpg",
        "status": "duplicate",
        "error_message": "File already exists"
      }
    ]
  },
  "timestamp": "2025-02-12T10:00:00Z"
}
```

### List Bulk Upload Sessions
- `GET /bulk/sessions`
- Auth: required
- Returns paginated list of the user's bulk upload sessions (15 per page).

### View Bulk Upload Session
- `GET /bulk/sessions/{session_uuid}`
- Auth: required; owner only

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "session_id": "uuid-session-id",
    "status": "processing",
    "expires_at": "2025-02-13T10:00:00+00:00",
    "completed_at": null,
    "summary": {
      "total": 5,
      "pending": 2,
      "uploading": 1,
      "processing": 1,
      "completed": 1,
      "failed": 0,
      "duplicate": 0
    },
    "files": [
      {
        "uuid": "uuid-file-1",
        "filename": "receipt-001.jpg",
        "original_path": "2025/January/receipt-001.jpg",
        "status": "completed",
        "file_id": 123,
        "job_id": "job-uuid",
        "error_message": null
      }
    ]
  },
  "timestamp": "2025-02-12T10:30:00Z"
}
```

### Get Presigned URLs (Batch)
- `POST /bulk/sessions/{session_uuid}/presign`
- Auth: required; owner only
- Rate limit: 60 requests/minute

#### Request Body (JSON)
```json
{
  "file_uuids": ["uuid-file-1", "uuid-file-2"]
}
```

- `file_uuids` (required): Array of file UUIDs (min: 1, max: 50)

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "presigned": [
      {
        "uuid": "uuid-file-1",
        "url": "https://s3.amazonaws.com/bucket/path?X-Amz-...",
        "expires_at": "2025-02-12T11:00:00Z"
      }
    ]
  },
  "timestamp": "2025-02-12T10:00:00Z"
}
```

### Get Presigned URL (Single File Retry)
- `POST /bulk/sessions/{session_uuid}/files/{file_uuid}/presign`
- Auth: required; owner only
- Rate limit: 60 requests/minute
- Use when a presigned URL has expired and needs to be regenerated.

### Confirm File Upload
- `POST /bulk/sessions/{session_uuid}/files/{file_uuid}/confirm`
- Auth: required; owner only
- Rate limit: 600 requests/minute
- Call after uploading the file to S3 to trigger processing.

#### Success Response (200)
```json
{
  "status": "success",
  "message": "File confirmed and processing started",
  "data": {
    "file_id": 123,
    "file_guid": "file-guid-uuid",
    "job_id": "job-uuid",
    "status": "processing"
  },
  "timestamp": "2025-02-12T10:05:00Z"
}
```

### Cancel Bulk Upload Session
- `POST /bulk/sessions/{session_uuid}/cancel`
- Auth: required; owner only
- Cancels the session and cleans up unprocessed files.

#### Success Response (200)
```json
{
  "status": "success",
  "message": "Session cancelled",
  "data": null,
  "timestamp": "2025-02-12T11:00:00Z"
}
```

---

## Standard Response Format

All API responses follow a consistent envelope:

### Success
```json
{
  "status": "success",
  "message": "Description",
  "data": { ... },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### Paginated Success
```json
{
  "status": "success",
  "message": "Description",
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 25,
    "total": 120,
    "from": 1,
    "to": 25
  },
  "links": {
    "first": "...?page=1",
    "last": "...?page=5",
    "prev": null,
    "next": "...?page=2"
  },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### Error
```json
{
  "status": "error",
  "code": "ERROR_CODE",
  "message": "Description",
  "errors": { ... },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

**Error codes:** `BAD_REQUEST`, `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `DUPLICATE_FILE`, `VALIDATION_ERROR`, `RATE_LIMITED`, `INTERNAL_ERROR`

---

## Other Routes Outside `/v1`
- `GET /api/health`: Health check (no auth). Returns status of database, Redis, Meilisearch, and queue.
- `POST /api/webdav/auth`: PulseDav auth (used by sync feature).
- `/api/documents/{id}/shares`, `/api/receipts/{id}/shares`, `/api/batch/*`: Internal/web-facing APIs. External clients should use `/api/v1` endpoints.

---

## Rate Limits

| Endpoint | Limit |
|----------|-------|
| `POST /auth/login` | 5 per 15 minutes |
| `POST /bulk/sessions` | 10 per minute |
| `POST /bulk/sessions/*/presign` | 60 per minute |
| `POST /bulk/sessions/*/files/*/presign` | 60 per minute |
| `POST /bulk/sessions/*/files/*/confirm` | 600 per minute |
| All other authenticated endpoints | 200 per minute |
