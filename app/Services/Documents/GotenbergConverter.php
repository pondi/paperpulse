<?php

namespace App\Services\Documents;

use App\Contracts\DocumentConverter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GotenbergConverter implements DocumentConverter
{
    public function supportedMimeTypes(): array
    {
        return array_values(ConversionCapabilities::OFFICE_MIME_TYPES);
    }

    public function convert(string $source, string $destination, string $mimeType): void
    {
        if (! in_array($mimeType, $this->supportedMimeTypes(), true)) {
            throw new RuntimeException('The external converter does not support this MIME type.');
        }
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Conversion source is unreadable.');
        }
        try {
            $response = Http::connectTimeout(5)
                ->timeout(config('processing.conversion.timeout', 120))
                ->sink($destination)
                ->attach('files', $stream, basename($source))
                ->post(rtrim(config('processing.conversion.service_url'), '/').'/forms/libreoffice/convert', [
                    'pdfa' => 'PDF/A-2b',
                    'updateDocumentLinks' => 'false',
                ]);
            if (! $response->successful()) {
                throw new RuntimeException('The external converter rejected the document.');
            }
        } finally {
            fclose($stream);
        }
    }
}
