<?php

namespace App\Services\AI\Providers;

use App\Exceptions\GeminiApiException;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf;

class GeminiProvider
{
    /**
     * Analyze a file with Gemini.
     */
    public function analyzeFile(string $filePath, array $schema, ?string $prompt = null): array
    {
        $this->ensureSupported($filePath);

        $model = config('ai.providers.gemini.model', 'gemini-2.0-flash');
        $apiKey = config('ai.providers.gemini.api_key');
        if (empty($apiKey)) {
            throw new GeminiApiException(
                'Missing Gemini API key.',
                GeminiApiException::CODE_API_ERROR,
                false,
                ['provider' => 'gemini']
            );
        }

        $fileSize = filesize($filePath) ?: 0;
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $textContext = $this->buildTextContext($filePath, $mime, $extension);
        $largeFileContext = $this->buildLargeFileContext($filePath, $mime, $fileSize, $textContext);
        $promptText = $this->buildPrompt($prompt, $schema, $textContext, $largeFileContext);

        Log::info('[GeminiProvider] Analyzing file', [
            'model' => $model,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime' => $mime,
            'schema' => $schema['name'] ?? 'unknown',
            'text_input' => $textContext !== null,
            'large_file' => $largeFileContext !== null,
        ]);

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new GeminiApiException(
                'Unable to read file contents for Gemini.',
                GeminiApiException::CODE_API_ERROR,
                false,
                ['file_path' => $filePath]
            );
        }

        // Build generation config with responseSchema
        $generationConfig = [
            'temperature' => 0.2,
            'responseMimeType' => 'application/json',
        ];

        // Add responseSchema if provided (Gemini's structured output feature)
        if (isset($schema['responseSchema'])) {
            $generationConfig['responseJsonSchema'] = $schema['responseSchema'];
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $this->buildParts($promptText, $mime, $fileContent, $textContext),
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        // Debug: Log the schema being sent
        if (isset($generationConfig['responseJsonSchema'])) {
            Log::debug('[GeminiProvider] Sending responseJsonSchema', [
                'has_schema' => true,
                'schema_type' => $generationConfig['responseJsonSchema']['type'] ?? 'unknown',
                'schema_required' => $generationConfig['responseJsonSchema']['required'] ?? [],
                'schema_properties' => array_keys($generationConfig['responseJsonSchema']['properties'] ?? []),
            ]);
        } else {
            Log::warning('[GeminiProvider] No responseJsonSchema in generation config!');
        }

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $apiKey
        );

        // Log the full request payload for debugging
        Log::info('[GeminiProvider] Full request payload', [
            'model' => $model,
            'has_responseJsonSchema' => isset($payload['generationConfig']['responseJsonSchema']),
            'generationConfig_keys' => array_keys($payload['generationConfig']),
            'schema_type' => $payload['generationConfig']['responseJsonSchema']['type'] ?? 'N/A',
            'payload_size' => strlen(json_encode($payload)),
        ]);

        try {
            $response = Http::timeout((int) config('ai.providers.gemini.timeout', 90))
                ->asJson()
                ->post($endpoint, $payload);
        } catch (Exception $e) {
            throw new GeminiApiException(
                'Gemini request failed: '.$e->getMessage(),
                GeminiApiException::CODE_API_ERROR,
                true,
                ['error' => $e->getMessage()]
            );
        }

        if (! $response->successful()) {
            $status = $response->status();
            $errorCode = $status === 429 ? GeminiApiException::CODE_RATE_LIMIT : GeminiApiException::CODE_API_ERROR;
            $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);

