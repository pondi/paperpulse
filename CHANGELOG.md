# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2025-12-17

### Added
- File management enhancements
  - SHA-256 deduplication to prevent duplicate uploads
  - Failed file reprocessing with management UI
  - Expanded file statuses and URL management
  - Orphan file record cleanup
  - Configurable pagination for file processing views
  - File streaming via API v1

- Search improvements
  - Natural language OR search with multi-word ranking
  - Unified search endpoint for receipts and documents
  - API v1 search endpoint

- PulseDav enhancements
  - Select all files in folder functionality
  - Document ID support for better tracking

- Job system improvements
  - Enhanced job history with pagination and clickable statistics
  - Hardened monitoring and restart capabilities
  - Improved failure persistence with sanitization

- UI/UX updates
  - Complete Inertia/Vue styling redesign
  - Modernized layouts and navigation
  - Theme toggle redesigned as icon button
  - Refreshed email templates
  - Modernized invitation intake flow

- AI and language features
  - AI outputs now match document language automatically

### Changed
- AI provider migration to gpt-5.2
- Textract processing optimized for better API usage and artifact handling
- Receipt processing now allows operations without merchant information
- Console commands consolidated and cleaned up
- Converted PDFs renamed to archive variant for clarity
- Original file dates now preserved from scanner imports
- Kubernetes deployment support with Horizon fast termination

### Fixed
- Security improvements with authorization checks and enhanced API error responses
- PulseDav race condition in UpdatePulseDavFileStatus job
- GPT-5.2 integration and token limit issues
- Watch functionality prevented from firing on initial mount
- Logging configuration handles empty env vars correctly

## [1.0.0] - 2025-01-07

### Added
- Receipt management
  - Upload via web and PulseDav; view images/PDFs
  - OCR with AWS Textract and AI extraction (OpenAI) to structured data and line items
  - Merchant detection with logo generation, tagging, categories, and sharing (view/edit permissions)
  - Bulk actions: export (CSV/PDF), categorize, delete; analytics dashboard
  - Full‑text search (Meilisearch) with filters and facets

- Document management (beta, feature‑flagged)
  - Upload and process general documents; OCR + AI analysis to title/summary/metadata
  - Suggested categories and tags; search, filters, and categories view
  - Tagging and secure sharing; bulk delete/download; original file download
  - REST API (Sanctum): CRUD, share/unshare, download

- PulseDav WebDAV integration
  - S3‑backed ingestion with scheduled and near‑real‑time sync
  - Folder hierarchy browsing, selection import with tags, temporary download URLs
  - Cleanup and notification jobs for imported/archived files

- Search and discovery
  - Global search endpoint with result facets; Meilisearch indexing for receipts/documents

- Operations and reliability
  - Horizon monitoring; queue health check CLI; retry failed receipt jobs; reliable job restarts

- Collaboration and onboarding
  - Share notifications and invitation flow for new users

- Internationalization and tenancy
  - English and Norwegian UI; strict per‑user data isolation
