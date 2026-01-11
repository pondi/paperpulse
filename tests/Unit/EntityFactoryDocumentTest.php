<?php

use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Services\EntityFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a document from normalized gemini data', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'processing_type' => 'gemini',
        'status' => 'processing',
    ]);

    $parsedData = [
        'entities' => [
            [
                'type' => 'document',
                'data' => [
                    'metadata' => [
                        'title' => 'New Global Security Solution for DeepOcean Group',
                        'type' => 'report',
                        'category' => 'business',
                    ],
                    'creation_info' => [
                        'author' => 'Jane Doe',
                        'creation_date' => '2026-01-05',
                    ],
                    'content' => [
                        'summary' => 'A summary about the report.',
                        'key_points' => ['Point A', 'Point B'],
                    ],
                    'entities_mentioned' => [
                        ['entity_name' => 'DeepOcean Group', 'entity_type' => 'organization'],
                    ],
                    'tags' => ['security', 'business'],
                ],
                'confidence_score' => 0.9,
            ],
        ],
    ];

    $factory = app(EntityFactory::class);
    $factory->createEntitiesFromParsedData($parsedData, $file, 'document');

    $document = Document::first();
    expect($document)->not->toBeNull();
    expect($document->title)->toBe('New Global Security Solution for DeepOcean Group');
    expect($document->document_type)->toBe('report');
    expect($document->document_date?->format('Y-m-d'))->toBe('2026-01-05');
    expect($document->summary)->toBe('A summary about the report.');
    expect($document->content)->toBeString();
    expect($document->content)->toContain('Point A');
    expect($document->content)->toContain('Point B');
    expect($document->metadata)->toBeArray();
    expect($document->metadata['title'])->toBe('New Global Security Solution for DeepOcean Group');
    expect($document->metadata['content']['key_points'])->toBe(['Point A', 'Point B']);
    expect($document->ai_entities[0]['entity_name'])->toBe('DeepOcean Group');
});
