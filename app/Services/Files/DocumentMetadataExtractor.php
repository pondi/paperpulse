<?php

namespace App\Services\Files;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class DocumentMetadataExtractor
{
    /**
     * Extract creation and modification dates from a file
     *
     * @param string $filePath Path to the file
     * @param string $extension File extension
     * @return array ['created_at' => Carbon|null, 'modified_at' => Carbon|null]
     */
    public static function extractDates(string $filePath, string $extension): array
    {
        $extension = strtolower($extension);

        // Try format-specific extraction first
        $dates = match ($extension) {
            'docx' => self::extractFromDocx($filePath),
            'xlsx' => self::extractFromXlsx($filePath),
            'pptx' => self::extractFromPptx($filePath),
            'jpg', 'jpeg' => self::extractFromJpeg($filePath),
            'pdf' => self::extractFromPdf($filePath),
            default => ['created_at' => null, 'modified_at' => null]
        };

        // If we got valid dates, return them
        if ($dates['created_at'] || $dates['modified_at']) {
            return $dates;
        }

        // Fallback to file system timestamps (not ideal but better than nothing)
        return self::extractFromFileSystem($filePath);
    }

    /**
     * Extract dates from DOCX file metadata
     */
    private static function extractFromDocx(string $filePath): array
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                return ['created_at' => null, 'modified_at' => null];
            }

            // Read core.xml which contains document metadata
            $coreXml = $zip->getFromName('docProps/core.xml');
            $zip->close();

            if ($coreXml === false) {
                return ['created_at' => null, 'modified_at' => null];
            }

            $xml = simplexml_load_string($coreXml);
            if ($xml === false) {
                return ['created_at' => null, 'modified_at' => null];
            }

            // Register namespaces
            $namespaces = $xml->getNamespaces(true);
            $dcterms = $namespaces['dcterms'] ?? 'http://purl.org/dc/terms/';

            $created = null;
            $modified = null;

            // Extract created date
            $createdNodes = $xml->children($dcterms)->created ?? null;
            if ($createdNodes && (string) $createdNodes) {
                try {
                    $created = Carbon::parse((string) $createdNodes);
                } catch (Exception $e) {
                    Log::debug('[DocumentMetadataExtractor] Failed to parse created date from DOCX', [
                        'date_string' => (string) $createdNodes,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Extract modified date
            $modifiedNodes = $xml->children($dcterms)->modified ?? null;
            if ($modifiedNodes && (string) $modifiedNodes) {
                try {
                    $modified = Carbon::parse((string) $modifiedNodes);
                } catch (Exception $e) {
                    Log::debug('[DocumentMetadataExtractor] Failed to parse modified date from DOCX', [
                        'date_string' => (string) $modifiedNodes,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'created_at' => $created,
                'modified_at' => $modified,
            ];
        } catch (Exception $e) {
            Log::debug('[DocumentMetadataExtractor] Failed to extract DOCX metadata', [
                'error' => $e->getMessage(),
            ]);

            return ['created_at' => null, 'modified_at' => null];
        }
    }

    /**
     * Extract dates from XLSX file metadata
     */
    private static function extractFromXlsx(string $filePath): array
    {
        // XLSX has similar structure to DOCX
        return self::extractFromDocx($filePath);
    }

    /**
     * Extract dates from PPTX file metadata
     */
    private static function extractFromPptx(string $filePath): array
    {
        // PPTX has similar structure to DOCX
        return self::extractFromDocx($filePath);
    }

    /**
     * Extract dates from JPEG EXIF data
     */
    private static function extractFromJpeg(string $filePath): array
    {
        try {
            if (! function_exists('exif_read_data')) {
                return ['created_at' => null, 'modified_at' => null];
            }

            $exif = @exif_read_data($filePath);
            if ($exif === false) {
                return ['created_at' => null, 'modified_at' => null];
            }

            $created = null;
            $modified = null;

            // Try DateTimeOriginal first (when photo was taken)
            if (isset($exif['DateTimeOriginal'])) {
                try {
                    $created = Carbon::createFromFormat('Y:m:d H:i:s', $exif['DateTimeOriginal']);
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // Try DateTime for modified
            if (isset($exif['DateTime'])) {
                try {
                    $modified = Carbon::createFromFormat('Y:m:d H:i:s', $exif['DateTime']);
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // If no DateTimeOriginal, use DateTime as created date too
            if (! $created && $modified) {
                $created = $modified;
            }

            return [
                'created_at' => $created,
                'modified_at' => $modified,
            ];
        } catch (Exception $e) {
            return ['created_at' => null, 'modified_at' => null];
        }
    }

    /**
     * Extract dates from PDF metadata
     */
    private static function extractFromPdf(string $filePath): array
    {
        try {
            // Read first few KB of PDF to find metadata
            $handle = fopen($filePath, 'r');
            if (! $handle) {
                return ['created_at' => null, 'modified_at' => null];
            }

            $content = fread($handle, 8192); // Read first 8KB
            fclose($handle);

            $created = null;
            $modified = null;

            // Look for CreationDate in PDF metadata
            if (preg_match('/\/CreationDate\s*\(D:(\d{14})/', $content, $matches)) {
                try {
                    $dateStr = $matches[1];
                    $created = Carbon::createFromFormat('YmdHis', $dateStr);
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // Look for ModDate in PDF metadata
            if (preg_match('/\/ModDate\s*\(D:(\d{14})/', $content, $matches)) {
                try {
                    $dateStr = $matches[1];
                    $modified = Carbon::createFromFormat('YmdHis', $dateStr);
                } catch (Exception $e) {
                    // Ignore
                }
            }

            return [
                'created_at' => $created,
                'modified_at' => $modified,
            ];
        } catch (Exception $e) {
            return ['created_at' => null, 'modified_at' => null];
        }
    }

    /**
     * Fallback: Extract dates from file system
     * Note: These are not the original file dates, but when the file was uploaded/created on server
     */
    private static function extractFromFileSystem(string $filePath): array
    {
        try {
            $created = null;
            $modified = null;

            if (file_exists($filePath)) {
                $mtime = @filemtime($filePath);
                if ($mtime !== false) {
                    $modified = Carbon::createFromTimestamp($mtime);
                }

                $ctime = @filectime($filePath);
                if ($ctime !== false) {
                    $created = Carbon::createFromTimestamp($ctime);
                }
            }

            return [
                'created_at' => $created,
                'modified_at' => $modified,
            ];
        } catch (Exception $e) {
            return ['created_at' => null, 'modified_at' => null];
        }
    }
}
