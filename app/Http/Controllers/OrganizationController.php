<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationDecisionRequest;
use App\Jobs\Organization\GenerateOrganizationRecommendations;
use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationBackfill;
use App\Models\OrganizationRun;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\FolderTreeService;
use App\Services\OrganizationDecisionService;
use App\Services\OrganizationRevisionService;
use App\Services\OrganizationRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request, OrganizationRevisionService $revisions): Response|JsonResponse
    {
        $userId = $request->user()->id;
        $run = OrganizationRun::query()->where('user_id', $userId)->orderByRaw('active_user_id IS NULL')->latest('id')->first();
        $recommendations = $run?->recommendations()->where('user_id', $userId)->orderBy('id')->paginate(20)->withQueryString();
        $recommendations?->through(function ($recommendation) use ($userId): array {
            $operation = $recommendation->operation;
            $before = $recommendation->before_state;
            $sourceIds = $operation['type'] === 'move' ? array_column($before['files'], 'primary_folder_id') : [$operation['folder_id'] ?? null];
            $current = array_values(array_unique(array_map(fn ($id) => $id ? $this->path($userId, $id) : 'Unfiled', $sourceIds)));
            $proposed = in_array($operation['type'], ['create', 'rename'], true)
                ? trim($this->path($userId, $operation['parent_id'] ?? ($before['folders'][$operation['folder_id'] ?? 0]['parent_id'] ?? null)).' / '.$operation['name'], ' /')
                : $this->path($userId, $operation['target_id']);
            $files = File::query()->where('user_id', $userId)->whereIn('id', $operation['file_ids'])->orderBy('id')->limit(3)->get();

            return ['id' => $recommendation->id, 'status' => $recommendation->status, 'type' => $operation['type'],
                'current_paths' => $current, 'proposed_path' => $proposed, 'affected_count' => $operation['type'] === 'rename'
                    ? ($before['folders'][$operation['folder_id']]['members_count'] ?? 0) : count($operation['file_ids']),
                'reason' => $recommendation->reason, 'confidence' => $recommendation->confidence, 'decision_reason' => $recommendation->decision_reason,
                'undone_at' => $recommendation->undone_at,
                'preview_items' => $files->map(function (File $file): array {
                    $pdf = $file->fileExtension === 'pdf' || $file->s3_archive_path;

                    return ['id' => $file->id, 'type' => $file->file_type, 'title' => $file->fileName,
                        'file' => ['pdfUrl' => $pdf ? route('api.files.content', ['file' => $file->id, 'variant' => $file->fileExtension === 'pdf' ? 'original' : 'archive']) : null,
                            'previewUrl' => $file->has_image_preview ? route('api.files.content', ['file' => $file->id, 'variant' => 'preview']) : null,
                            'url' => route('api.files.content', $file->id)]];
                })->all()];
        });
        $state = $revisions->state($userId);
        $enabled = UserPreference::query()->where('user_id', $userId)->value('auto_organize_documents') !== false;
        $backfilling = OrganizationBackfill::query()->where('active_user_id', $userId)->exists();
        $data = ['backfill_waiting' => $backfilling, 'run' => $run?->only(['id', 'status', 'attempts', 'calls', 'tokens', 'error', 'created_at', 'started_at', 'updated_at', 'completed_at']),
            'recommendations' => $recommendations,
            'pending_count' => $run?->recommendations()->whereIn('status', ['pending', 'conflict'])->count() ?? 0,
            'changes_waiting' => $state->revision > ($run?->input_revision ?? $state->analyzed_revision),
            'can_start' => $enabled && ! $backfilling && ! $run?->active_user_id && $revisions->hasChanges($userId), 'enabled' => $enabled];

        return $request->is('api/*') ? response()->json(['data' => $data]) : Inertia::render('Collections/Recommendations', $data);
    }

    public function start(Request $request, OrganizationRunService $runs): JsonResponse|RedirectResponse
    {
        $runs->start($request->user()->id);

        return $this->respond($request);
    }

    public function decide(OrganizationDecisionRequest $request, OrganizationDecisionService $decisions): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $decisions->decide($request->user()->id, $data['recommendation_ids'], $data['decision'], $data['reason'] ?? null, $data['remove_empty'] ?? false);

        return $this->respond($request);
    }

    public function undo(Request $request, int $recommendation, OrganizationDecisionService $decisions): JsonResponse|RedirectResponse
    {
        $decisions->undo($request->user()->id, $recommendation);

        return $this->respond($request);
    }

    public function retry(Request $request, int $run, OrganizationRunService $runs): JsonResponse|RedirectResponse
    {
        $run = $runs->retry($request->user()->id, $run);
        GenerateOrganizationRecommendations::dispatch($run->user_id, $run->id)->afterCommit();

        return $this->respond($request);
    }

    public function dismiss(Request $request, int $run): JsonResponse|RedirectResponse
    {
        (new OrganizationRun)->getConnection()->transaction(function () use ($request, $run): void {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $run = OrganizationRun::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($run);
            abort_unless($run->status === 'failed', 422);
            $run->recommendations()->whereIn('status', ['pending', 'conflict'])->update(['status' => 'declined', 'decided_at' => now()]);
            $run->update(['status' => 'completed', 'active_user_id' => null, 'completed_at' => now()]);
        });

        return $this->respond($request);
    }

    private function path(int $userId, ?int $id): string
    {
        $folder = $id ? Collection::query()->where('user_id', $userId)->find($id) : null;

        return $folder ? implode(' / ', array_column(app(FolderTreeService::class)->breadcrumbs($folder), 'label')) : '';
    }

    private function respond(Request $request): JsonResponse|RedirectResponse
    {
        return $request->is('api/*') ? response()->json(['success' => true]) : back();
    }
}
