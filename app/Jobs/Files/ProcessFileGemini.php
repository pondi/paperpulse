<?php

namespace App\Jobs\Files;

use App\Exceptions\AIResponseException;
use App\Exceptions\GeminiApiException;
use App\Jobs\BaseJob;
use App\Models\BankStatement;
use App\Models\File;
use App\Models\FileProcessingAnalytic;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Notifications\DocumentProcessed;
use App\Notifications\ReceiptProcessed;
use App\Services\AI\Extractors\EntityExtractorFactory;
use App\Services\AI\FileManager\GeminiFileManager;
use App\Services\AI\Providers\GeminiFileAnalyzer;
use App\Services\AI\Shared\ProcessingStageCache;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\AI\TypeClassification\AutomaticTypeResolver;
use App\Services\AI\TypeClassification\ClassificationResult;
use App\Services\AI\TypeClassification\ClassificationSchema;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use App\Services\BankStatements\TransactionCategorizationService;
use App\Services\DuplicateDetectionService;
use App\Services\EntityFactory;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\ImagePreviewGenerator;
use App\Services\OrganizationEvidenceSchema;
use App\Services\OrganizationFolderContext;
use App\Services\Receipts\Analysis\UserPreferencesLoader;
use App\Services\Workers\WorkerFileManager;
use Exception;
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf;
use Throwable;

class ProcessFileGemini extends BaseJob
{
    public int $timeout = 600;

    public int $tries = 0;

    public int $maxExceptions = 5;

    public $backoff = [60, 120, 300, 600, 900];

