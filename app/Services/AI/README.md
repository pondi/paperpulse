# AI Services Documentation

This directory contains the AI integration services for PaperPulse, replacing the helgesverre/receipt-scanner dependency with a flexible, provider-agnostic AI service architecture.

## Architecture

### Core Components

1. **AIService Interface** (`AIService.php`)
   - Defines the contract for all AI providers
   - Methods for receipt analysis, document analysis, entity extraction, etc.

2. **AIServiceFactory** (`AIServiceFactory.php`)
   - Creates AI provider instances based on configuration
   - Supports fallback providers
   - Caches instances for performance

3. **Providers**
   - **OpenAIProvider** - Uses OpenAI GPT models
   - **AnthropicProvider** - Uses Anthropic Claude models

### Analysis Services

1. **ReceiptAnalysisService** (`app/Services/ReceiptAnalysisService.php`)
   - Analyzes receipt OCR text
   - Creates Receipt and LineItem records
   - Handles merchant matching

2. **DocumentAnalysisService** (`app/Services/DocumentAnalysisService.php`)
   - Analyzes general documents
   - Extracts metadata, entities, and summaries
   - Auto-categorization and tagging

## Configuration

### Environment Variables

```env
# AI Provider Selection
AI_PROVIDER=openai  # or 'anthropic'

# OpenAI Configuration
OPENAI_API_KEY=sk-...
AI_MODEL_RECEIPT=gpt-3.5-turbo
AI_MODEL_DOCUMENT=gpt-4

# Anthropic Configuration
ANTHROPIC_API_KEY=sk-ant-...
AI_MODEL_ANTHROPIC_RECEIPT=claude-3-haiku-20240307
AI_MODEL_ANTHROPIC_DOCUMENT=claude-3-sonnet-20240229
```

### Config File

See `config/ai.php` for detailed configuration options:
- Model selection per task type
- Temperature settings
- Token limits
- Feature flags

## Usage Examples

### Basic Usage

```php
use App\Services\AI\AIServiceFactory;

// Create AI service with default provider
$aiService = AIServiceFactory::create();

// Create with specific provider
$aiService = AIServiceFactory::create('anthropic');

// Create with fallback support
$aiService = AIServiceFactory::createWithFallback(['openai', 'anthropic']);
```

### Receipt Analysis

```php
use App\Services\ReceiptAnalysisService;

$receiptService = app(ReceiptAnalysisService::class);

// Analyze and create receipt
$receipt = $receiptService->analyzeAndCreateReceipt(
    $ocrText,
    $fileId,
    $userId
);

// Extract specific data
$merchantInfo = $receiptService->extractMerchantInfo($ocrText);
$lineItems = $receiptService->extractLineItems($ocrText);
```

### Document Analysis

```php
use App\Services\DocumentAnalysisService;

$documentService = app(DocumentAnalysisService::class);

// Analyze and create document
$document = $documentService->analyzeAndCreateDocument(
    $content,
    $fileId,
    $userId
);

// Generate summary
$summary = $documentService->generateSummary($content, 200);

// Suggest tags
$tags = $documentService->suggestTags($content, 5);

// Classify document
$type = $documentService->classifyDocument($content);
```

## Testing

### Unit Tests

```bash
php artisan test --filter AIServiceTest
```

### Command Line Testing

```bash
# Test AI service creation
php artisan ai:test openai

# Test receipt analysis
php artisan ai:test openai --receipt

# Test document analysis
php artisan ai:test anthropic --document
```

## Provider-Specific Notes

### OpenAI
- Uses function calling for structured output
- Supports both chat and completion models
- Better for English documents

### Anthropic
- Uses Claude models with structured prompts
- Good for multi-language support
- More cost-effective for simple tasks

## Migration from receipt-scanner

The new AI services maintain compatibility with existing data structures while providing enhanced functionality:

1. **Receipt Processing**: Same workflow through jobs (ProcessFile → ProcessReceipt → MatchMerchant)
2. **Data Structure**: Compatible with existing Receipt and LineItem models
3. **Enhanced Features**: Better error handling, retry logic, and provider flexibility

## Error Handling

All AI services include:
- Automatic retry on failure
- Detailed logging
- Graceful fallback
- Structured error responses

## Performance Considerations

1. **Caching**: Results are cached to avoid duplicate API calls
2. **Token Optimization**: Automatic truncation of long texts
3. **Batch Processing**: Support for processing multiple documents
4. **Provider Selection**: Choose appropriate models for each task type

## Future Enhancements

- Support for additional providers (Google AI, local models)
- Fine-tuned models for specific document types
- Multi-modal analysis (images, PDFs)
- Streaming responses for large documents