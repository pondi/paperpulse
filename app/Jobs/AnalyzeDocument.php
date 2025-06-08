<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Category;
use App\Models\Tag;
use App\Services\DocumentAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AnalyzeDocument extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(DocumentAnalysisService $analysisService): void
    {
        try {
            $startTime = microtime(true);
            
            // Find the document associated with this file
            $document = Document::where('file_id', $this->file->id)->first();
            
            if (!$document) {
                Log::error('Document not found for file', [
                    'file_id' => $this->file->id,
                ]);
                $this->updateStatus('failed', 'Document not found');
                return;
            }

            $this->updateStatus('processing', 'Analyzing document with AI');

            // Analyze the document using AI
            $analysis = $analysisService->analyze($document);

            if (!$analysis) {
                Log::error('Document analysis failed', [
                    'document_id' => $document->id,
                ]);
                $this->updateStatus('failed', 'AI analysis failed');
                return;
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
                if (!empty($analysis['suggested_category'])) {
                    $category = $this->findOrCreateCategory($analysis['suggested_category'], $document->user_id);
                    if ($category) {
                        $document->update(['category_id' => $category->id]);
                    }
                }

                // Handle tags
                if (!empty($analysis['tags']) && is_array($analysis['tags'])) {
                    $tagIds = [];
                    foreach ($analysis['tags'] as $tagName) {
                        $tag = $this->findOrCreateTag($tagName, $document->user_id);
                        if ($tag) {
                            $tagIds[] = $tag->id;
                        }
                    }
                    
                    // Sync tags with the document
                    $document->tags()->sync($tagIds);
                }

                // Handle entities extraction
                if (!empty($analysis['entities'])) {
                    $metadata = $document->metadata ?? [];
                    $metadata['entities'] = $analysis['entities'];
                    $document->update(['metadata' => $metadata]);
                }
            });

            // Log successful analysis
            $processingTime = round(microtime(true) - $startTime, 2);
            Log::info('Document analysis completed', [
                'document_id' => $document->id,
                'file_id' => $this->file->id,
                'processing_time' => $processingTime,
                'title' => $document->title,
                'tags_count' => count($analysis['tags'] ?? []),
            ]);

            $this->updateStatus('completed', 'Document analysis completed successfully');

        } catch (\Exception $e) {
            Log::error('Document analysis job failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateStatus('failed', 'Analysis failed: ' . $e->getMessage());
            
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

            if (!$category) {
                // Create a new category with a default color
                $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
                $color = $colors[array_rand($colors)];

                $category = Category::create([
                    'user_id' => $userId,
                    'name' => $categoryName,
                    'color' => $color,
                ]);

                Log::info('Created new category from AI suggestion', [
                    'category_id' => $category->id,
                    'name' => $categoryName,
                    'user_id' => $userId,
                ]);
            }

            return $category;
        } catch (\Exception $e) {
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

            if (!$tag) {
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
        } catch (\Exception $e) {
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
        return ['document-analysis', 'file:' . $this->file->id];
    }
}