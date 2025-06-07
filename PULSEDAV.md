# PulseDAV Integration Guide for Laravel External API

This document provides comprehensive information about the PulseDAV WebDAV server implementation to help developers building the Laravel external API understand the file storage structure, authentication mechanisms, and integration requirements.

## Overview

PulseDAV is a WebDAV server written in Go that provides file upload capabilities with S3 backend storage. It supports both external API authentication and local authentication modes, making it flexible for different deployment scenarios.

## File Storage Structure in S3

### Storage Path Pattern
Files uploaded through PulseDAV are stored in S3 with the following structure:
```
s3://{bucket}/incoming/{userID}/{filename}
```

- **bucket**: Configured via `S3_BUCKET` environment variable
- **userID**: Obtained from authentication (either API response or local config)
- **filename**: Original filename, sanitized for security

### Example S3 Structure
```
your-bucket/
├── incoming/
│   ├── 123/                    # User ID 123
│   │   ├── report.pdf
│   │   ├── data.xlsx
│   │   └── image.jpg
│   ├── 456/                    # User ID 456
│   │   └── document.docx
│   └── ...
└── webdav/
    └── production/             # Environment name
        └── webdav-server/
            └── (log files)
```

### Key Points for Laravel API
- Files are stored flat within user directories (no subdirectories)
- Original filenames are preserved but sanitized
- Each user's files are isolated in their own directory
- The `incoming/` prefix is hardcoded in the WebDAV server

## Authentication and User Identification

### API Authentication Mode (Recommended for Laravel Integration)

When `API_AUTH=true`, PulseDAV sends authentication requests to your Laravel API.

#### Request Format
```http
POST {API_AUTH_URL}
Content-Type: application/json
Authorization: Basic {base64(username:password)}

{
    "username": "user@example.com",
    "password": "userpassword"
}
```

#### Expected Response
```json
{
    "user_id": 123,
    "username": "user@example.com"
}
```

#### Important Notes
- The `user_id` from your response is used to construct the S3 path
- Must be a valid integer or string that can be used in file paths
- This ID should be consistent across sessions for the same user
- Response must be JSON with correct content-type header

### Local Authentication Mode

When `API_AUTH=false`, authentication uses environment variables:
- `LOCAL_AUTH_USERNAME`: Expected username
- `LOCAL_AUTH_PASSWORD`: Expected password
- `LOCAL_AUTH_USER_ID`: User ID for S3 paths (defaults to "1")

## File Upload Process

### 1. Client Authentication
- Client sends WebDAV PUT request with Basic Auth headers
- PulseDAV validates credentials against configured auth method
- User ID is obtained for S3 path construction

### 2. File Validation
Before accepting uploads, PulseDAV validates:

#### Allowed File Extensions
- `.txt`, `.pdf`, `.doc`, `.docx`
- `.xls`, `.xlsx`
- `.jpg`, `.jpeg`, `.png`, `.gif`
- `.zip`, `.csv`

#### Size Limits
- Maximum file size: 100MB
- Request size limit: 110MB (to account for multipart overhead)

#### Security Checks
- Path traversal protection
- Filename sanitization
- Content-Length validation
- No transfer encoding allowed

### 3. S3 Upload
- File is read into memory (with size limit)
- Uploaded to S3 at: `incoming/{userID}/{filename}`
- Audit log entry created on success

### 4. Response
- Success: HTTP 201 Created
- Failure: WebDAV-formatted error response

## WebDAV Operations

### Supported Methods
- **OPTIONS**: Returns server capabilities
- **PROPFIND**: Directory listing (depth 0 or 1 only)
- **PUT**: File upload

### Example WebDAV Request
```http
PUT /test-file.pdf HTTP/1.1
Host: webdav.example.com
Authorization: Basic dXNlcjpwYXNzd29yZA==
Content-Type: application/pdf
Content-Length: 1024000

[binary file data]
```

## Security and Rate Limiting

### Authentication Security
- Rate limit: 10 auth attempts per minute per IP
- Lockout: 5 failed attempts triggers 15-minute lockout
- Constant-time password comparison
- All auth attempts are audit logged

### Request Rate Limiting
- Global: 100 requests per minute per IP
- Automatic cleanup of old rate limit entries
- Returns 429 Too Many Requests when exceeded

## Logging and Monitoring

### Log Storage Locations
1. **Console**: Real-time output
2. **S3**: `webdav/{environment}/webdav-server/`

### Log Types
- **Request Logs**: All HTTP requests with timing
- **Audit Logs**: Authentication attempts and uploads
- **Error Logs**: System errors and failures