    /**
     * Track processing start time for analytics.
     */
    protected float $processingStartTime;

    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Process File (Gemini)';
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new FailOnException(fn (Throwable $exception): bool => $exception instanceof AIResponseException && ! $exception->retryable),
        ];
    }

    protected function handleJob(): void
    {
        $this->processingStartTime = microtime(true);

        $metadata = $this->getMetadata();
        if (! $metadata || ! isset($metadata['fileId'])) {
            Log::error('[ProcessFileGemini] Missing metadata', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'metadata_exists' => $metadata !== null,
                'has_file_id' => isset($metadata['fileId']),
            ]);
            throw new Exception('Missing job metadata for Gemini processing');
        }

        $fileId = $metadata['fileId'];
        $file = File::find($fileId);

        if (! $file) {
            Log::error('[ProcessFileGemini] File not found in database', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_id' => $fileId,
                'metadata' => $metadata,
            ]);
            throw new Exception("File record not found: {$fileId}");
        }

        if ($file->status === 'needs_review' && ($file->meta['review']['reason'] ?? null) !== 'uncertain_classification') {
            return;
        }

        UserPreferencesLoader::load($file->user_id, $file);

        // Idempotency: skip if file already has entities (prevents duplicates on retry)
        $isReprocessing = $metadata['metadata']['reprocessing'] ?? false;
        if (! $isReprocessing && $file->status === 'completed' && $file->extractableEntities()->exists()) {
            Log::info('[ProcessFileGemini] File already processed, skipping', [
                'file_id' => $file->id,
                'job_id' => $this->jobID,
            ]);

            return;
        }

        Log::info('[ProcessFileGemini] Starting Gemini processing', [
            'job_id' => $this->jobID,
            'task_id' => $this->uuid,
            'file_id' => $file->id,
            'file_guid' => $file->guid,
            'file_type' => $file->file_type,
            'file_status' => $file->status,
        ]);

        $this->updateProgress(10);

        $s3Path = $metadata['s3ArchivePath'] ?? $metadata['s3OriginalPath'] ?? null;
        if (! $s3Path) {
            throw new Exception('No S3 path available for Gemini processing');
        }

        $workerFileManager = app(WorkerFileManager::class);
        $fileManager = app(GeminiFileManager::class);
        $classifier = app(GeminiTypeClassifier::class);
        $entityFactory = app(EntityFactory::class);
        $previewManager = app(FilePreviewManager::class);

        $result = $workerFileManager->processWithCleanup(
            $s3Path,
            $metadata['fileGuid'],
            $metadata['fileExtension'],
            function (string $localPath) use ($fileManager, $classifier, $file, $previewManager) {
                $this->updateProgress(30);

                $contentHash = hash_file('sha256', $localPath);
                $mime = mime_content_type($localPath) ?: '';
                $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                $analyzer = app(GeminiFileAnalyzer::class);
                $analyzer->ensureSupported($localPath);
                $textContext = $analyzer->buildTextContext($localPath, $mime, $extension);
                $large = $analyzer->buildLargeFileContext($localPath, $mime, filesize($localPath), $textContext);
                $pages = $large['page_count'] ?? ($mime === 'application/pdf' ? (new Pdf($localPath))->pageCount() : 1);
                $pageLimit = max(1, (int) config('ai.providers.gemini.large_pdf_page_limit', 25));
                $exceeds = $pages > $pageLimit || ($textContext['truncated'] ?? false);
                $file->meta = array_merge($file->meta ?? [], ['processing_coverage' => [
                    'total_pages' => $pages, 'processed_pages' => 0, 'purpose' => 'extraction',
                    'classification_sample_pages' => $large['sample_pages'] ?? [], 'complete' => false,
                ]]);
                if ($exceeds) {
                    $file->status = 'needs_review';
                    $file->meta = array_merge($file->meta, ['review' => ['reason' => 'processing_limit',
                        'page_limit' => $pages > $pageLimit ? $pageLimit : null,
                        'text_limit_bytes' => ($textContext['truncated'] ?? false) ? (int) config('ai.providers.gemini.text_max_bytes') : null]]);
                    $file->save();

                    return ['needs_review' => true];
                }
                $file->save();

                // PASS 0: Upload to Gemini Files API
                Log::info('[ProcessFileGemini] Uploading file to Gemini Files API', [
                    'file_id' => $file->id,
                    'file_size' => filesize($localPath),
                ]);

                $uploadResult = $fileManager->uploadFile($localPath, $file->guid);
                $fileUri = $uploadResult['fileUri'];
                $geminiFileName = $uploadResult['name'];

                try {
                    $fileManager->waitUntilActive($uploadResult);
                    $this->updateProgress(40);

                    // PASS 1: Classify document type
                    Log::info('[ProcessFileGemini] Pass 1: Classifying document', [
                        'file_id' => $file->id,
                        'file_uri' => $fileUri,
                    ]);

                    $correctedType = $file->meta['review']['corrected_type'] ?? null;
                    $hints = ['filename' => $file->fileName, 'extension' => $extension, 'mime_type' => $uploadResult['mimeType']];
                    $version = ['model' => config('ai.providers.gemini.model'), 'options' => ['mime' => $uploadResult['mimeType'], 'output_tokens' => 512]];
                    $classification = $correctedType
                        ? new ClassificationResult($correctedType, 1.0, 'Owner corrected document type')
                        : ClassificationResult::fromGeminiResponse(ProcessingStageCache::remember($file->user_id, $contentHash, 'classification', [
                            ...$version, 'prompt' => ClassificationSchema::getPrompt($hints), 'schema' => ClassificationSchema::get(),
                        ], fn () => ProcessingUsageBudget::run($file->user_id, $this->jobID, 'classification', fn () => $classifier->classify($fileUri, $hints)->toArray()), $file->guid));

                    Log::info('[ProcessFileGemini] Classification result', [
                        'file_id' => $file->id,
                        'type' => $classification->type,
                        'confidence' => $classification->confidence,
                        'reasoning' => $classification->reasoning,
                    ]);

                    $classification = app(AutomaticTypeResolver::class)->resolve($file, $classification, $fileUri, $hints, $textContext['excerpt'] ?? null, $this->jobID, $contentHash);
                    $meta = $file->meta ?? [];
                    if (($meta['review']['reason'] ?? null) === 'uncertain_classification') {
                        unset($meta['review']);
                        $file->status = 'processing';
                    }
                    $file->meta = $meta;
                    $file->save();

                    // Update file_type if Gemini reclassified (e.g. receipt → document)
                    $classifiedType = $classification->type === 'receipt' ? 'receipt' : 'document';
                    if ($file->file_type !== $classifiedType) {
                        Log::info('[ProcessFileGemini] Reclassifying file type', [
                            'file_id' => $file->id,
                            'original_type' => $file->file_type,
                            'new_type' => $classifiedType,
                            'classified_as' => $classification->type,
                        ]);
                        $file->file_type = $classifiedType;
                        $file->save();
                    }

                    $this->updateProgress(50);

                    $version['options']['output_tokens'] = config('ai.providers.gemini.max_output_tokens', 8192);

                    // PASS 2: Extract structured data
                    Log::info('[ProcessFileGemini] Pass 2: Extracting data', [
                        'file_id' => $file->id,
                        'type' => $classification->type,
                    ]);

                    // Check if extractor exists for this type
                    if (! EntityExtractorFactory::hasExtractor($classification->type)) {
                        throw new Exception(
                            "No extractor implemented for type '{$classification->type}'. Currently only 'receipt' is supported."
                        );
                    }

                    $extractor = EntityExtractorFactory::create($classification->type);
                    $folderContext = app(OrganizationFolderContext::class);
                    $folders = $folderContext->candidates($file->user_id, $file->fileName.' '.$classification->reasoning.' '.($textContext['excerpt'] ?? ''));
                    $schema = $extractor->getSchema();
                    $schema['responseSchema']['properties']['organization'] = OrganizationEvidenceSchema::get();
                    if ($folders !== []) {
                        $schema['responseSchema']['properties']['organization']['properties']['collection_id']['enum'] = array_column($folders, 'id');
                    } else {
                        unset($schema['responseSchema']['properties']['organization']['properties']['collection_id']);
                    }
                    $schema['responseSchema']['required'] = array_values(array_unique([...($schema['responseSchema']['required'] ?? []), 'organization']));
                    $prompt = $extractor->getPrompt().$folderContext->prompt($folders);
                    $extracted = ProcessingStageCache::remember($file->user_id, $contentHash, 'extraction:'.$classification->type, [
                        ...$version, 'prompt' => $prompt, 'schema' => $schema, 'normalizer_version' => 2,
                    ], fn () => ProcessingUsageBudget::run($file->user_id, $this->jobID, 'extraction', fn () => $extractor->extract($fileUri, $file, [
                        'classification' => $classification, 'mime_type' => $uploadResult['mimeType'],
                        'extraction_schema' => $schema, 'extraction_prompt' => $prompt,
                    ])), $file->guid);

                    $this->updateProgress(70);

                    // Generate preview image for supported file types
                    try {
                        $generated = $previewManager->generatePreviewForFile($file, $localPath);
                        if (! $generated && $file->image_generation_error !== null) {
                            GenerateFilePreview::dispatch($file->id)->onQueue('files');
                        }
                        Log::info('[ProcessFileGemini] Preview generated', [
                            'file_id' => $file->id,
                            'has_preview' => $file->fresh()->has_image_preview,
                        ]);
                    } catch (Exception $e) {
                        $this->reportProcessingFailure($e, 'preview');
                        Log::warning('[ProcessFileGemini] Preview generation failed', [
                            'file_id' => $file->id,
                            'error' => $e->getMessage(),
                        ]);
                        // Don't fail the job if preview generation fails
                    }

                    $this->updateProgress(80);

                    // Return data in format expected by EntityFactory
                    return [
                        'typeInfo' => [
                            'type' => $classification->type,
                            'subtype' => null,
                        ],
                        'parsed' => [
                            'entities' => array_merge([$extracted], $extracted['supplemental_entities'] ?? []),
                            'provider_response' => [
                                'classification' => $classification->toArray(),
                            ],
                        ],
                    ];

                } finally {
                    // ALWAYS cleanup Files API upload
                    Log::info('[ProcessFileGemini] Cleaning up Gemini Files API upload', [
                        'file_id' => $file->id,
                        'gemini_file_name' => $geminiFileName,
                    ]);
                    try {
                        if (! $fileManager->deleteFile($geminiFileName)) {
                            $this->reportProcessingFailure('Gemini uploaded file cleanup returned false.', 'provider-file-cleanup');
                            DeleteGeminiFile::dispatch($geminiFileName)->onQueue('files');
                        }
                    } catch (Throwable $exception) {
                        $this->reportProcessingFailure($exception, 'provider-file-cleanup');
                        DeleteGeminiFile::dispatch($geminiFileName)->onQueue('files');
                    }
                }
            },
            'ProcessFileGemini'
        );

        $file->meta = array_merge($file->fresh()->meta ?? [], ['processing_usage' => ProcessingUsageBudget::usage($file->user_id, $this->jobID)]);
        $file->save();
        if ($result['needs_review'] ?? false) {
            return;
        }
        $meta = $file->fresh()->meta ?? [];
        $coverage = $meta['processing_coverage'] ?? [];
        $coverage['processed_pages'] = $coverage['total_pages'] ?? 1;
        $coverage['complete'] = true;
        $file->meta = array_merge($meta, ['processing_coverage' => $coverage]);
        $file->save();

        // Persist parsed data on the file metadata for downstream factories
        $classification = $result['parsed']['provider_response']['classification'] ?? null;
        $extractedEntity = $result['parsed']['entities'][0] ?? null;

        $file->meta = array_merge($file->meta ?? [], [
            'gemini' => [
                'type' => $result['typeInfo']['type'] ?? null,
                'subtype' => $result['typeInfo']['subtype'] ?? null,
                'provider_response' => $result['parsed']['provider_response'] ?? null,
                'entities' => $result['parsed']['entities'] ?? [],
                // Analytics metadata for production learning
                'classification' => [
                    'type' => $classification['document_type'] ?? null,
                    'confidence' => $classification['confidence'] ?? null,
                    'reasoning' => $classification['reasoning'] ?? null,
                    'detected_entities' => $classification['detected_entities'] ?? null,
                    'timestamp' => now()->toISOString(),
                ],
                'extraction' => [
                    'confidence_score' => $extractedEntity['confidence_score'] ?? null,
                    'validation_warnings' => $extractedEntity['validation_warnings'] ?? [],
                    'timestamp' => now()->toISOString(),
                ],
                'processing' => [
                    'completed_at' => now()->toISOString(),
                    'model' => config('ai.providers.gemini.model'),
                ],
            ],
        ]);
        $file->processing_type = 'gemini';
        $file->save();

        $metadata['fileType'] = $file->file_type;
        $metadata['gemini'] = $result;
        $this->storeMetadata($metadata);

        // Create entities from parsed data
        $createdEntities = $entityFactory->createEntitiesFromParsedData(
            $result['parsed'],
            $file,
            $result['typeInfo']['type'] ?? 'document'
        );

        $this->flagDuplicateEntities($createdEntities);

        // Trigger indexing for searchable models
        foreach ($createdEntities as $entityInfo) {
            $model = $entityInfo['model'];
            if (method_exists($model, 'searchable')) {
                $model->searchable();
            }
        }

        // Categorize bank transactions if any bank statements were created
        foreach ($createdEntities as $entityInfo) {
            if ($entityInfo['type'] === 'bank_statement' && $entityInfo['model'] instanceof BankStatement) {
                try {
                    $categorizationService = app(TransactionCategorizationService::class);
                    $entityInfo['model']->load('transactions');
                    $categorizationService->categorize($entityInfo['model']->transactions);
                } catch (Exception $e) {
                    $this->reportProcessingFailure($e, 'transaction-categorization');
                    Log::warning('[ProcessFileGemini] Transaction categorization failed', [
                        'statement_id' => $entityInfo['model']->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $file->refresh();
        if ($file->status !== 'needs_review') {
            $file->status = 'completed';
        }
        $file->save();

        // Create analytics record for production learning
        $this->createAnalyticsRecord($file, $file->status, $classification ?? null, $extractedEntity ?? null);

        $this->updateProgress(100);

        if ($file->status === 'completed') {
            $notifiedReceipt = false;
            foreach ($createdEntities as $entityInfo) {
                if ($entityInfo['model'] instanceof Receipt) {
                    $file->user->notify((new ReceiptProcessed($entityInfo['model']))->onConnection('database')->beforeCommit());
                    $notifiedReceipt = true;
                }
            }
            if (! $notifiedReceipt) {
                $file->user->notify(DocumentProcessed::forFile($file)->onConnection('database')->beforeCommit());
            }
        }
    }

    /**
     * Generate thumbnail preview.
     */
    protected function generateThumbnail(string $filePath, string $fileGuid): ?string
    {
        return base64_encode(ImagePreviewGenerator::generatePreview($filePath, pathinfo($filePath, PATHINFO_EXTENSION)));
    }

    protected function flagDuplicateEntities(array $createdEntities): void
    {
        if (empty($createdEntities)) {
            return;
        }

        $duplicateDetection = app(DuplicateDetectionService::class);

        foreach ($createdEntities as $entityInfo) {
            $model = $entityInfo['model'] ?? null;

            if ($model instanceof Receipt) {
                $duplicateDetection->flagReceiptDuplicates($model);
            } elseif ($model instanceof Invoice) {
                $duplicateDetection->flagInvoiceDuplicates($model);
            }
        }
    }

    public function tags(): array
    {
        return [
            'provider:gemini',
            'pipeline:file-processing',
            'gemini',
            'job:'.$this->jobID,
            'task:'.$this->uuid,
        ];
    }

    protected function isTextFile(string $mime, string $extension): bool
    {
        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        return in_array($extension, ['txt', 'md', 'csv', 'log'], true);
    }

    public function failed(Throwable $exception): void
    {
        // Let BaseJob handle JobHistory updates and file status
        parent::failed($exception);

        $metadata = $this->getMetadata();
        $file = null;

        if ($metadata && isset($metadata['fileId'])) {
            $file = File::find($metadata['fileId']);
        }

        $this->recordGeminiFailure($file, $exception);
    }

    /**
     * Create analytics record for production learning.
     */
    protected function createAnalyticsRecord(
        File $file,
        string $status,
        ?array $classification = null,
        ?array $extraction = null,
        ?string $failureCategory = null,
        ?string $errorMessage = null,
        ?bool $isRetryable = null
    ): void {
        try {
            $processingDuration = isset($this->processingStartTime)
                ? (int) ((microtime(true) - $this->processingStartTime) * 1000)
                : null;

            FileProcessingAnalytic::create([
                'file_id' => $file->id,
                'user_id' => $file->user_id,
                'processing_type' => 'gemini',
                'processing_status' => $status,
                'processing_duration_ms' => $processingDuration,
                'model_used' => config('ai.providers.gemini.model'),
                // Classification data
                'document_type' => $classification['document_type'] ?? null,
                'classification_confidence' => $classification['confidence'] ?? null,
                'classification_reasoning' => $classification['reasoning'] ?? null,
                'detected_entities' => $classification['detected_entities'] ?? null,
                // Extraction data
                'extraction_confidence' => $extraction['confidence_score'] ?? null,
                'validation_warnings' => $extraction['validation_warnings'] ?? null,
                // Failure data
                'failure_category' => $failureCategory,
                'error_message' => $errorMessage,
                'is_retryable' => $isRetryable,
            ]);

            Log::debug('[ProcessFileGemini] Analytics record created', [
                'file_id' => $file->id,
                'status' => $status,
                'document_type' => $classification['type'] ?? null,
                'duration_ms' => $processingDuration,
            ]);
        } catch (Exception $e) {
            // Don't fail the job if analytics tracking fails
            $this->reportProcessingFailure($e, 'analytics');
            Log::warning('[ProcessFileGemini] Failed to create analytics record', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Categorize failure type for analytics and learning.
     */
    protected function categorizeFailure(Throwable $exception): string
    {
        if ($exception instanceof AIResponseException && $exception->errorCode === AIResponseException::CODE_USAGE_BUDGET_EXCEEDED) {
            return AIResponseException::CODE_USAGE_BUDGET_EXCEEDED;
        }

        if ($exception instanceof GeminiApiException) {
            $category = match ($exception->getErrorCode()) {
                GeminiApiException::CODE_FILE_NOT_FOUND => 'file_missing',
                GeminiApiException::CODE_FILE_TOO_LARGE => 'file_too_large',
                GeminiApiException::CODE_UNSUPPORTED_MIME => 'unsupported_format',
                GeminiApiException::CODE_RATE_LIMIT => 'api_rate_limited',
                GeminiApiException::CODE_TIMEOUT => 'api_timeout',
                default => null,
            };
            if ($category !== null) {
                return $category;
            }
        }

        $message = strtolower($exception->getMessage());

        // Classification failures
        if (str_contains($message, 'classification failed')) {
            if (str_contains($message, 'confidence')) {
                return 'classification_low_confidence';
            }
            if (str_contains($message, 'unknown')) {
                return 'classification_unknown_type';
            }

            return 'classification_failed';
        }

        // Extraction failures
        if (str_contains($message, 'validation failed') || str_contains($message, 'validator')) {
            return 'extraction_validation_failed';
        }

        if (str_contains($message, 'no extractor')) {
            return 'extraction_no_extractor';
        }

        // API failures
        if ($exception instanceof GeminiApiException) {
            if (str_contains($message, 'upload')) {
                return 'api_upload_failed';
            }
            if (str_contains($message, 'timeout')) {
                return 'api_timeout';
            }
            if (str_contains($message, 'quota') || str_contains($message, 'rate limit')) {
                return 'api_rate_limited';
            }

            return 'api_error';
        }

        // File/processing failures
        if (str_contains($message, 'file not found') || str_contains($message, 'missing')) {
            return 'file_missing';
        }

        return 'unknown_error';
    }

    protected function recordGeminiFailure(?File $file, Throwable $exception): void
    {
        $errorCode = null;
        $retryable = null;
        $context = [];

        if ($exception instanceof GeminiApiException) {
            $errorCode = $exception->getErrorCode();
            $retryable = $exception->isRetryable();
            $context = $exception->getContext();
        } elseif ($exception instanceof AIResponseException) {
            $errorCode = $exception->errorCode;
            $retryable = $exception->retryable;
            $context = $exception->context;
        }

        Log::error('[ProcessFileGemini] Job failed', [
            'job_id' => $this->jobID,
            'task_id' => $this->uuid,
            'file_id' => $file?->id,
            'error' => $exception->getMessage(),
            'error_code' => $errorCode,
            'retryable' => $retryable,
            'context' => $context,
            'exception' => get_class($exception),
        ]);

        if (! $file) {
            return;
        }

        // Categorize failure type for analytics
        $failureCategory = $this->categorizeFailure($exception);

        $file->meta = array_merge($file->meta ?? [], [
            'gemini_error' => [
                'message' => $exception->getMessage(),
                'code' => $errorCode,
                'retryable' => $retryable,
                'context' => $context,
                'category' => $failureCategory,
                'timestamp' => now()->toISOString(),
            ],
        ]);
        $file->status = 'failed';
        $file->save();

        // Create analytics record for failure analysis
        $this->createAnalyticsRecord($file, 'failed', null, null, $failureCategory, $exception->getMessage(), $retryable);
    }
}
