<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\BaseResourceController;
use App\Models\Receipt;
use App\Models\Tag;
use App\Services\DocumentService;
use App\Services\Receipts\ReceiptTransformer;
use App\Services\ReceiptService;
use App\Traits\SanitizesInput;
use App\Traits\ShareableController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends BaseResourceController
{
    use SanitizesInput, ShareableController;

    protected string $model = Receipt::class;

    protected string $resource = 'Receipt';

    protected array $indexWith = ['merchant', 'file', 'lineItems', 'category', 'tags'];

    protected array $showWith = ['merchant', 'file', 'lineItems', 'tags', 'sharedUsers'];

    protected array $searchableFields = ['receipt_description'];

    protected array $filterableFields = ['category_id', 'merchant_id'];

    protected array $validationRules = [
        'receipt_date' => 'required|date',
        'total_amount' => 'required|numeric',
        'tax_amount' => 'nullable|numeric',
        'currency' => 'required|string|size:3',
        'receipt_category' => 'nullable|string|max:255',
        'receipt_description' => 'nullable|string|max:1000',
        'merchant_id' => 'nullable|exists:merchants,id',
        'tags' => 'sometimes|array',
        'tags.*' => 'integer|exists:tags,id',
    ];

    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Override show method to pass correct prop name for Vue component.
     */
    public function show($id): Response
    {
        $receipt = $this->model::with($this->showWith)->findOrFail($id);

        $this->authorize('view', $receipt);

        return Inertia::render("{$this->resource}/Show", [
            'receipt' => ReceiptTransformer::forShow($receipt),
            'meta' => $this->getShowMeta(),
        ]);
    }

    /**
     * Display a listing of receipts with user preferences.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $this->perPage = $user->preference('receipts_per_page', 20);
        $sortOption = $user->preference('default_sort', 'date_desc');

        $query = $this->model::query()->with($this->indexWith);

        // Apply search
        if ($search = $request->input('search')) {
            $query = $this->applySearch($query, $search);
        }

        // Apply filters
        foreach ($this->filterableFields as $field) {
            if ($value = $request->input($field)) {
                $query = $this->applyFilter($query, $field, $value);
            }
        }

        // Apply custom sorting based on user preference
        $this->applySortOption($query, $sortOption);

        $receipts = $query->paginate($this->perPage);

        $categories = auth()->user()->categories()
            ->active()
            ->ordered()
            ->get();

        return inertia("{$this->resource}/Index", [
            'receipts' => $receipts->through(fn ($receipt) => ReceiptTransformer::forIndex($receipt))->items(),
            'categories' => $categories,
            'pagination' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
                'from' => $receipts->firstItem(),
                'to' => $receipts->lastItem(),
            ],
            'user_preferences' => [
                'receipts_per_page' => $this->perPage,
                'default_sort' => $sortOption,
                'receipt_list_view' => $user->preference('receipt_list_view', 'grid'),
            ],
            'filters' => $this->getFilters($request),
        ]);
    }

    /**
     * Transform item for index display.
     */
    protected function transformForIndex($receipt): array
    {
        return ReceiptTransformer::forIndex($receipt);
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow($receipt): array
    {
        return ReceiptTransformer::forShow($receipt);
    }

    /**
     * Prepare data for update.
     */
    protected function prepareForUpdate(array $validated, $receipt): array
    {
        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['receipt_category', 'receipt_description']);

        // Handle tags separately
        if (isset($validated['tags'])) {
            $receipt->tags()->sync($validated['tags']);
            unset($validated['tags']);
        }

        // Clear date update flag if date was updated
        if (isset($validated['receipt_date']) && \App\Services\Receipts\Analysis\DateUpdateNotifier::needsDateUpdate($receipt)) {
            \App\Services\Receipts\Analysis\DateUpdateNotifier::clearDateUpdateFlag($receipt);
        }

        return $validated;
    }

    /**
     * Hook called before destroy.
     */
    protected function beforeDestroy($receipt): void
    {
        $receiptService = app(ReceiptService::class);

        if (! $receiptService->deleteReceipt($receipt)) {
            throw new \Exception('Could not delete the receipt');
        }
    }

    /**
     * Apply sort option to query.
     */
    protected function applySortOption($query, string $sortOption): void
    {
        \App\Services\Receipts\ReceiptSortApplier::apply($query, $sortOption);
    }

    /**
     * Get shareable type for ShareableController trait.
     */
    protected function getShareableType(): string
    {
        return 'receipt';
    }

    /**
     * Get route name prefix.
     */
    protected function getRouteName(): string
    {
        return 'receipts';
    }

    /**
     * Display receipt image.
     */
    public function showImage(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->file || ! $receipt->file->guid) {
            abort(404);
        }

        $extension = $receipt->file->fileExtension ?? 'jpg';

        return redirect()->route('documents.serve', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => $extension,
        ]);
    }

    /**
     * Display receipt PDF.
     */
    public function showPdf(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->file || ! $receipt->file->guid) {
            abort(404);
        }

        $extension = $receipt->file->fileExtension ?? 'jpg';

        if ($extension !== 'pdf') {
            return redirect()->route('receipts.showImage', $receipt->id);
        }

        return redirect()->route('documents.serve', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => 'pdf',
        ]);
    }

    /**
     * Attach tag to receipt.
     */
    public function attachTag(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $tag = $receipt->addTagByName($validated['name']);

        return back()->with('success', 'Tag added successfully');
    }

    /**
     * Detach tag from receipt.
     */
    public function detachTag(Receipt $receipt, Tag $tag)
    {
        $this->authorize('update', $receipt);
        $this->authorize('view', $tag);

        $receipt->removeTag($tag);

        return back()->with('success', 'Tag removed successfully');
    }

    /**
     * Show receipts by merchant.
     */
    public function byMerchant($merchant)
    {
        // Validate merchant ID
        $merchantModel = \App\Models\Merchant::findOrFail($merchant);

        // Verify user has access to this merchant through their receipts
        $hasAccess = Receipt::where('merchant_id', $merchantModel->id)
            ->where('user_id', auth()->id())
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized access to merchant');
        }

        $receipts = Receipt::where('merchant_id', $merchantModel->id)
            ->where('user_id', auth()->id())
            ->with(['merchant', 'file', 'lineItems', 'category', 'tags'])
            ->orderBy('receipt_date', 'desc')
            ->get()
            ->map(function ($receipt) {
                return $this->transformForIndex($receipt);
            });

        return Inertia::render('Receipt/Index', [
            'receipts' => $receipts,
        ]);
    }

    /**
     * Override update method to include custom validation and sanitization.
     */
    public function update(Request $request, $id)
    {
        $receipt = $this->findModel($id);
        $this->authorize('update', $receipt);

        $validated = $request->validate($this->validationRules);

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['receipt_category', 'receipt_description']);

        // Update receipt
        $receipt->update(array_diff_key($validated, ['tags' => '']));

        // Sync tags if provided
        if (isset($validated['tags'])) {
            $receipt->tags()->sync($validated['tags']);
        }

        return redirect()->back()->with('success', 'Receipt updated successfully');
    }

    /**
     * Override destroy method to use ReceiptService.
     */
    public function destroy($id)
    {
        $receipt = $this->findModel($id);
        $this->authorize('delete', $receipt);

        $receiptService = app(ReceiptService::class);

        if ($receiptService->deleteReceipt($receipt)) {
            return redirect()->route('receipts.index')->with('success', 'The receipt was deleted');
        }

        return redirect()->back()->with('error', 'Could not delete the receipt');
    }
}
