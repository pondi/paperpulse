<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\BaseResourceController;
use App\Models\Receipt;
use App\Models\Tag;
use App\Services\DocumentService;
use App\Services\ReceiptService;
use App\Traits\ShareableController;
use App\Traits\SanitizesInput;
use Illuminate\Http\Request;

class ReceiptResourceController extends BaseResourceController
{
    use ShareableController, SanitizesInput;

    protected string $model = Receipt::class;
    protected string $resource = 'Receipt';

    protected array $indexWith = ['merchant', 'file', 'lineItems', 'category', 'tags'];
    protected array $showWith = ['merchant', 'file', 'lineItems', 'tags'];

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
        parent::__construct();
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of receipts with user preferences.
     */
    public function index(Request $request)
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
            'receipts' => $receipts->through(fn($receipt) => $this->transformForIndex($receipt)),
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
        return [
            'id' => $receipt->id,
            'merchant' => $receipt->merchant,
            'category' => $receipt->category,
            'category_id' => $receipt->category_id,
            'receipt_date' => $receipt->receipt_date,
            'tax_amount' => $receipt->tax_amount,
            'total_amount' => $receipt->total_amount,
            'currency' => $receipt->currency,
            'receipt_category' => $receipt->receipt_category,
            'receipt_description' => $receipt->receipt_description,
            'file' => $receipt->file ? [
                'id' => $receipt->file->id,
                'url' => route('receipts.showImage', $receipt->id),
                'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                'extension' => $receipt->file->fileExtension ?? 'jpg',
                'mime_type' => $receipt->file->mime_type,
            ] : null,
            'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->text,
                    'sku' => $item->sku,
                    'quantity' => $item->qty,
                    'unit_price' => $item->price,
                    'total_amount' => $item->total,
                ];
            }) : [],
            'tags' => $receipt->tags ? $receipt->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            }) : [],
        ];
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow($receipt): array
    {
        return [
            'id' => $receipt->id,
            'merchant' => $receipt->merchant,
            'receipt_date' => $receipt->receipt_date,
            'tax_amount' => $receipt->tax_amount,
            'total_amount' => $receipt->total_amount,
            'currency' => $receipt->currency,
            'receipt_category' => $receipt->receipt_category,
            'receipt_description' => $receipt->receipt_description,
            'file' => $receipt->file ? [
                'id' => $receipt->file->id,
                'url' => route('receipts.showImage', $receipt->id),
                'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                'extension' => $receipt->file->fileExtension ?? 'jpg',
                'mime_type' => $receipt->file->mime_type,
            ] : null,
            'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->text,
                    'sku' => $item->sku,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'total' => $item->total,
                ];
            }) : [],
            'tags' => $receipt->tags ? $receipt->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            }) : [],
        ];
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

        return $validated;
    }

    /**
     * Hook called before destroy.
     */
    protected function beforeDestroy($receipt): void
    {
        $receiptService = app(ReceiptService::class);
        
        if (!$receiptService->deleteReceipt($receipt)) {
            throw new \Exception('Could not delete the receipt');
        }
    }

    /**
     * Apply sort option to query.
     */
    protected function applySortOption($query, string $sortOption): void
    {
        switch ($sortOption) {
            case 'date_asc':
                $query->orderBy('receipt_date', 'asc');
                break;
            case 'amount_desc':
                $query->orderBy('total_amount', 'desc');
                break;
            case 'amount_asc':
                $query->orderBy('total_amount', 'asc');
                break;
            case 'merchant_asc':
                $query->leftJoin('merchants', 'receipts.merchant_id', '=', 'merchants.id')
                    ->orderBy('merchants.name', 'asc')
                    ->select('receipts.*');
                break;
            case 'merchant_desc':
                $query->leftJoin('merchants', 'receipts.merchant_id', '=', 'merchants.id')
                    ->orderBy('merchants.name', 'desc')
                    ->select('receipts.*');
                break;
            case 'date_desc':
            default:
                $query->orderBy('receipt_date', 'desc');
                break;
        }
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

        if (!$receipt->file || !$receipt->file->guid) {
            abort(404);
        }

        $extension = $receipt->file->fileExtension ?? 'jpg';

        return redirect()->route('documents.serve', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => $extension,
            'user_id' => $receipt->user_id,
        ]);
    }

    /**
     * Display receipt PDF.
     */
    public function showPdf(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (!$receipt->file || !$receipt->file->guid) {
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
            'user_id' => $receipt->user_id,
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
}