<?php

namespace App\Http\Controllers;

use App\Services\BatchProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BatchProcessingController extends Controller
{
    protected BatchProcessingService $batchService;

    public function __construct(BatchProcessingService $batchService)
    {
        $this->batchService = $batchService;
    }

    /**
     * Start a new batch processing job
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1|max:1000',
            'items.*.source' => 'required|string',
            'items.*.type' => 'sometimes|string',
            'items.*.options' => 'sometimes|array',
            'type' => 'required|in:receipt,document',
            'options' => 'sometimes|array',
            'options.quality' => 'sometimes|in:basic,standard,high,premium',
            'options.budget' => 'sometimes|in:economy,standard,premium,unlimited',
            'options.batch_size' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $items = $request->input('items');
            $type = $request->input('type');
            $options = $request->input('options', []);

            $batchJob = $this->batchService->processBatch($items, $user, $type, $options);

            return response()->json([
                'success' => true,
                'batch_job' => [
                    'id' => $batchJob->id,
                    'status' => $batchJob->status,
                    'total_items' => $batchJob->total_items,
                    'estimated_cost' => $batchJob->estimated_cost,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get batch job status
     */
    public function status(int $batchJobId): JsonResponse
    {
        try {
            $status = $this->batchService->getBatchStatus($batchJobId);

            return response()->json([
                'success' => true,
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Cancel a batch job
     */
    public function cancel(int $batchJobId): JsonResponse
    {
        try {
            $cancelled = $this->batchService->cancelBatch($batchJobId);

            return response()->json([
                'success' => $cancelled,
                'message' => $cancelled ? 'Batch cancelled successfully' : 'Batch could not be cancelled',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List user's batch jobs
     */
    public function list(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = $user->batchJobs()
            ->recent($request->input('days', 30))
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        $batchJobs = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'batch_jobs' => $batchJobs,
        ]);
    }
}
