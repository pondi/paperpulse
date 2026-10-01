<?php

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

class ConversionCapabilities
{
    /** @var array<string, string> */
    public const OFFICE_MIME_TYPES = [
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'rtf' => 'application/rtf',
    ];

    public static function detect(string $source, string $extension): string
    {
        $extension = strtolower($extension);
        $expected = self::OFFICE_MIME_TYPES[$extension] ?? null;
        if ($expected === null || ! is_file($source) || filesize($source) === 0) {
            throw new RuntimeException('Unsupported or empty conversion input.');
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if (in_array($extension, ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'], true)) {
            $archive = new ZipArchive;
            if ($archive->open($source) !== true) {
                throw new RuntimeException('Invalid office document container.');
            }
            try {
                $entry = match ($extension) {
                    'docx' => 'word/document.xml',
                    'xlsx' => 'xl/workbook.xml',
                    'pptx' => 'ppt/presentation.xml',
                    default => 'content.xml',
                };
                if ($archive->locateName($entry) === false) {
                    throw new RuntimeException('Office document content does not match its extension.');
                }
                if (in_array($extension, ['odt', 'ods', 'odp'], true) && $archive->getFromName('mimetype', 256) !== $expected) {
                    throw new RuntimeException('OpenDocument MIME type does not match its extension.');
                }
            } finally {
                $archive->close();
            }

            return $expected;
        }
        if ($extension === 'rtf' && in_array($detected, ['application/rtf', 'text/rtf'], true)) {
            return $expected;
        }
        if (in_array($extension, ['doc', 'xls', 'ppt'], true)
            && file_get_contents($source, false, null, 0, 8) === hex2bin('d0cf11e0a1b11ae1')) {
            return $expected;
        }

        throw new RuntimeException('Conversion input bytes do not match an office document.');
    }
}
