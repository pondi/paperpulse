@component('mail::message')
# Receipt Processed Successfully

Your receipt has been processed successfully.

@component('mail::panel')
**Merchant:** {{ $receipt->merchant->name ?? 'Unknown' }}  
**Date:** {{ $receipt->receipt_date ? \Carbon\Carbon::parse($receipt->receipt_date)->format('F j, Y') : 'No date' }}  
**Total:** {{ number_format($receipt->total_amount ?? 0, 2) }} {{ $receipt->currency ?? $receipt->user->preference('currency', 'NOK') }}  
@if($receipt->receipt_category)
**Category:** {{ $receipt->receipt_category }}
@endif
@endcomponent

@component('mail::button', ['url' => route('receipts.show', $receipt->id)])
View Receipt
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent