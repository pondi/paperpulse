<?php

namespace App\Jobs\Documents;

use App\Jobs\BaseJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\Tag;
use App\Services\DocumentAnalysisService;
use App\Services\Tags\TagAttachmentService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs AI-powered analysis on previously created Document records.
 *
 * Updates title/summary/metadata, applies suggested category and tags,
 * and records entities extracted from content.
 */
class AnalyzeDocument extends BaseJob
{
    public $timeout = 3600;

    public $tries = 3;

    public $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Analyze Document';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            $metadata = $this->getMetadata();
            if (! $metadata) {
                throw new Exception('No metadata found for job');
            }

            $fileId = $metadata['fileId'];
            $this->updateProgress(10);

            Log::info('Analyzing document', [
                'job_id' => $this->jobID,
                'file_id' => $fileId,
            ]);

            // Find the document associated with this file
            $document = Document::where('file_id', $fileId)->first();

            if (! $document) {
                throw new Exception("Document not found for file ID: {$fileId}");
            }

            $this->updateProgress(25);

            // Get document analysis service
            $analysisService = app(DocumentAnalysisService::class);

            // Analyze the document using AI
            $analysis = $analysisService->analyze($document);
            $this->updateProgress(50);

            if (! $analysis) {
                throw new Exception('Document analysis failed - no response from AI service');
            }

            DB::transaction(function () use ($document, $analysis) {
                // Update document with AI-generated metadata
                $document->update([
                    'title' => $analysis['title'] ?? $document->title,
                    'summary' => $analysis['summary'] ?? null,
                    'language' => $analysis['language'] ?? 'en',
                    'document_type' => $analysis['document_type'] ?? 'general',
                    'metadata' => array_merge($document->metadata ?? [], [
                        'ai_analysis' => $analysis,
                        'analyzed_at' => now()->toIso8601String(),
                    ]),
                ]);

                // Handle category suggestion
                if (! empty($analysis['suggested_category'])) {
                    $category = $this->findOrCreateCategory($analysis['suggested_category'], $document->user_id);
                    if ($category) {
                        $document->update(['category_id' => $category->id]);
                    }
                }

                // Handle tags
                if (! empty($analysis['tags']) && is_array($analysis['tags'])) {
                    $tagIds = [];
                    foreach ($analysis['tags'] as $tagName) {
                        $tag = $this->findOrCreateTag($tagName, $document->user_id);
                        if ($tag) {
                            $tagIds[] = $tag->id;
                        }
                    }

                    // Sync tags with the document using proper file_type
                    TagAttachmentService::syncTags($document, $tagIds, 'document');
                }

                // Handle entities extraction
                if (! empty($analysis['entities'])) {
                    $metadata = $document->metadata ?? [];
                    $metadata['entities'] = $analysis['entities'];
                    $document->update(['metadata' => $metadata]);
                }
            });

            $this->updateProgress(90);

            // Log successful analysis
            Log::info('Document analysis completed', [
                'job_id' => $this->jobID,
                'document_id' => $document->id,
                'file_id' => $fileId,
                'title' => $document->title,
                'tags_count' => count($analysis['tags'] ?? []),
            ]);

            $this->updateProgress(100);

        } catch (Exception $e) {
            Log::error('Document analysis job failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_id' => $metadata['fileId'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Find or create a category for the user
     */
    private function findOrCreateCategory(string $categoryName, int $userId): ?Category
    {
        try {
            // First try to find an existing category
            $category = Category::where('user_id', $userId)
                ->where('name', 'like', $categoryName)
                ->first();

            if (! $category) {
                // Create a new category with a default color
                $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
                $color = $colors[array_rand($colors)];

                // Generate a unique slug for this category
                $slug = Category::generateUniqueSlug($categoryName, $userId);

                try {
                    $category = Category::create([
                        'user_id' => $userId,
                        'name' => $categoryName,
                        'slug' => $slug,
                        'color' => $color,
                    ]);

                    Log::info('Created new category from AI suggestion', [
                        'category_id' => $category->id,
                        'name' => $categoryName,
                        'slug' => $slug,
                        'user_id' => $userId,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Race condition: another process created the category, fetch it
                    $category = Category::where('user_id', $userId)
                        ->where('name', 'like', $categoryName)
                        ->first();

                    Log::debug('Category was created by concurrent process', [
                        'category_name' => $categoryName,
                        'user_id' => $userId,
                    ]);
                }
            }

            return $category;
        } catch (Exception $e) {
            Log::error('Failed to find or create category', [
                'category_name' => $categoryName,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find or create a tag for the user
     */
    private function findOrCreateTag(string $tagName, int $userId): ?Tag
    {
        try {
            // Normalize tag name
            $tagName = trim(strtolower($tagName));

            if (empty($tagName)) {
                return null;
            }

            // First try to find an existing tag
            $tag = Tag::where('user_id', $userId)
                ->where('name', $tagName)
                ->first();

            if (! $tag) {
                // Create a new tag
                $tag = Tag::create([
                    'user_id' => $userId,
                    'name' => $tagName,
                ]);

                Log::info('Created new tag from AI suggestion', [
                    'tag_id' => $tag->id,
                    'name' => $tagName,
                    'user_id' => $userId,
                ]);
            }

            return $tag;
        } catch (Exception $e) {
            Log::error('Failed to find or create tag', [
                'tag_name' => $tagName,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the tags that should be assigned to this job.
     */
    public function tags(): array
    {
        $metadata = $this->getMetadata();
        $fileId = $metadata['fileId'] ?? 'unknown';

        return ['document-analysis', 'file:'.$fileId];
    }
}
