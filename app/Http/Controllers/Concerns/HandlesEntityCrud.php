<?php

namespace App\Http\Controllers\Concerns;

use App\Models\File;
use App\Models\Tag;
use App\Services\Files\FileDeletionService;
use App\Services\StorageService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shared CRUD operations for entity controllers (Invoice, Contract, Voucher).
 *
 * Provides download, destroy, attachTag, and detachTag methods that follow
 * the same patterns used by DocumentController.
 */
trait HandlesEntityCrud
{
    /**
     * Download the original file for an entity.
     */
    protected function entityDownload(Model $entity): mixed
    {
        $this->authorize('view', $entity);

        $entity->loadMissing('file');

        if (! $entity->file || ! $entity->file->s3_original_path) {
            abort(404, 'File not found');
        }

        try {
            $storageService = app(StorageService::class);
            $extension = $entity->file->fileExtension ?? 'pdf';
            $content = $storageService->getFile($entity->file->s3_original_path);

            if ($content === null) {
                abort(404, 'File not found');
            }

            $rawFilename = $entity->file->fileName
                ?? ($this->getEntityTitle($entity)
                    ? preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $this->getEntityTitle($entity)).'.'.$extension
                    : $this->getModelName().'.'.$extension);

            // Sanitize filename to prevent header injection (remove quotes, newlines, control chars)
            $filename = preg_replace('/["\r\n\x00-\x1f\x7f]/', '_', $rawFilename);

            return response($content)
                ->header('Content-Type', $entity->file->mime_type ?? 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } catch (Exception $e) {
            Log::error('Failed to download '.$this->getModelName(), [
                $this->getModelName().'_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to download file');
        }
    }

    /**
     * Delete an entity and clean up its file record.
     */
    protected function entityDestroy(Model $entity): mixed
    {
        $this->authorize('delete', $entity);

        $entity->loadMissing('file');
        $modelName = $this->getModelName();

        try {
            app(FileDeletionService::class)
                ->deleteEntity($entity, (int) auth()->id());

            return redirect()->route($this->getRouteName().'.index')
                ->with('success', ucfirst($modelName).' deleted successfully');
        } catch (Exception $e) {
            Log::error('Failed to delete '.$modelName, [
                $modelName.'_id' => $entity->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to delete '.ucfirst($modelName).'. Please try again.');
        }
    }

    /**
     * Attach a tag to an entity.
     */
    protected function entityAttachTag(Request $request, Model $entity): mixed
    {
        $this->authorize('update', $entity);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $entity->addTagByName($validated['name']);

        return back()->with('success', 'Tag added successfully');
    }

    /**
     * Detach a tag from an entity.
     */
    protected function entityDetachTag(Model $entity, Tag $tag): mixed
    {
        $this->authorize('update', $entity);

        $entity->removeTag($tag);

        return back()->with('success', 'Tag removed successfully');
    }

    /**
     * Get a display title for the entity (used for download filenames).
     */
    protected function getEntityTitle(Model $entity): ?string
    {
        return $entity->title
            ?? $entity->contract_title
            ?? $entity->invoice_number
            ?? $entity->voucher_name
            ?? $entity->code
            ?? $entity->bank_name
            ?? null;
    }
}
