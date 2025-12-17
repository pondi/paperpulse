<?php

use App\Services\OCR\Textract\TextractResponseParser;

test('parseDocumentPage formats page header and sorts lines', function () {
    $blocks = [
        [
            'Id' => 'l2',
            'BlockType' => 'LINE',
            'Text' => 'Second line',
            'Confidence' => 90,
            'Geometry' => ['BoundingBox' => ['Top' => 0.5]],
        ],
        [
            'Id' => 'l1',
            'BlockType' => 'LINE',
            'Text' => 'First line',
            'Confidence' => 80,
            'Geometry' => ['BoundingBox' => ['Top' => 0.1]],
        ],
    ];

    $parsed = TextractResponseParser::parseDocumentPage($blocks, 2);

    expect($parsed['text'])->toContain('--- Page 2 ---')
        ->and($parsed['text'])->toContain("First line\nSecond line\n")
        ->and($parsed['line_count'])->toBe(2)
        ->and($parsed['confidence_sum'])->toBe(170.0);
});

test('parseDocumentPage renders tables using relationships', function () {
    $blocks = [
        [
            'Id' => 't1',
            'BlockType' => 'TABLE',
            'Relationships' => [
                ['Type' => 'CHILD', 'Ids' => ['c1']],
            ],
            'Geometry' => ['BoundingBox' => ['Top' => 0.2]],
        ],
        [
            'Id' => 'c1',
            'BlockType' => 'CELL',
            'RowIndex' => 1,
            'ColumnIndex' => 1,
            'Relationships' => [
                ['Type' => 'CHILD', 'Ids' => ['w1']],
            ],
        ],
        [
            'Id' => 'w1',
            'BlockType' => 'WORD',
            'Text' => 'A1',
        ],
    ];

    $parsed = TextractResponseParser::parseDocumentPage($blocks, 1);

    expect($parsed['text'])->toContain("A1\n");
});

test('parseStructuredPage extracts key value pairs and tables', function () {
    $blocks = [
        [
            'Id' => 'kv_key',
            'BlockType' => 'KEY_VALUE_SET',
            'EntityTypes' => ['KEY'],
            'Relationships' => [
                ['Type' => 'CHILD', 'Ids' => ['k_word']],
                ['Type' => 'VALUE', 'Ids' => ['kv_val']],
            ],
        ],
        [
            'Id' => 'k_word',
            'BlockType' => 'WORD',
            'Text' => 'Total',
        ],
        [
            'Id' => 'kv_val',
            'BlockType' => 'KEY_VALUE_SET',
            'EntityTypes' => ['VALUE'],
            'Relationships' => [
                ['Type' => 'CHILD', 'Ids' => ['v_word']],
            ],
        ],
        [
            'Id' => 'v_word',
            'BlockType' => 'WORD',
            'Text' => '123.45',
        ],
        [
            'Id' => 't1',
            'BlockType' => 'TABLE',
            'Relationships' => [
                ['Type' => 'CHILD', 'Ids' => ['c1']],
            ],
        ],
        [
            'Id' => 'c1',
            'BlockType' => 'CELL',
            'RowIndex' => 1,
            'ColumnIndex' => 1,
            'Relationships' => [
                ['Type' => 'CHILD', 'Ids' => ['w1']],
            ],
        ],
        [
            'Id' => 'w1',
            'BlockType' => 'WORD',
            'Text' => 'Item',
        ],
    ];

    $parsed = TextractResponseParser::parseStructuredPage($blocks);

    expect($parsed['forms'])->toHaveKey('Total', '123.45')
        ->and($parsed['tables'])->toHaveCount(1)
        ->and($parsed['text'])->toBe('');
});

