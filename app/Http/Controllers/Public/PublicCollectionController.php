<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PublicShareAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inertia\PublicCollectionFileResource;
use App\Services\PublicCollectionSharingService;
use App\Services\StorageService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PublicCollectionController extends Controller
{
    public function __construct(
        private readonly PublicCollectionSharingService $sharingService,
    ) {}

    public function show(Request $request, string $token): InertiaResponse
    {
        $link = $this->sharingService->findLinkByToken($token);

        if (! $link || ! $link->isAccessible()) {
            return Inertia::render('Public/SharedCollectionExpired');
        }

        if ($link->isPasswordProtected() && ! $this->isUnlocked($request, $link->id)) {
            return Inertia::render('Public/SharedCollectionPassword', [
                'collectionName' => $link->collection->name,
                'token' => $token,
            ]);
        }

        $data = $this->sharingService->getCollectionForPublicView($link);

        $link->incrementViewCount();
        $this->sharingService->logAccess($link, $request, PublicShareAction::View);

        $files = $data['files']->map(fn ($file) => (new PublicCollectionFileResource($file, $token))->resolve($request));

        return Inertia::render('Public/SharedCollection', [
            'collection' => [
                'name' => $data['collection']->name,
                'description' => $data['collection']->description,
                'icon' => $data['collection']->icon,
                'color' => $data['collection']->color,
                'owner_name' => $data['collection']->user->name,
            ],
            'files' => $files,
            'link' => [
                'expires_at' => $link->expires_at?->toISOString(),
                'label' => $link->label,
                'token' => $token,
            ],
        ]);
    }

    public function verifyPassword(Request $request, string $token): InertiaResponse|Response
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $link = $this->sharingService->findLinkByToken($token);

        if (! $link || ! $link->isAccessible()) {
            return Inertia::render('Public/SharedCollectionExpired');
        }

        if ($this->sharingService->verifyPassword($link, $request->input('password'))) {
            $this->sharingService->logAccess($link, $request, PublicShareAction::PasswordSuccess);
            $request->session()->put('public_share_unlocked_'.$link->id, true);

            return redirect()->route('shared.collections.show', $token);
        }

        $this->sharingService->logAccess($link, $request, PublicShareAction::PasswordAttempt);

        return back()->withErrors(['password' => 'The password is incorrect.']);
    }

    public function serveFile(Request $request, string $token, string $guid): Response
    {
        $link = $this->sharingService->findLinkByToken($token);

        if (! $link || ! $link->isAccessible()) {
            abort(404);
        }

        if ($link->isPasswordProtected() && ! $this->isUnlocked($request, $link->id)) {
            abort(403);
        }

        $file = $this->sharingService->fileExistsInCollection($link, $guid);

        if (! $file) {
            abort(404);
        }

        $storageService = app(StorageService::class);
        $variant = $request->input('variant', 'original');
        $extension = $file->fileExtension ?? 'pdf';

        $content = null;
        if ($variant === 'preview' && $file->has_image_preview && $file->s3_image_path) {
            $content = $storageService->getFile($file->s3_image_path);
            $extension = 'jpg';
        } elseif ($variant === 'archive' && ! empty($file->s3_archive_path)) {
            $content = $storageService->getFile($file->s3_archive_path);
            $extension = 'pdf';
        } elseif (! empty($file->s3_original_path)) {
            $content = $storageService->getFile($file->s3_original_path);
        }

        if (! $content) {
            $fileType = $file->file_type === 'receipt' ? 'receipt' : 'document';
            $content = $storageService->getFileByUserAndGuid(
                $file->user_id,
                $guid,
                $fileType,
                $variant === 'preview' ? 'preview' : 'original',
                $extension,
            );
        }

        if (! $content) {
            abort(404);
        }

        $this->sharingService->logAccess($link, $request, PublicShareAction::DownloadFile, [
            'file_id' => $file->id,
            'file_guid' => $guid,
        ]);

        $mimeType = $this->getMimeType($extension);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return new StreamedResponse(function () use ($content) {
            echo $content;
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'Content-Disposition' => $disposition.'; filename="'.preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $file->fileName ?? 'file').'.'.$extension.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadAll(Request $request, string $token): StreamedResponse
    {
        $link = $this->sharingService->findLinkByToken($token);

        if (! $link || ! $link->isAccessible()) {
            abort(404);
        }

        if ($link->isPasswordProtected() && ! $this->isUnlocked($request, $link->id)) {
            abort(403);
        }

        $data = $this->sharingService->getCollectionForPublicView($link);

        $this->sharingService->logAccess($link, $request, PublicShareAction::DownloadAll);

        $zipFileName = 'collection_'.now()->format('Y-m-d_H-i-s').'.zip';

        return new StreamedResponse(function () use ($data) {
            $zipPath = tempnam(sys_get_temp_dir(), 'public_collection_download');
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                Log::error('Failed to create zip file for public collection download');

                return;
            }

            $storageService = app(StorageService::class);
            $filenameCounter = [];

            foreach ($data['files'] as $file) {
                try {
                    if (! $file->guid) {
                        continue;
                    }

                    $extension = $file->fileExtension ?? 'pdf';
                    $fileContent = null;

                    if (! empty($file->s3_original_path)) {
                        $fileContent = $storageService->getFile($file->s3_original_path);
                    }

                    if (! $fileContent) {
                        $fileType = $file->file_type === 'receipt' ? 'receipt' : 'document';
                        $fileContent = $storageService->getFileByUserAndGuid(
                            $file->user_id,
                            $file->guid,
                            $fileType,
                            'original',
                            $extension,
                        );
                    }

                    if (! $fileContent) {
                        continue;
                    }

                    $originalName = $file->fileName ?? $file->guid;
                    $safeFilename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $originalName);

                    $baseFilename = $safeFilename.'.'.$extension;
                    $finalFilename = $baseFilename;
                    $counter = 1;

                    while (isset($filenameCounter[$finalFilename])) {
                        $finalFilename = $safeFilename.'_'.$counter.'.'.$extension;
                        $counter++;
                    }

                    $filenameCounter[$finalFilename] = true;
                    $zip->addFromString($finalFilename, $fileContent);
                } catch (Exception $e) {
                    Log::error('Error adding file to public collection zip: '.$e->getMessage(), [
                        'file_id' => $file->id,
                    ]);
                }
            }

            $zip->close();

            if (file_exists($zipPath)) {
                readfile($zipPath);
                unlink($zipPath);
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zipFileName.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function isUnlocked(Request $request, int $linkId): bool
    {
        return $request->session()->get('public_share_unlocked_'.$linkId) === true;
    }

    private function getMimeType(string $extension): string
    {
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
            'csv' => 'text/csv; charset=utf-8',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}
