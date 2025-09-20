# Paperpulse Documentation

## Overview

Paperpulse is a comprehensive document management system built with Laravel that automates the processing, organization, and retrieval of receipts and documents. The system leverages OCR and AI technologies to extract structured data from uploaded files, making them searchable and actionable.

### Key Features

- **Automated Document Processing** - Files are automatically processed using OCR and AI to extract meaningful data
- **Smart Receipt Handling** - Extracts vendor, amounts, dates, and line items from receipts
- **Full-Text Search** - Powered by Meilisearch for instant document discovery
- **WebDAV Support** - Upload files directly from any WebDAV-compatible client
- **Multi-format Support** - Handles PDFs, images, Word documents, Excel sheets, and more
- **Asynchronous Processing** - Background jobs ensure fast uploads and reliable processing
- **Secure Sharing** - Generate secure, time-limited links for document sharing

## Architecture

### Core Components

**Application Stack**
- Laravel 11 framework for the backend
- Vue.js 3 with Inertia.js for reactive frontend
- PostgreSQL/MySQL for data persistence
- Redis for caching and queue management
- Meilisearch for full-text search capabilities

**Processing Pipeline**
1. Files uploaded via web interface or WebDAV
2. Queued for asynchronous processing
3. OCR extraction for text content
4. AI analysis for structured data
5. Indexing for search
6. Storage in cloud (S3) or local filesystem

### Integration Points

- **AI Services** - OpenAI (extensible for custom providers)
- **Storage** - AWS S3 or compatible object storage
- **WebDAV** - Pulsedav server for desktop/mobile client uploads
- **Search** - Meilisearch for instant document retrieval

## Documentation Structure

### For Users
- [Getting Started](getting-started.md) - Installation and initial setup
- [Console Commands](cli.md) - Managing the system via CLI

### For Administrators
- [Pulsedav](pulsedav.md) - WebDAV server configuration and integration

### For Developers
- [Developer Guide](developer.md) - Building and extending Paperpulse

## System Requirements

- PHP 8.2 or higher with required extensions
- Composer for dependency management
- Node.js 18+ for frontend builds
- PostgreSQL 14+ or MySQL 8.0+
- Redis 6.0+ for queues and caching
- Meilisearch 1.0+ for search functionality

## Quick Start

1. Clone the repository and install dependencies
2. Configure your environment variables
3. Run database migrations
4. Start queue workers for background processing
5. Access the application through your web browser

For detailed instructions, see the [Getting Started](getting-started.md) guide.
