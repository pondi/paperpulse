<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Traits\SanitizesInput;
use Illuminate\Http\Request;

class LineItemController extends Controller
{
    use SanitizesInput;

    public function store(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ]);

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['text', 'sku']);

        // Explicitly add receipt_id to ensure it's set
        $validated['receipt_id'] = $receipt->id;

        $receipt->lineItems()->create($validated);

        return redirect()->back()->with('success', 'Line item added successfully');
    }

    public function update(Request $request, Receipt $receipt, $lineItemId)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ]);

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
