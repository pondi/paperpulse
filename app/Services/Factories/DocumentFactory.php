<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\Document;
use App\Models\File;

class DocumentFactory
{
    public function create(array $data, File $file, string $type = 'document'): ?Document
    {
        if (empty($data)) {
            return null;
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadataTitle = is_string($metadata['title'] ?? null) ? $metadata['title'] : null;
        $metadataType = is_string($metadata['type'] ?? null) ? $metadata['type'] : null;
        $metadataLanguage = is_string($metadata['language'] ?? null) ? $metadata['language'] : null;
        $creationInfo = is_array($data['creation_info'] ?? null) ? $data['creation_info'] : [];

        $content = $data['content'] ?? null;
        $contentSummary = null;
        $keyPoints = [];
        if (is_array($content)) {
            $contentSummary = is_string($content['summary'] ?? null) ? $content['summary'] : null;
            $keyPoints = is_array($content['key_points'] ?? null)
                ? array_values(array_filter($content['key_points'], 'is_string'))
                : [];
        }

        $summary = $data['summary'] ?? $contentSummary;
        if (! is_string($summary)) {
            $summary = null;
        }

        $contentChunks = [];
        if (is_string($content)) {
            $contentChunks[] = $content;
        } elseif (is_string($summary)) {
            $contentChunks[] = $summary;
        }
        if (! empty($keyPoints)) {
            $contentChunks[] = implode("\n", $keyPoints);
        }
        $contentText = $contentChunks === [] ? null : trim(implode("\n\n", $contentChunks));

        $metadata = array_merge($metadata, array_filter([
            'creation_info' => $creationInfo !== [] ? $creationInfo : null,
            'content' => is_array($content) && $content !== [] ? $content : null,
            'tags' => is_array($data['tags'] ?? null) ? array_values(array_filter($data['tags'], 'is_string')) : null,
            'entities_mentioned' => is_array($data['entities_mentioned'] ?? null) ? $data['entities_mentioned'] : null,
        ], static fn ($value) => $value !== null));

        if ($metadata === []) {
            $metadata = null;
        }

        $categoryId = $data['category_id'] ?? null;
        if (is_string($categoryId) && ctype_digit($categoryId)) {
            $categoryId = (int) $categoryId;
        } elseif (! is_int($categoryId)) {
            $categoryId = null;
        }

        $description = $data['description'] ?? null;
        if (! is_string($description)) {
            $description = null;
        }

        return Document::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'category_id' => $categoryId,
            'title' => $data['title'] ?? $metadataTitle ?? 'Detected Document',
            'description' => $description,
            'summary' => $summary,
            'content' => $contentText,
            'document_type' => $data['document_type'] ?? $metadataType ?? $type,
            'document_subtype' => $data['document_subtype'] ?? null,
            'document_date' => $data['document_date'] ?? ($creationInfo['creation_date'] ?? null),
            'metadata' => $metadata,
            'extracted_text' => $data['extracted_text'] ?? null,
            'ai_entities' => $data['ai_entities'] ?? $data['entities_mentioned'] ?? null,
            'extracted_entities' => $data['extracted_entities'] ?? null,
            'language' => $data['language'] ?? $metadataLanguage,
            'page_count' => $data['page_count'] ?? 1,
        ]);
    }
}