### Log Format
```json
{
    "timestamp": "2024-01-20T10:30:00Z",
    "level": "info",
    "message": "File uploaded successfully",
    "trace_id": "abc-123",
    "user_id": "123",
    "filename": "report.pdf",
    "size": 1024000
}
```

## Laravel API Implementation Requirements

### 1. Authentication Endpoint
Create an endpoint that:
- Accepts POST requests with Basic Auth
- Validates credentials against your user database
- Returns user_id and username in JSON format
- Handles rate limiting appropriately

### 2. S3 Access Configuration
- Use the same S3 bucket as PulseDAV
- Access files at: `incoming/{userID}/{filename}`
- Implement appropriate IAM permissions

### 3. User ID Management
- Ensure consistent user IDs across systems
- IDs must be valid for use in file paths
- Consider using numeric IDs for simplicity

### 4. File Processing
When processing uploaded files:
- Monitor the `incoming/{userID}/` directories
- Process files asynchronously if possible
- Consider moving processed files to archive locations
- Implement cleanup policies for old files

### 5. Error Handling
- Handle missing files gracefully
- Implement retry logic for S3 operations
- Log errors for debugging

## Environment Variables Reference

### Required
- `S3_BUCKET`: S3 bucket name
- `S3_REGION`: AWS region (or S3-compatible region)

### Authentication
- `API_AUTH`: "true" for API auth, "false" for local
- `API_AUTH_URL`: Your Laravel API endpoint (if API_AUTH=true)
- `LOCAL_AUTH_USERNAME`: Username (if API_AUTH=false)
- `LOCAL_AUTH_PASSWORD`: Password (if API_AUTH=false)
- `LOCAL_AUTH_USER_ID`: User ID for S3 paths (if API_AUTH=false)

### Optional S3 Configuration
- `S3_ENDPOINT`: Custom S3 endpoint for compatible services
- `S3_ACCESS_KEY_ID`: AWS access key
- `S3_SECRET_ACCESS_KEY`: AWS secret key
- `S3_USE_PATH_STYLE`: "true" for path-style URLs

## Integration Checklist

- [ ] Implement authentication endpoint returning user_id
- [ ] Configure S3 access with same bucket
- [ ] Map user IDs consistently between systems
- [ ] Implement file processing for `incoming/{userID}/` paths
- [ ] Set up monitoring for upload failures
- [ ] Configure appropriate S3 lifecycle policies
- [ ] Test with various file types and sizes
- [ ] Implement error handling and logging
- [ ] Set up alerts for authentication failures
- [ ] Document user ID mapping strategy

## Common Integration Patterns

### Asynchronous File Processing
```php
// Laravel job to process uploaded files
$files = Storage::disk('s3')->files("incoming/{$userId}");
foreach ($files as $file) {
    ProcessUploadedFile::dispatch($userId, $file);
}
```

### Authentication Endpoint Example
```php
Route::post('/api/webdav/auth', function (Request $request) {
    $credentials = [
        'email' => $request->input('username'),
        'password' => $request->input('password')
    ];
    
    if (Auth::attempt($credentials)) {
        return response()->json([
            'user_id' => Auth::id(),
            'username' => Auth::user()->email
        ]);
    }
    
    return response()->json(['error' => 'Invalid credentials'], 401);
})->middleware('throttle:10,1');
```

## Troubleshooting

### Common Issues
1. **Files not appearing in S3**: Check user_id format and S3 permissions
2. **Authentication failures**: Verify API endpoint returns correct JSON format
3. **Upload failures**: Check file size and extension restrictions
4. **S3 access errors**: Verify IAM roles and bucket policies

### Debug Steps
1. Check PulseDAV logs in S3 under `webdav/{environment}/`
2. Verify authentication endpoint responds correctly
3. Test S3 access with AWS CLI
4. Monitor Laravel logs for API errors
5. Use correlation IDs (X-Request-ID) to trace requests

## Security Considerations

1. **User Isolation**: Files are isolated by user ID in S3
2. **Path Traversal**: Protected against directory traversal attacks
3. **File Validation**: Strict extension and size limits
4. **Rate Limiting**: Prevents brute force attacks
5. **Audit Logging**: All operations are logged for security review

## Contact and Support

For issues or questions about PulseDAV integration:
- Review S3 audit logs for detailed operation history
- Check WebDAV server logs for error details
- Ensure your Laravel API matches the expected interface
- Verify S3 bucket permissions and policies