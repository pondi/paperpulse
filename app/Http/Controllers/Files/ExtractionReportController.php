<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FileExtractionReportResource;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExtractionReportController extends BaseApiController
{
    public function show(Request $request, int $file): JsonResponse
    {
        return $this->success($this->report($request, $file), 'Extraction report retrieved successfully');
    }

    public function page(Request $request, int $file): Response
    {
        return Inertia::render('Files/ExtractionReport', ['report' => $this->report($request, $file)->toArray($request)]);
    }

    private function report(Request $request, int $file): FileExtractionReportResource
    {
        $ownedFile = File::query()->where('user_id', $request->user()->id)
            ->with(['extractableEntities' => fn ($query) => $query->where('user_id', $request->user()->id)->orderByDesc('is_primary')->orderBy('id')])
            ->findOrFail($file);

        return new FileExtractionReportResource($ownedFile);
    }
}
