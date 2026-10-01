<?php

namespace App\Services\AI\Shared;

use InvalidArgumentException;

class DocumentContentBudget
{
    public static function select(string $content, int $maxBytes): array
    {
        if ($maxBytes < 64) {
            throw new InvalidArgumentException('Document prompt budget has no room for content');
        }
        $total = strlen($content);
        if ($total <= $maxBytes) {
            return ['content' => $content, 'coverage' => ['total_bytes' => $total, 'selected_bytes' => $total, 'truncated' => false]];
        }
        $marker = "\n[Middle document sections omitted]\n";
        $available = $maxBytes - strlen($marker);
        $beginning = mb_strcut($content, 0, intdiv($available, 2), 'UTF-8');
        $tailBytes = $available - strlen($beginning);
        $end = mb_strcut($content, $total - $tailBytes, null, 'UTF-8');
        if (strlen($end) > $tailBytes) {
            $end = mb_substr($end, 1, null, 'UTF-8');
        }

        return ['content' => $beginning.$marker.$end, 'coverage' => [
            'total_bytes' => $total, 'selected_bytes' => strlen($beginning) + strlen($end), 'truncated' => true,
        ]];
    }
}
