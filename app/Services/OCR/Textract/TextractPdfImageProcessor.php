<?php

namespace App\Services\OCR\Textract;

use Aws\Textract\TextractClient;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;

class TextractPdfImageProcessor
{
    public static function process(TextractClient $client, string $bucket, string $s3Path, array $options = []): array
    {
        $textractDisk = Storage::disk('textract');
        $fileGuid = pathinfo($s3Path, PATHINFO_FILENAME);

        try {
            $pdfContent = $textractDisk->get($s3Path);
            $localPdfPath = storage_path('app/temp/'.$fileGuid.'.pdf');

            if (! is_dir(dirname($localPdfPath))) {
                mkdir(dirname($localPdfPath), 0755, true);
            }
            file_put_contents($localPdfPath, $pdfContent);

            if (! extension_loaded('imagick')) {
                Log::warning('[Textract] Imagick not available for PDF conversion');
                throw new Exception('PDF format not supported and conversion tools unavailable.');
            }
            $gsPath = exec('which gs 2>/dev/null');
            if (empty($gsPath)) {
                Log::warning('[Textract] Ghostscript not available for PDF conversion');
                throw new Exception('PDF format not supported and Ghostscript is unavailable.');
            }

            $pdf = new Pdf($localPdfPath);
            $pageCount = $pdf->pageCount();
            Log::info('[Textract] Converting PDF to images', ['pdf_path' => $localPdfPath, 'page_count' => $pageCount]);

            $allText = '';
            $allBlocks = [];
            $allForms = [];
            $allTables = [];
            $totalConfidence = 0.0;
            $processedPages = 0;

            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            for ($page = 1; $page <= min($pageCount, 10); $page++) {
                $savedFiles = (new Pdf($localPdfPath))
                    ->selectPage($page)
                    ->resolution(144)
                    ->quality(85)
                    ->save($tempDir, $fileGuid.'_page_'.$page);

                $imagePath = $savedFiles[0]->path ?? storage_path('app/temp/'.$fileGuid.'_page_'.$page.'.jpg');
                $imageContent = file_get_contents($imagePath);
                $imageS3Path = "temp/{$fileGuid}/page_{$page}.jpg";
                $textractDisk->put($imageS3Path, $imageContent);

                try {
                    $result = $client->analyzeDocument([
                        'Document' => [
                            'S3Object' => [
                                'Bucket' => $bucket,
                                'Name' => $imageS3Path,
                            ],
                        ],
                        'FeatureTypes' => $options['feature_types'] ?? ['LAYOUT', 'TABLES', 'FORMS'],
                    ]);

                    $pageResult = TextractResponseParser::parseDocument($result->toArray());

                    if ($page > 1) {
                        $allText .= "\n\n--- Page {$page} ---\n\n";
                    }
                    $allText .= $pageResult['text'];
                    $allBlocks = array_merge($allBlocks, $pageResult['blocks'] ?? []);
                    $allForms = array_merge($allForms, $pageResult['forms'] ?? []);
                    $allTables = array_merge($allTables, $pageResult['tables'] ?? []);
                    $totalConfidence += $pageResult['confidence'] ?? 0.0;
                    $processedPages++;
                } finally {
                    try {
                        $textractDisk->delete($imageS3Path);
                    } catch (Exception $e) {
                    }
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }
            }

            if (file_exists($localPdfPath)) {
                @unlink($localPdfPath);
            }

            Log::info('[Textract] Processed PDF via image conversion', ['pages_processed' => $processedPages, 'text_length' => strlen($allText)]);

            return [
                'text' => trim($allText),
                'metadata' => [
                    'page_count' => $processedPages,
                    'block_count' => count($allBlocks),
                    'extraction_type' => 'pdf_converted_to_images',
                    'original_pdf_pages' => $pageCount,
                ],
                'confidence' => $processedPages > 0 ? $totalConfidence / $processedPages : 0,
                'pages' => range(1, $processedPages),
                'blocks' => $allBlocks,
                'forms' => $allForms,
                'tables' => $allTables,
            ];
        } catch (Exception $e) {
            Log::error('[Textract] Failed to convert/process PDF', ['error' => $e->getMessage(), 's3_path' => $s3Path]);
            foreach (glob(storage_path('app/temp/'.$fileGuid.'*')) as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            throw $e;
        }
    }
}
