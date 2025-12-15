<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileShare;
use App\Services\StorageService;
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

        // Determine file type from DB (not from request)
        $fileType = $file->file_type === 'receipt' ? 'receipt' : 'document';
        $variant = $requestedVariant ?? 'original';

        // Check if requesting preview
        if ($type === 'preview' || ($type === 'image' && strtolower($extension) === 'jpg')) {
            $variant = 'preview';
        }

        // Authorization: owner, valid share, or admin
        $isOwner = $file->user_id === auth()->id();
        $hasShare = FileShare::active()
            ->where('file_id', $file->id)
            ->where('file_type', $fileType)
            ->where('shared_with_user_id', auth()->id())
            ->exists();

        if (! $isOwner && ! $hasShare && ! (auth()->user()?->is_admin)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Prefer serving from stored S3 paths to avoid issues when a file was mis-typed.
        $content = null;
        if ($variant === 'preview' && $file->has_image_preview && $file->s3_image_path) {
            $content = $storageService->getFile($file->s3_image_path);
            $extension = 'jpg';
        } elseif ($variant === 'archive' && ! empty($file->s3_converted_path)) {
            $content = $storageService->getFile($file->s3_converted_path);
            $extension = 'pdf';
        } elseif ($variant === 'original' && ! empty($file->s3_original_path)) {
            $content = $storageService->getFile($file->s3_original_path);
            $extension = $file->fileExtension ?? $extension;
        }

        if (! $content) {
            // Fallback: compute canonical path from GUID/type/variant/extension
            $content = $storageService->getFileByUserAndGuid($file->user_id, $guid, $fileType, $variant, $extension);
        }

        if (! $content) {
            // If preview was requested but not found, try to fall back to original
            if ($variant === 'preview' && $file->fileExtension) {
                Log::info('(FileServeController) [serve] - Preview not found, falling back to original', [
                    'guid' => $guid,
                    'original_extension' => $file->fileExtension,
                ]);

                if (! empty($file->s3_original_path)) {
                    $content = $storageService->getFile($file->s3_original_path);
                } else {
                    $content = $storageService->getFileByUserAndGuid($file->user_id, $guid, $fileType, 'original', $file->fileExtension);
                }
                $extension = $file->fileExtension;
            }

            if (! $content) {
                Log::error('(FileServeController) [serve] - Document not found', [
                    'guid' => $guid,
                    'type' => $type,
                    'extension' => $extension,
                    'user_id' => $file->user_id,
                    'variant' => $variant,
                ]);

                return response()->json(['error' => 'File not found'], 404);
            }
        }

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
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'Content-Disposition' => 'inline; filename="document.'.$extension.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
