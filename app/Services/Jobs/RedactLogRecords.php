<?php

namespace App\Services\Jobs;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class RedactLogRecords
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(static function (LogRecord $record): LogRecord {
            return $record->with(message: self::message($record->message), context: self::context($record->context), extra: self::context($record->extra));
        });
    }

    public static function context(array $context, int $depth = 0): array
    {
        if ($depth >= 4) {
            return [];
        }
        $safe = [];
        foreach (array_slice($context, 0, 25, true) as $key => $value) {
            if (preg_match('/(?:token|secret|password|key|authorization|cookie|content|text|body|payload|response|request_data|url|uri|filename|file_name|title|description|note|merchant|account|email|trace|error|exception)/i', (string) $key)
                && ! in_array($key, ['error_code', 'error_class', 'content_length', 'response_length', 'error_count', 'errors_count'], true)) {
                $safe[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $safe[$key] = self::context($value, $depth + 1);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_string($value) && preg_match('/(?:^|_)(?:id|guid|stage|provider|model|type|class|method|status|code|reason|channel|queue|extension|mime|format)$/', (string) $key)) {
                $safe[$key] = mb_substr(self::message($value), 0, 120);
            } else {
                $safe[$key] = '[redacted]';
            }
        }

        return $safe;
    }

    public static function message(string $message): string
    {
        $message = preg_replace('~https?://[^\s]+~i', '[redacted URL]', $message);
        $message = preg_replace('/(?:api[_ -]?key|token|password|secret|authorization)\s*[:=]\s*[^\s,;]+/i', '[redacted credential]', $message);
        $message = preg_replace('/(failed|error|exception)\s*:\s*.*/is', '$1: [redacted details]', $message);

        return mb_substr($message, 0, 512);
    }
}
