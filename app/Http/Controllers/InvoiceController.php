<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesEntityCrud;
use App\Http\Resources\Inertia\InvoiceInertiaResource;
use App\Models\Invoice;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends BaseResourceController
{
    use HandlesEntityCrud;

    protected string $model = Invoice::class;

    protected string $resource = 'Invoices';

    protected array $indexWith = ['merchant'];

    protected array $showWith = ['merchant', 'file', 'tags', 'lineItems'];

    protected array $searchableFields = ['invoice_number', 'from_name', 'to_name'];

    protected string $defaultSort = 'invoice_date';

    protected array $validationRules = [
        'invoice_number' => 'sometimes|string|max:255',
        'from_name' => 'sometimes|string|max:255',
        'to_name' => 'sometimes|string|max:255',
        'invoice_date' => 'sometimes|date',
        'due_date' => 'nullable|date',
        'total_amount' => 'sometimes|numeric|min:0',
        'payment_status' => 'sometimes|string|max:50',
        'notes' => 'nullable|string|max:2000',
    ];

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): Response
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with($this->indexWith)
            ->withCount('lineItems')
            ->orderBy($this->defaultSort, $this->defaultSortDirection)
            ->get()
            ->map(fn (Invoice $invoice) => InvoiceInertiaResource::forIndex($invoice)->toArray(request()));

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display the specified invoice.
     */
    public function show($id): Response
    {
        $invoice = Invoice::with($this->showWith)->findOrFail($id);

        $this->authorize('view', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceInertiaResource::forShow($invoice)->toArray(request()),
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Invoices', 'href' => route('invoices.index')],
                ['label' => 'Invoice #'.$invoice->invoice_number],
            ],
        ]);
    }

    /**
     * Transform item for index display.
     */
    protected function transformForIndex(Model $item): array
    {
        return InvoiceInertiaResource::forIndex($item)->toArray(request());
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow(Model $item): array
    {
        return InvoiceInertiaResource::forShow($item)->toArray(request());
    }

    public function download(Invoice $invoice): mixed
    {
        return $this->entityDownload($invoice);
    }

    public function destroy($id): mixed
    {
        $invoice = $id instanceof Invoice
            ? $id
            : Invoice::findOrFail($id);

        return $this->entityDestroy($invoice);
    }

    public function attachTag(Request $request, Invoice $invoice): mixed
    {
        return $this->entityAttachTag($request, $invoice);
    }

    public function detachTag(Invoice $invoice, Tag $tag): mixed
    {
        return $this->entityDetachTag($invoice, $tag);
    }

    protected function getRouteName(): string
    {
        return 'invoices';
    }
}
