<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\WorkspaceActionRequest;
use App\Models\Document;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\Files\FileDeletionService;
use App\Services\Files\FileReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class WorkspaceActionController extends Controller
{
    public function store(WorkspaceActionRequest $request, FileDeletionService $deletion, FileReviewService $review): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;
        $files = File::query()->where('user_id', $userId)->whereKey($data['file_ids'])->orderBy('id')->get();
        abort_unless($files->count() === count($data['file_ids']), 403);
        if ($data['action'] === 'save' && $files->count() !== 1) {
            throw ValidationException::withMessages(['file_ids' => 'Edit one file at a time.']);
        }
        $files->first()->getConnection()->transaction(function () use ($files, $data, $userId, $deletion, $review): void {
            foreach ($files as $source) {
                $file = File::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($source->id);
                $this->authorize($data['action'] === 'delete' ? 'delete' : 'update', $file);
                if ($data['action'] === 'delete') {
                    $deletion->deleteFile($file, $userId);
                    continue;
                }
                if ($data['action'] === 'tag') {
                    \App\Services\Tags\TagAttachmentService::attachTags($file, [$data['tag_id']]);
                    continue;
                }
                if (! in_array($file->status, ['completed', 'needs_review'], true)) {
                    throw ValidationException::withMessages(['files' => 'Wait for processing to finish before editing or reviewing '.$file->fileName.'.']);
                }
                if ($data['action'] === 'save') {
                    $model = $data['entity_type'] === 'receipt' ? Receipt::class : Document::class;
                    $entity = $model::query()->where('user_id', $userId)->where('file_id', $file->id)->findOrFail($data['entity_id']);
                    $this->authorize('update', $entity);
                    if ($entity instanceof Receipt) {
                        $values = array_intersect_key($data, array_flip(['total_amount', 'tax_amount', 'receipt_date', 'currency', 'category_id']));
                        if (array_key_exists('vendor', $data)) {
                            $values['merchant_id'] = filled($data['vendor']) ? Merchant::query()->firstOrCreate(['user_id' => $userId, 'name' => trim($data['vendor'])])->id : null;
                        }
                        $entity->update($values);
                        foreach ($data['line_items'] ?? [] as $item) {
                            $entity->lineItems()->findOrFail($item['id'])->update(array_diff_key($item, ['id' => true]));
                        }
                    } else {
                        $entity->update(array_intersect_key($data, array_flip(['title', 'summary', 'category_id'])));
                    }
                    $meta = $file->meta ?? [];
                    unset($meta['workspace_review']);
                    $file->update(['meta' => $meta]);
                    continue;
                }
                if ($data['action'] === 'approve' && $file->status === 'needs_review') {
                    $review->resolveTotals($file, $userId);
                    $file->refresh();
                }
                $meta = $file->meta ?? [];
                $meta['workspace_review'] = ['status' => $data['action'] === 'approve' ? 'approved' : 'flagged',
                    'reviewed_by' => $userId, 'reviewed_at' => now()->toIso8601String()];
                $file->update(['meta' => $meta]);
            }
        });

        return back()->with('success', match ($data['action']) {
            'save' => 'Changes saved.', 'approve' => 'Selected files approved.',
            'flag' => 'Selected files flagged.', 'tag' => 'Tag applied.', 'delete' => 'Selected files deleted.',
        });
    }
}
