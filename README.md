# PaperPulse

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE)

PaperPulse is a modern receipt digitization and management system that uses OCR and AI to automatically extract and organize receipt data. Built with Laravel 11 and Vue.js 3, it provides a seamless experience for converting physical receipts into searchable digital records.

## 🚀 Features

### Core Functionality
- **📸 Receipt Processing**: Upload receipts in multiple formats (JPEG, PNG, PDF)
- **🤖 AI-Powered OCR**: Automatic text extraction using AWS Textract
- **🧠 Smart Parsing**: OpenAI-powered receipt data extraction and categorization
- **🏪 Merchant Matching**: Automatic merchant identification and logo management
- **🔍 Full-Text Search**: Powerful search across receipts and line items using Meilisearch
- **📊 Analytics Dashboard**: Visual insights into spending patterns

### Advanced Features
- **🌐 Multi-language Support**: Available in English and Norwegian
- **🔒 Multi-tenancy**: Complete user data isolation with secure access controls
- **📂 PulseDav Integration**: WebDAV server integration for automatic receipt imports
- **📈 Real-time Job Monitoring**: Track processing status with Laravel Horizon
- **🎯 Bulk Operations**: Export, delete, and categorize multiple receipts at once
- **📧 Email Notifications**: Get notified when receipts are processed

### Technical Features
- **⚡ Laravel Octane Ready**: Optimized for high-performance with FrankenPHP
- **🔐 Enterprise Security**: CSRF protection, rate limiting, and secure authentication
- **📱 Responsive Design**: Works seamlessly on desktop and mobile devices
- **🎨 Modern UI**: Clean interface built with Tailwind CSS and Inertia.js

## 📋 Requirements

- PHP >= 8.2
- Composer
- Node.js >= 18.x
- PostgreSQL >= 14
- Redis >= 6.x
- Meilisearch >= 1.0
- ImageMagick (for PDF processing)

### External Services
- AWS account (for Textract OCR)
- OpenAI API key (for receipt parsing)
- S3-compatible storage (optional, for PulseDav integration)

## 🛠️ Installation

### 1. Clone the repository
```bash
git clone https://github.com/yourusername/paperpulse.git
cd paperpulse
```

### 2. Install dependencies
```bash
composer install
npm install
```

### 3. Environment setup
```bash
cp .env.example .env
```

Edit `.env` and configure:
- Database credentials
- Redis connection
- AWS credentials for Textract
- OpenAI API key
- Meilisearch host and key
- Mail settings

### 4. Generate application key
```bash
php artisan key:generate
```

### 5. Database setup
```bash
php artisan migrate
php artisan db:seed # Optional: seed with demo data
```

### 6. Build frontend assets
```bash
npm run build
```

### 7. Index setup
```bash
php artisan scout:import "App\Models\Receipt"
php artisan scout:import "App\Models\LineItem"
```

### 8. Start the application
```bash
# Development
php artisan serve
npm run dev # In another terminal

# Production with Octane
php artisan octane:start

# Queue workers
php artisan horizon
```

## 🐳 Docker Deployment

### Quick Start
```bash
# Copy environment file
cp .env.docker.example .env

# Edit .env with your settings
nano .env

# Build and start services
make build
make up

# Run migrations
make migrate
```

### Using Docker Compose
```bash
# Production deployment
docker-compose up -d

# Development with hot reload
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

### Available Make Commands
```bash
make build       # Build Docker images
make up          # Start production services
make up-dev      # Start development services
make down        # Stop all services
make logs        # View logs
make shell       # Access web container
make migrate     # Run migrations
make test        # Run tests
```

This will start:
- Web server (FrankenPHP with Laravel Octane)
- Caddy reverse proxy (automatic HTTPS)
- PostgreSQL database
- Redis cache
- Meilisearch
- Queue workers
- Scheduler

### Environment Variables
Key environment variables for production:

```env
APP_NAME=PaperPulse
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=paperpulse
DB_USERNAME=paperpulse
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Search
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=your_master_key

# AWS Services
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=eu-west-1

# OpenAI
OPENAI_API_KEY=your_openai_key

# PulseDav (Optional)
PULSEDAV_AUTH_ENABLED=true
AWS_S3_INCOMING_PREFIX=incoming/
```

## 📱 Usage

### Uploading Receipts
1. Navigate to the Documents page
2. Drag and drop receipt files or click to browse
3. Receipts are automatically processed in the background
4. View processing status in the Jobs monitor

### Searching Receipts
- Use the search bar to find receipts by:
  - Merchant name
  - Line item description
  - Amount
  - Date range
  - Category

### PulseDav Integration
1. Configure your WebDAV client to upload to the S3 bucket
2. Files appear in the PulseDav section
3. Select files to import
4. Monitor import progress

### Analytics
- View spending trends over time
- Top merchants by spend
- Category breakdowns
- Export data as CSV or PDF

## 🔧 Configuration

### Admin Access
Horizon dashboard is protected and requires admin privileges:

```bash
# Promote a user to admin
php artisan user:promote-admin user@example.com

# Demote an admin to regular user
php artisan user:demote-admin user@example.com
```

Admin users can access the Horizon dashboard at `/horizon` in production.

### Queue Workers
Configure Horizon in `config/horizon.php`:
```php
'environments' => [
    'production' => [
        'worker' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
        ],
    ],
],
```

### Search Configuration
Meilisearch settings in `config/scout.php`:
```php
'meilisearch' => [
    'host' => env('MEILISEARCH_HOST'),
    'key' => env('MEILISEARCH_KEY'),
    'index-settings' => [
        'receipts' => [
            'filterableAttributes' => ['user_id', 'merchant_id'],
            'searchableAttributes' => ['merchant_name', 'line_items'],
        ],
    ],
],
```

## 🧪 Testing

Run the test suite:
```bash
# All tests
php artisan test

# Specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# With coverage
php artisan test --coverage
```

## 🔒 Security

PaperPulse implements multiple security layers:

- **Multi-tenancy**: Complete user data isolation using global scopes
- **Authentication**: Laravel Breeze with email verification
- **Authorization**: Policy-based access control
- **Rate Limiting**: API and authentication throttling
- **Input Validation**: Comprehensive request validation
- **XSS Protection**: Content Security Policy headers
- **CSRF Protection**: Token-based form protection

For security concerns, please email security@paperpulse.app

## 📖 API Documentation

### Authentication Endpoint
```http
POST /api/webdav/auth
Content-Type: application/json

{
    "username": "user@example.com",
    "password": "password"
}
```

### Response
```json
{
    "user_id": 123,
    "username": "user@example.com"
}
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP framework
- [Vue.js](https://vuejs.org) - The progressive JavaScript framework
- [Inertia.js](https://inertiajs.com) - The modern monolith
- [Tailwind CSS](https://tailwindcss.com) - For beautiful styling
- [AWS Textract](https://aws.amazon.com/textract/) - OCR service
- [OpenAI](https://openai.com) - AI-powered parsing

## 📞 Support

- Documentation: [https://docs.paperpulse.app](https://docs.paperpulse.app)
- Issues: [GitHub Issues](https://github.com/yourusername/paperpulse/issues)
- Email: support@paperpulse.app

---

Built with ❤️ by the PaperPulse Team