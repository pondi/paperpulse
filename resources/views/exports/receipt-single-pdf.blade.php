<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $receipt->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            color: #333;
        }
        .merchant-info {
            margin-bottom: 30px;
        }
        .merchant-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        .info-value {
            flex: 1;
        }
        .line-items {
            margin-top: 30px;
        }
        .line-items h2 {
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #333;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Receipt Details</h1>
        <p>Receipt ID: #{{ $receipt->id }}</p>
    </div>

    <div class="merchant-info">
        <div class="merchant-name">{{ $receipt->merchant?->name ?: 'Unknown Merchant' }}</div>
        @if($receipt->merchant?->address)
            <div>{{ $receipt->merchant->address }}</div>
        @endif
        @if($receipt->merchant?->vat_id)
            <div>VAT: {{ $receipt->merchant->vat_id }}</div>
        @endif
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Date:</div>
            <div class="info-value">
                {{ $receipt->receipt_date ? \Carbon\Carbon::parse($receipt->receipt_date)->format('F d, Y') : 'N/A' }}
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Category:</div>
            <div class="info-value">{{ $receipt->receipt_category ?: 'Uncategorized' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Description:</div>
            <div class="info-value">{{ $receipt->receipt_description ?: 'No description' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Currency:</div>
            <div class="info-value">{{ $receipt->currency ?: $receipt->user->preference('currency', 'NOK') }}</div>
        </div>
    </div>

    @if($receipt->lineItems->count() > 0)
        <div class="line-items">
            <h2>Line Items</h2>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>SKU</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipt->lineItems as $item)
                        <tr>
                            <td>{{ $item->text }}</td>
                            <td>{{ $item->sku ?: '-' }}</td>
                            <td>{{ $item->qty }}</td>
                            <td>{{ number_format($item->price, 2) }}</td>
                            <td>{{ number_format($item->qty * $item->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">Subtotal:</td>
                        <td>{{ number_format($receipt->total_amount - $receipt->tax_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;">Tax:</td>
                        <td>{{ number_format($receipt->tax_amount ?: 0, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">Total:</td>
                        <td>{{ number_format($receipt->total_amount ?: 0, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <div class="footer">
        <p>Generated on {{ $generated_at->format('F d, Y h:i A') }}</p>
        <p>PaperPulse Receipt Management System</p>
    </div>
</body>
</html>