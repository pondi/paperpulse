<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use App\Support\UploadedContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileServeController extends Controller
{
    public function serve(Request $request, StorageService $storageService)
    {
        $request->validate([
            'guid' => 'required|string|regex:/^[a-f0-9\-]{36}$/i',
            'type' => 'required|string|in:receipts,image,pdf,documents,preview',
            'extension' => 'required|string|in:jpg,jpeg,png,gif,webp,bmp,tif,tiff,pdf,txt,rtf,html,csv,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,JPG,JPEG,PNG,GIF,WEBP,BMP,TIF,TIFF,PDF,TXT,RTF,HTML,CSV,DOC,DOCX,XLS,XLSX,PPT,PPTX,ODT,ODS,ODP',
            'variant' => 'nullable|string|in:original,archive,preview',
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');
        $requestedVariant = $request->input('variant');

        // Look up file by GUID across users
        $file = File::withoutGlobalScope('user')->where('guid', $guid)->first();
        if (! $file) {
            Log::warning('(FileServeController) [serve] - File not found by GUID', [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
            ]);

            return response()->json(['error' => 'File not found'], 404);
        }

        $variant = $requestedVariant ?? 'original';

        // Check if requesting preview
        if ($type === 'preview' || ($type === 'image' && strtolower($extension) === 'jpg')) {
            $variant = 'preview';
        }

        if (! auth()->user()->can('view', $file)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $path = StoragePathBuilder::variantPath($file, $variant);
        $content = $path ? $storageService->getFile($path) : null;
        if ($content === null) {
            return response()->json(['error' => 'File not found'], 404);
        }
        $extension = match ($variant) {
            'preview' => 'jpg',
            'archive' => 'pdf',
            default => strtolower((string) $file->fileExtension),
        };

        // Map extension to MIME type
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=utf-8',
            'rtf' => 'application/rtf',
            'html' => 'text/html; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'JPG' => 'image/jpeg',
            'JPEG' => 'image/jpeg',
            'PNG' => 'image/png',
            'GIF' => 'image/gif',
            'WEBP' => 'image/webp',
            'BMP' => 'image/bmp',
            'TIF' => 'image/tiff',
            'TIFF' => 'image/tiff',
            'PDF' => 'application/pdf',
            'TXT' => 'text/plain; charset=utf-8',
            'RTF' => 'application/rtf',
            'HTML' => 'text/html; charset=utf-8',
            'CSV' => 'text/csv; charset=utf-8',
            'DOC' => 'application/msword',
            'DOCX' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'XLS' => 'application/vnd.ms-excel',
            'XLSX' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'PPT' => 'application/vnd.ms-powerpoint',
            'PPTX' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ODT' => 'application/vnd.oasis.opendocument.text',
            'ODS' => 'application/vnd.oasis.opendocument.spreadsheet',
            'ODP' => 'application/vnd.oasis.opendocument.presentation',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Create a StreamedResponse
        return new StreamedResponse(function () use ($content) {
            echo $content;
        }, 200, array_merge([
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'Content-Disposition' => 'inline; filename="document.'.$extension.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ], UploadedContent::headers($extension, 'document.'.$extension)));
    }
}
