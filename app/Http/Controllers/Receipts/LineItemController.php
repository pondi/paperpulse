<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLineItemRequest;
use App\Models\Receipt;
use App\Traits\SanitizesInput;

class LineItemController extends Controller
{
    use SanitizesInput;

    public function store(StoreLineItemRequest $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validated();

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['text', 'sku']);

        // Explicitly add receipt_id to ensure it's set
        $validated['receipt_id'] = $receipt->id;

        $receipt->lineItems()->create($validated);

        return redirect()->back()->with('success', 'Line item added successfully');
    }

    public function update(StoreLineItemRequest $request, Receipt $receipt, $lineItemId)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validated();

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['text', 'sku']);

        $lineItem = $receipt->lineItems()->findOrFail($lineItemId);
        $lineItem->update($validated);

        return redirect()->back()->with('success', 'Line item updated successfully');
    }

    public function destroy(Receipt $receipt, $lineItemId)
    {
        $this->authorize('update', $receipt);

        $lineItem = $receipt->lineItems()->findOrFail($lineItemId);
        $lineItem->delete();

        return redirect()->back()->with('success', 'Line item deleted successfully');
    }
}
