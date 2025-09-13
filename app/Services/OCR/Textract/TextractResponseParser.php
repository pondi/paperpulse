<?php

namespace App\Services\OCR\Textract;

class TextractResponseParser
{
    public static function parseBasic(array $result, string $type = 'basic'): array
    {
        $text = '';
        $blocks = $result['Blocks'] ?? [];
        $confidence = 0.0;
        $lineCount = 0;

        foreach ($blocks as $block) {
            if (($block['BlockType'] ?? null) === 'LINE' && isset($block['Text'])) {
                $text .= $block['Text'] . "\n";
                $confidence += $block['Confidence'] ?? 0;
                $lineCount++;
            }
        }

        return [
            'text' => trim($text),
            'metadata' => [
                'block_count' => count($blocks),
                'line_count' => $lineCount,
                'extraction_type' => $type,
                'textract_job_id' => $result['JobId'] ?? null,
            ],
            'confidence' => $lineCount > 0 ? $confidence / $lineCount / 100 : 0,
            'blocks' => $blocks,
        ];
    }

    public static function parseDocument(array $result): array
    {
        $text = '';
        $blocks = $result['Blocks'] ?? [];
        $pages = [];
        $confidence = 0.0;
        $lineCount = 0;

        $blocksById = array_column($blocks, null, 'Id');

        foreach ($blocks as $block) {
            $page = $block['Page'] ?? 1;
            $pages[$page][] = $block;
        }

        foreach ($pages as $pageNum => $pageBlocks) {
            if ($pageNum > 1) {
                $text .= "\n\n--- Page {$pageNum} ---\n\n";
            }
            usort($pageBlocks, function ($a, $b) {
                $aTop = $a['Geometry']['BoundingBox']['Top'] ?? 0;
                $bTop = $b['Geometry']['BoundingBox']['Top'] ?? 0;
                return $aTop <=> $bTop;
            });
            foreach ($pageBlocks as $block) {
                if (($block['BlockType'] ?? null) === 'LINE' && isset($block['Text'])) {
                    $text .= $block['Text'] . "\n";
                    $confidence += $block['Confidence'] ?? 0;
                    $lineCount++;
                } elseif (($block['BlockType'] ?? null) === 'TABLE') {
                    $tableData = self::parseTable($block, $blocksById);
                    $text .= self::formatTableAsText($tableData) . "\n";
                }
            }
        }

        return [
            'text' => trim($text),
            'metadata' => [
                'page_count' => count($pages),
                'block_count' => count($blocks),
                'line_count' => $lineCount,
                'extraction_type' => 'document_analysis',
            ],
            'confidence' => $lineCount > 0 ? $confidence / $lineCount / 100 : 0,
            'pages' => array_keys($pages),
            'blocks' => $blocks,
        ];
    }

    public static function parseStructured(array $result, string $type = 'receipt'): array
    {
        $blocks = $result['Blocks'] ?? [];
        $blocksById = array_column($blocks, null, 'Id');
        
        $text = '';
        $forms = [];
        $tables = [];
        $confidence = 0.0;
        $lineCount = 0;

        foreach ($blocks as $block) {
            if (($block['BlockType'] ?? null) === 'LINE' && isset($block['Text'])) {
                $text .= $block['Text'] . "\n";
                $confidence += $block['Confidence'] ?? 0;
                $lineCount++;
            }
        }

        foreach ($blocks as $block) {
            if (($block['BlockType'] ?? null) === 'KEY_VALUE_SET' && isset($block['EntityTypes']) && in_array('KEY', $block['EntityTypes'])) {
                if (isset($block['Relationships'])) {
                    $keyText = '';
                    $valueText = '';
                    foreach ($block['Relationships'] as $relationship) {
                        if ($relationship['Type'] === 'CHILD') {
                            $keyText = self::resolveBlockText($relationship['Ids'], $blocksById);
                        }
                        if ($relationship['Type'] === 'VALUE') {
                            foreach ($relationship['Ids'] as $valueId) {
                                if (isset($blocksById[$valueId])) {
                                    $valueBlock = $blocksById[$valueId];
                                    if (isset($valueBlock['Relationships'])) {
                                        foreach ($valueBlock['Relationships'] as $valueRel) {
                                            if ($valueRel['Type'] === 'CHILD') {
                                                $valueText = self::resolveBlockText($valueRel['Ids'], $blocksById);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $keyText = trim($keyText);
                    if ($keyText !== '') {
                        $forms[$keyText] = trim($valueText);
                    }
                }
            }
        }

        foreach ($blocks as $block) {
            if (($block['BlockType'] ?? null) === 'TABLE') {
                $tables[] = self::parseTable($block, $blocksById);
            }
        }

        return [
            'text' => trim($text),
            'metadata' => [
                'block_count' => count($blocks),
                'line_count' => $lineCount,
                'extraction_type' => $type,
            ],
            'confidence' => $lineCount > 0 ? $confidence / $lineCount / 100 : 0,
            'blocks' => $blocks,
            'forms' => $forms,
            'tables' => $tables,
        ];
    }

    public static function resolveBlockText(array $blockIds, array $blocksById): string
    {
        $text = '';
        foreach ($blockIds as $blockId) {
            if (isset($blocksById[$blockId]['Text'])) {
                $text .= $blocksById[$blockId]['Text'] . ' ';
            }
        }
        return trim($text);
    }

    public static function parseTable(array $tableBlock, array $blocksById): array
    {
        $table = [];
        if (!isset($tableBlock['Relationships'])) {
            return $table;
        }
        foreach ($tableBlock['Relationships'] as $relationship) {
            if ($relationship['Type'] === 'CHILD') {
                foreach ($relationship['Ids'] as $cellId) {
                    if (isset($blocksById[$cellId]) && ($blocksById[$cellId]['BlockType'] ?? null) === 'CELL') {
                        $cell = $blocksById[$cellId];
                        $row = ($cell['RowIndex'] ?? 1) - 1;
                        $col = ($cell['ColumnIndex'] ?? 1) - 1;

                        $cellText = '';
                        if (isset($cell['Relationships'])) {
                            foreach ($cell['Relationships'] as $cellRelation) {
                                if ($cellRelation['Type'] === 'CHILD') {
                                    $cellText = self::resolveBlockText($cellRelation['Ids'], $blocksById);
                                }
                            }
                        }
                        $table[$row][$col] = trim($cellText);
                    }
                }
            }
        }
        return $table;
    }

    public static function formatTableAsText(array $table): string
    {
        if (empty($table)) {
            return '';
        }
        $text = '';
        foreach ($table as $row) {
            if (is_array($row)) {
                $text .= implode(' | ', $row) . "\n";
            }
        }
        return trim($text);
    }
}