            throw new GeminiApiException(
                'Gemini API error: '.$response->body(),
                $errorCode,
                $retryable,
                ['status' => $status, 'body' => $response->body()]
            );
        }

        $responseBody = $response->json();
        $textResponse = $this->extractTextResponse($responseBody);
        $parsed = $this->parseJsonResponse($textResponse);
        $entities = $parsed['entities'] ?? [];
        if (! is_array($entities)) {
            throw new GeminiApiException(
                'Gemini response missing entities array.',
                GeminiApiException::CODE_RESPONSE_INVALID,
                false,
                ['response' => $parsed]
            );
        }

        // Normalize entities to expected format (Gemini sometimes ignores responseSchema)
        $entities = $this->normalizeEntities($entities, $schema);

        return [
            'provider' => 'gemini',
            'model' => $model,
            'prompt_used' => $promptText,
            'schema' => $schema,
            'mime' => $mime,
            'file_size' => $fileSize,
            'text_input' => $textContext,
            'large_file' => $largeFileContext,
            'entities' => $entities,
            'raw_response' => [
                'text' => $textResponse,
                'json' => $responseBody,
            ],
        ];
    }

    /**
     * Analyze a file using Gemini Files API (via fileUri).
     *
     * Used for two-pass extraction: file is uploaded once and referenced multiple times.
     *
     * @param  string  $fileUri  File URI from Gemini Files API (e.g., from GeminiFileManager::uploadFile)
     * @param  array  $schema  Schema configuration with 'responseSchema' key
     * @param  string  $prompt  Extraction prompt
     * @param  array  $conversationHistory  Previous messages for multi-turn conversation
     * @return array Parsed response with extracted data
     */
    public function analyzeFileByUri(
        string $fileUri,
        array $schema,
        string $prompt,
        array $conversationHistory = []
    ): array {
        $model = config('ai.providers.gemini.model', 'gemini-3-flash-preview');
        $apiKey = config('ai.providers.gemini.api_key');

        if (empty($apiKey)) {
            throw new GeminiApiException(
                'Missing Gemini API key.',
                GeminiApiException::CODE_API_ERROR,
                false,
                ['provider' => 'gemini']
            );
        }

        Log::info('[GeminiProvider] Analyzing file by URI', [
            'model' => $model,
            'file_uri' => $fileUri,
            'schema' => $schema['name'] ?? 'unknown',
            'has_conversation_history' => ! empty($conversationHistory),
        ]);

        // Build generation config
        $generationConfig = [
            'temperature' => 0.2,
            'responseMimeType' => 'application/json',
        ];

        if (isset($schema['responseSchema'])) {
            $generationConfig['responseJsonSchema'] = $schema['responseSchema'];
        }

        // Build contents array (conversation history + current message)
        $contents = $conversationHistory;

        // Add current user message with file reference
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
                [
                    'fileData' => [
                        'fileUri' => $fileUri,
                        'mimeType' => 'application/pdf', // Most common, can be made dynamic
                    ],
                ],
            ],
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => $generationConfig,
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $apiKey
        );

        Log::debug('[GeminiProvider] Sending request with fileUri', [
            'has_responseJsonSchema' => isset($generationConfig['responseJsonSchema']),
            'conversation_turns' => count($contents),
        ]);

        try {
            $response = Http::timeout((int) config('ai.providers.gemini.timeout', 90))
                ->asJson()
                ->post($endpoint, $payload);
        } catch (Exception $e) {
            throw new GeminiApiException(
                'Gemini request failed: '.$e->getMessage(),
                GeminiApiException::CODE_API_ERROR,
                true,
                ['error' => $e->getMessage()]
            );
        }

        if (! $response->successful()) {
            $status = $response->status();
            $errorCode = $status === 429 ? GeminiApiException::CODE_RATE_LIMIT : GeminiApiException::CODE_API_ERROR;
            $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);

            throw new GeminiApiException(
                'Gemini API error: '.$response->body(),
                $errorCode,
                $retryable,
                ['status' => $status, 'body' => $response->body()]
            );
        }

        $responseBody = $response->json();
        $textResponse = $this->extractTextResponse($responseBody);

        // Parse the JSON response
        $parsed = $this->parseJsonResponse($textResponse);

        return [
            'provider' => 'gemini',
            'model' => $model,
            'prompt_used' => $prompt,
            'schema' => $schema,
            'data' => $parsed,
            'raw_text' => $textResponse,
            'raw_json' => $responseBody,
        ];
    }

    /**
     * Build the Gemini prompt.
     *
     * Schema is now enforced via responseSchema in generationConfig,
     * so we don't need to embed JSON schema in the prompt.
     */
    protected function buildPrompt(?string $prompt, array $schema, ?array $textContext, ?array $largeFileContext): string
    {
        $base = trim($prompt ?? '');

        $lines = array_filter([
            $base,
        ]);

        // Add text context if available (helps with large PDFs)
        if ($textContext) {
            $lines[] = "\n--- Text excerpt for reference (if needed) ---";
            $lines[] = $textContext['excerpt'] ?? '';
        }

        // Add large file context if needed
        if ($largeFileContext) {
            $lines[] = "\n--- Large file context ---";
            $lines[] = 'This is a '.($largeFileContext['total_pages'] ?? '?').' page document. Focus on extracting complete and accurate information.';
        }

        return trim(implode("\n\n", $lines));
    }

    /**
     * Build parts payload for Gemini request.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildParts(string $promptText, string $mime, string $fileContent, ?array $textContext): array
    {
        $parts = [
            [
                'text' => $promptText,
            ],
            [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => base64_encode($fileContent),
                ],
            ],
        ];

        if ($textContext && ! empty($textContext['excerpt'])) {
            $parts[] = [
                'text' => 'Text excerpt for reference: '.$textContext['excerpt'],
            ];
        }

        return $parts;
    }

    /**
     * Extract text response from Gemini API payload.
     */
    protected function extractTextResponse(array $responseBody): string
    {
        $parts = $responseBody['candidates'][0]['content']['parts'] ?? [];
        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        return trim(implode("\n", $texts));
    }

    /**
     * Parse JSON response content from Gemini.
     *
     * @return array<string, mixed>
     */
    protected function parseJsonResponse(string $text): array
    {
        // Try parsing as-is first
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->cleanNulls($decoded);
        }

        // Try extracting JSON snippet
        $jsonSnippet = $this->extractJsonSnippet($text);
        $decoded = json_decode($jsonSnippet, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->cleanNulls($decoded);
        }

        // Try cleaning and repairing the text
        $cleanedText = $this->cleanJsonText($text);
        $decoded = json_decode($cleanedText, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('[GeminiProvider] JSON cleaned successfully');

            return $this->cleanNulls($decoded);
        }

        // Try repairing known issues
        $repairedText = $this->repairJson($cleanedText);
        $decoded = json_decode($repairedText, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('[GeminiProvider] JSON repaired successfully');

            return $this->cleanNulls($decoded);
        }

        // Try repairing the snippet
        $repairedSnippet = $this->repairJson($jsonSnippet);
        $decoded = json_decode($repairedSnippet, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('[GeminiProvider] JSON snippet repaired successfully');

            return $this->cleanNulls($decoded);
        }

        // All parsing attempts failed
        throw new GeminiApiException(
            'Gemini response is not valid JSON.',
            GeminiApiException::CODE_RESPONSE_INVALID,
            false,
            [
                'error' => json_last_error_msg(),
                'response_length' => strlen($text),
                'response_preview' => substr($text, 0, 500),
            ]
        );
    }

    /**
     * Clean JSON text from common LLM formatting issues.
     */
    protected function cleanJsonText(string $text): string
    {
        // Remove trailing commas before closing brackets/braces
        $text = preg_replace('/,(\s*[\]}])/m', '$1', $text);

        // Fix newlines after colons (Gemini 2.0 Flash bug: "key":\n      value)
        // Replace with single space
        $text = preg_replace('/:[\s\n]+/', ': ', $text);

        // Normalize whitespace in arrays
        $text = preg_replace('/\[\s+/', '[', $text);
        $text = preg_replace('/\s+\]/', ']', $text);

        // Fix arrays with excessive nulls - remove sequences of null values
        $text = preg_replace('/\[\s*null\s*(?:,\s*null\s*)*\]/m', '[]', $text);

        return $text;
    }

    /**
     * Recursively remove null-only arrays and clean null values.
     */
    protected function cleanNulls(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if array contains only nulls
                $nonNulls = array_filter($value, fn ($v) => $v !== null);
                if (empty($nonNulls) && ! empty($value)) {
                    // Array of only nulls - replace with empty array
                    $data[$key] = [];
                } else {
                    // Recursively clean nested arrays
                    $data[$key] = $this->cleanNulls($value);
                }
            }
        }

        return $data;
    }

    /**
     * Repair known JSON malformations from Gemini.
     */
    protected function repairJson(string $text): string
    {
        // Fix missing closing/opening braces between entities in a list
        // Replaces `}, "type": "ENTITY"` with `}}, {"type": "ENTITY"`
        // This handles cases where the model outputs:
        // { "type": "A", "data": {...}, "type": "B", "data": {...} }
        // instead of:
        // { "type": "A", "data": {...} }, { "type": "B", "data": {...} }

        $knownTypes = [
            'receipt', 'voucher', 'warranty', 'return_policy',
            'invoice', 'invoice_line_items', 'contract',
            'bank_statement', 'bank_transactions', 'document',
        ];

        $typesPattern = implode('|', $knownTypes);

        // Regex looks for:
        // 1. `}` (closing of data object)
        // 2. optional whitespace and comma
        // 3. optional whitespace
        // 4. "type" key
        // 5. colon and value which is one of the known types
        $pattern = '/\}\s*,\s*"type"\s*:\s*"('.$typesPattern.')"/';

        return preg_replace($pattern, '}}, {"type": "$1"', $text);
    }

    /**
     * Extract JSON snippet from a response string.
     */
    protected function extractJsonSnippet(string $text): string
    {
        if (preg_match('/```json\\s*(.*?)\\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    /**
     * Validate file size and mime support for Gemini.
     */
    protected function ensureSupported(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new GeminiApiException(
                "File not found: {$filePath}",
                GeminiApiException::CODE_FILE_NOT_FOUND,
                false,
                ['file_path' => $filePath]
            );
        }

        $fileSize = filesize($filePath) ?: 0;
        $maxSizeMb = (int) config('ai.providers.gemini.max_file_size_mb', 50);
        if ($fileSize > ($maxSizeMb * 1024 * 1024)) {
            throw new GeminiApiException(
                $this->formatOversizeError($maxSizeMb),
                GeminiApiException::CODE_FILE_TOO_LARGE,
                false,
                [
                    'file_size_bytes' => $fileSize,
                    'max_size_mb' => $maxSizeMb,
                ]
            );
        }

        $mime = mime_content_type($filePath) ?: '';
        $supported = config('ai.providers.gemini.supported_mime_types', []);
        if (! empty($supported) && ! in_array($mime, $supported)) {
            throw new GeminiApiException(
                "Unsupported mime type for Gemini: {$mime}",
                GeminiApiException::CODE_UNSUPPORTED_MIME,
                false,
                [
                    'mime' => $mime,
                    'supported_mime_types' => $supported,
                ]
            );
        }
    }

    /**
     * Build a context payload for large files to guide downstream handling.
     */
    protected function buildLargeFileContext(string $filePath, string $mime, int $fileSize, ?array $textContext): ?array
    {
        $maxSizeMb = (int) config('ai.providers.gemini.max_file_size_mb', 50);
        $defaultThreshold = max(1, (int) floor($maxSizeMb * 0.8));
        $thresholdMb = (int) config('ai.providers.gemini.large_file_threshold_mb', $defaultThreshold);
        if ($thresholdMb <= 0) {
            $thresholdMb = $defaultThreshold;
        }
        $thresholdMb = min($thresholdMb, $maxSizeMb);
        $thresholdBytes = $thresholdMb * 1024 * 1024;
        $isLargeBySize = $fileSize >= $thresholdBytes;

        $context = [
            'size_bytes' => $fileSize,
            'size_mb' => round($fileSize / (1024 * 1024), 2),
            'threshold_mb' => $thresholdMb,
            'max_size_mb' => $maxSizeMb,
        ];

        $strategy = null;

        if ($textContext && ($textContext['truncated'] ?? false)) {
            $strategy = 'text_truncate';
            $context['text_bytes'] = $textContext['bytes'] ?? null;
            $context['text_truncated'] = true;
        }

        if ($mime === 'application/pdf') {
            $pageCount = $this->getPdfPageCount($filePath);
            $context['page_count'] = $pageCount;

            $pageLimit = (int) config('ai.providers.gemini.large_pdf_page_limit', 25);
            if ($pageLimit <= 0) {
                $pageLimit = 25;
            }
            if ($pageCount > $pageLimit) {
                $sampleSize = (int) config('ai.providers.gemini.large_pdf_sample_size', 4);
                if ($sampleSize <= 0) {
                    $sampleSize = 4;
                }
                $context['page_limit'] = $pageLimit;
                $context['sample_pages'] = $this->buildPageSample($pageCount, $sampleSize);
                $context['sample_size'] = $sampleSize;
                $strategy = $strategy ?? 'sample_pages';
            }
        }

        if (! $isLargeBySize && $strategy === null) {
            return null;
        }

        $context['strategy'] = $strategy ?? 'size_only';

        return $context;
    }

    /**
     * Get the page count of a PDF file.
     * Returns 1 if unable to determine (safe fallback).
     */
    protected function getPdfPageCount(string $filePath): int
    {
        try {
            if (! extension_loaded('imagick')) {
                Log::debug('[GeminiProvider] Imagick not available for PDF page counting, assuming single page');

                return 1;
            }

            $gsPath = exec('which gs 2>/dev/null');
            if (empty($gsPath)) {
                Log::debug('[GeminiProvider] Ghostscript not available for PDF page counting, assuming single page');

                return 1;
            }

            $pdf = new Pdf($filePath);
            $pageCount = $pdf->pageCount();

            Log::debug('[GeminiProvider] PDF page count determined', [
                'file_path' => basename($filePath),
                'page_count' => $pageCount,
            ]);

            return $pageCount;

        } catch (Exception $e) {
            Log::warning('[GeminiProvider] Failed to determine PDF page count, assuming single page', [
                'file_path' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    /**
     * Build a sample of pages for large PDFs (first + last pages).
     *
     * @return array<int>
     */
    protected function buildPageSample(int $pageCount, int $sampleSize): array
    {
        $sampleSize = max(1, $sampleSize);
        $startPages = range(1, min($sampleSize, $pageCount));
        $endPages = range(max(1, $pageCount - $sampleSize + 1), $pageCount);
        $pages = array_values(array_unique(array_merge($startPages, $endPages)));
        sort($pages);

        return $pages;
    }

    /**
     * Format a user-friendly oversize error message.
     */
    protected function formatOversizeError(int $maxSizeMb): string
    {
        return "Gemini processing supports files up to {$maxSizeMb}MB. Please upload a smaller file or switch providers.";
    }

    /**
     * Build a text input context for plain text files.
     */
    protected function buildTextContext(string $filePath, string $mime, string $extension): ?array
    {
        if (! $this->isTextFile($mime, $extension)) {
            return null;
        }

        $maxBytes = (int) config('ai.providers.gemini.text_max_bytes', 200000);
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $content = stream_get_contents($handle, $maxBytes + 1);
        fclose($handle);

        if ($content === false) {
            return null;
        }

        $truncated = strlen($content) > $maxBytes;
        if ($truncated) {
            $content = substr($content, 0, $maxBytes);
        }

        return [
            'excerpt' => $content,
            'bytes' => strlen($content),
            'truncated' => $truncated,
        ];
    }

    /**
     * Determine whether a file should be treated as text input.
     */
    protected function isTextFile(string $mime, string $extension): bool
    {
        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        return in_array($extension, ['txt', 'md', 'csv', 'log'], true);
    }

    /**
     * Normalize entities from Gemini response to expected format.
     *
     * Gemini 2.0 Flash sometimes ignores the responseSchema and returns flat structures.
     * This method transforms them into the expected {type, confidence_score, data} format.
     */
    protected function normalizeEntities(array $entities, array $schema): array
    {
        $normalized = [];
        $primaryType = $schema['primary_entity'] ?? null;

        foreach ($entities as $index => $entity) {
            if (! is_array($entity)) {
                Log::warning('[GeminiProvider] Skipping non-array entity', ['index' => $index]);

                continue;
            }

            // Check if already in correct format
            if (isset($entity['type']) && isset($entity['data'])) {
                $normalized[] = $entity;

                continue;
            }

            // Entity is in wrong format - need to normalize
            $type = $this->detectEntityType($entity, $primaryType, $index);
            $confidenceScore = $entity['confidence_score'] ?? 0.85;

            // Remove meta fields that shouldn't be in data
            $metaFields = ['type', 'confidence_score', 'receipt', 'merchant', 'document'];
            $data = array_diff_key($entity, array_flip($metaFields));

            // Restructure based on type
            $data = $this->restructureEntityData($data, $type);

            $normalized[] = [
                'type' => $type,
                'confidence_score' => $confidenceScore,
                'data' => $data,
            ];

            Log::info('[GeminiProvider] Normalized entity', [
                'original_keys' => array_keys($entity),
                'normalized_type' => $type,
                'data_keys' => array_keys($data),
            ]);
        }

        return $normalized;
    }

    /**
     * Detect the entity type from a flat entity structure.
     */
    protected function detectEntityType(array $entity, ?string $defaultType, int $index): string
    {
        // Check if type is explicitly set but in wrong place
        if (isset($entity['type'])) {
            return strtolower($entity['type']);
        }

        // Receipt indicators
        if (isset($entity['merchant']) || isset($entity['date']) || isset($entity['total']) || isset($entity['receipt_number']) || isset($entity['purchase_items'])) {
            return 'receipt';
        }

        // Voucher indicators
        if (isset($entity['code']) || isset($entity['voucher_type']) || isset($entity['expiry_date'])) {
            return 'voucher';
        }

        // Warranty indicators
        if (isset($entity['product_name']) || isset($entity['warranty_end_date']) || isset($entity['serial_number'])) {
            return 'warranty';
        }

        // Invoice indicators
        if (isset($entity['invoice_number']) || isset($entity['from_name']) || isset($entity['to_name'])) {
            return 'invoice';
        }

        // Contract indicators
        if (isset($entity['contract_number']) || isset($entity['contract_title']) || isset($entity['parties'])) {
            return 'contract';
        }

        // Bank statement indicators
        if (isset($entity['account_number']) || isset($entity['iban']) || isset($entity['bank_name'])) {
            return 'bank_statement';
        }

        // Use primary type for first entity if no indicators found
        if ($index === 0 && $defaultType) {
            return $defaultType;
        }

        return 'document';
    }

    /**
     * Restructure flat entity data into nested structure based on type.
     */
    protected function restructureEntityData(array $data, string $type): array
    {
        if ($type === 'receipt') {
            return $this->restructureReceiptData($data);
        }

        // For other types, return as-is for now
        // Can add more specific restructuring as needed
        return $data;
    }

    /**
     * Restructure flat receipt data into expected nested structure.
     */
    protected function restructureReceiptData(array $flat): array
    {
        $structured = [];

        // Map merchant fields
        $merchantFields = ['name', 'address', 'vat_number', 'phone', 'website', 'email', 'category', 'contact_details'];
        $merchant = [];
        foreach ($merchantFields as $field) {
            if (isset($flat[$field])) {
                $merchant[$field] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($merchant)) {
            $structured['merchant'] = $merchant;
        }

        // Map totals
        $totalsFields = ['subtotal', 'total', 'total_amount', 'tax', 'tax_amount', 'discount', 'total_discount', 'tip_amount'];
        $totals = [];
        foreach ($totalsFields as $field) {
            if (isset($flat[$field])) {
                // Normalize field names
                $normalizedField = match ($field) {
                    'total' => 'total_amount',
                    'tax' => 'tax_amount',
                    'discount' => 'total_discount',
                    default => $field
                };
                $totals[$normalizedField] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($totals)) {
            $structured['totals'] = $totals;
        }

        // Map receipt_info
        $receiptInfoFields = ['date', 'time', 'receipt_number', 'transaction_id', 'cashier', 'terminal_id'];
        $receiptInfo = [];
        foreach ($receiptInfoFields as $field) {
            if (isset($flat[$field])) {
                $receiptInfo[$field] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($receiptInfo)) {
            $structured['receipt_info'] = $receiptInfo;
        }

        // Map payment
        $paymentFields = ['payment_method', 'card_type', 'card_last_four', 'currency', 'change_given', 'amount_paid'];
        $payment = [];
        foreach ($paymentFields as $field) {
            if (isset($flat[$field])) {
                // Normalize field names
                $normalizedField = str_replace('payment_', '', $field);
                $payment[$normalizedField] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($payment)) {
            $structured['payment'] = $payment;
        }

        // Map items - handle both 'items' and 'purchase_items'
        if (isset($flat['items'])) {
            $structured['items'] = $flat['items'];
            unset($flat['items']);
        } elseif (isset($flat['purchase_items'])) {
            // Convert simple item names to structured items
            $items = [];
            foreach ($flat['purchase_items'] as $itemName) {
                $items[] = [
                    'name' => $itemName,
                    'total_price' => 0, // Will be inferred from totals if needed
                ];
            }
            $structured['items'] = $items;
            unset($flat['purchase_items']);
        }

        // Map vendors
        if (isset($flat['vendor'])) {
            $structured['vendors'] = is_array($flat['vendor']) ? $flat['vendor'] : [$flat['vendor']];
            unset($flat['vendor']);
        } elseif (isset($flat['vendors'])) {
            $structured['vendors'] = $flat['vendors'];
            unset($flat['vendors']);
        }

        // Map summary
        if (isset($flat['summary'])) {
            $structured['summary'] = $flat['summary'];
            unset($flat['summary']);
        }

        // Map any remaining fields as-is (loyalty_program, metadata, etc.)
        foreach ($flat as $key => $value) {
            if (! isset($structured[$key])) {
                $structured[$key] = $value;
            }
        }

        return $structured;
    }
}
