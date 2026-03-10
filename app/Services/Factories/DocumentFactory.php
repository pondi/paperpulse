<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\Document;
use App\Models\File;

class DocumentFactory extends BaseEntityFactory
{
    protected function modelClass(): string
    {
        return Document::class;
    }

    protected function fields(): array
    {
        return [
            'category_id',
            'title',
            'description',
            'summary',
            'content',
            'document_type',
            'document_subtype',
            'document_date',
            'metadata',
            'extracted_text',
            'ai_entities',
            'extracted_entities',
            'language',
            'page_count',
        ];
    }

    protected function dateFields(): array
    {
        return ['document_date'];
    }

    protected function defaults(): array
    {
        return [
            'title' => 'Detected Document',
            'document_type' => 'document',
            'page_count' => 1,
        ];
    }

    protected function prepareData(array $data, File $file): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $creationInfo = is_array($data['creation_info'] ?? null) ? $data['creation_info'] : [];

        $data['title'] = $data['title']
            ?? (is_string($metadata['title'] ?? null) ? $metadata['title'] : null);

        $data['document_type'] = $data['document_type']
            ?? (is_string($metadata['type'] ?? null) ? $metadata['type'] : null)
            ?? ($data['_fallback_type'] ?? null);

        $data['language'] = $data['language']
            ?? (is_string($metadata['language'] ?? null) ? $metadata['language'] : null);

        $data['document_date'] = $data['document_date'] ?? ($creationInfo['creation_date'] ?? null);

        $data['description'] = is_string($data['description'] ?? null) ? $data['description'] : null;

        $data['ai_entities'] = $data['ai_entities'] ?? $data['entities_mentioned'] ?? null;

        $data = $this->resolveContent($data, $metadata, $creationInfo);
        $data = $this->resolveCategoryId($data);

        return $data;
    }

    /**
     * Build content and summary from various source fields.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $creationInfo
     * @return array<string, mixed>
     */
    protected function resolveContent(array $data, array $metadata, array $creationInfo): array
    {
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
        $data['summary'] = $summary;

        $contentChunks = [];
        if (is_string($content)) {
            $contentChunks[] = $content;
        } elseif (is_string($summary)) {
            $contentChunks[] = $summary;
        }
        if (! empty($keyPoints)) {
            $contentChunks[] = implode("\n", $keyPoints);
        }
        $data['content'] = $contentChunks === [] ? null : trim(implode("\n\n", $contentChunks));

        $enrichedMetadata = array_merge($metadata, array_filter([
            'creation_info' => $creationInfo !== [] ? $creationInfo : null,
            'content' => is_array($content) && $content !== [] ? $content : null,
            'tags' => is_array($data['tags'] ?? null) ? array_values(array_filter($data['tags'], 'is_string')) : null,
            'entities_mentioned' => is_array($data['entities_mentioned'] ?? null) ? $data['entities_mentioned'] : null,
        ], static fn ($value) => $value !== null));

        $data['metadata'] = $enrichedMetadata === [] ? null : $enrichedMetadata;

        return $data;
    }

    /**
     * Normalize category_id from string or integer input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveCategoryId(array $data): array
    {
        $categoryId = $data['category_id'] ?? null;

        if (is_string($categoryId) && ctype_digit($categoryId)) {
            $categoryId = (int) $categoryId;
        } elseif (! is_int($categoryId)) {
            $categoryId = null;
        }

        $data['category_id'] = $categoryId;

        return $data;
    }
}
