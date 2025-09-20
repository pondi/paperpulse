# Changelog

All notable changes to this project will be documented in this file.

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
