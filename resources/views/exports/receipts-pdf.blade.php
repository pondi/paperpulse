@php use Carbon\Carbon; @endphp
        <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipts Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }

        .summary-item {
            display: inline-block;
            margin-right: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #333;
            color: white;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #666;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>Receipts Export</h1>
    <p>Generated on {{ $generated_at->format('F d, Y h:i A') }}</p>
    @if($from_date || $to_date)
        <p>Period: {{ $from_date ?: 'Start' }} - {{ $to_date ?: 'End' }}</p>
    @endif
</div>

<div class="summary">
    <div class="summary-item">
        <strong>Total Receipts:</strong> {{ $total_count }}
    </div>
    <div class="summary-item">
        <strong>Total
            Amount:</strong> {{ number_format($total_amount, 2) }} {{ auth()->user()->preference('currency', 'NOK') }}
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>Date</th>
        <th>Merchant</th>
        <th>Category</th>
        <th>Description</th>
        <th>Note</th>
        <th>Total</th>
        <th>Tax</th>
        <th>Items</th>
    </tr>
    </thead>
    <tbody>
    @foreach($receipts as $receipt)
        <tr>
            <td>{{ $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : '-' }}</td>
            <td>{{ $receipt->merchant?->name ?: 'Unknown' }}</td>
            <td>{{ $receipt->receipt_category ?: '-' }}</td>
            <td>{{ Str::limit($receipt->receipt_description ?: '-', 30) }}</td>
            <td>{{ Str::limit($receipt->note ?: '-', 30) }}</td>
            <td>{{ number_format($receipt->total_amount ?: 0, 2) }} {{ $receipt->currency }}</td>
            <td>{{ number_format($receipt->tax_amount ?: 0, 2) }}</td>
            <td>{{ $receipt->lineItems->count() }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="footer">
    <p>PaperPulse Receipt Management System</p>
</div>
</body>
</html>
