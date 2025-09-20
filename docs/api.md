# API Security Documentation

## Authentication

### PulseDav Authentication Endpoint

Endpoint: `POST /api/webdav/auth`

Rate Limiting: 10 requests per minute per IP

Request Headers:
- `Content-Type: application/json`
- `Accept: application/json`

Request Body:
```json
{
    "username": "user@example.com",
    "password": "password123"
}
```

Success Response (200 OK):
```json
{
    "user_id": 123,
    "username": "user@example.com"
}
```

Error Responses:
- 401 Unauthorized: Invalid credentials
- 403 Forbidden: PulseDav authentication disabled or email not verified
- 422 Unprocessable Entity: Invalid request format
- 429 Too Many Requests: Rate limit exceeded

## Security Features

1. Rate Limiting
- WebDav Auth: 10 requests/minute per IP
- API: 60 requests/minute per user
- File Uploads: 10 requests/minute per user
- Exports: 10 requests/hour per user

2. Security Headers
All API responses include:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Cache-Control: no-cache, no-store, must-revalidate`

3. Input Validation
- All inputs are validated and sanitized
- Email addresses are validated
- Password minimum length: 8 characters
- Maximum field lengths enforced

4. Timing Attack Prevention
- Consistent response times for authentication
- Dummy operations for non-existent users

5. Logging
- All authentication attempts are logged
- Failed attempts trigger warnings
- IP addresses and user agents recorded

## Security Best Practices

1. Always use HTTPS in production
2. Enable email verification for all users
3. Monitor rate limit violations for potential attacks
4. Review authentication logs regularly
5. Keep API keys secure and rotate regularly
6. Use strong passwords (minimum 8 characters)
7. Implement IP whitelisting for sensitive endpoints if possible

