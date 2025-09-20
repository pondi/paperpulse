@component('mail::message')
# Bulk Operation Completed

Your bulk operation has been completed successfully.

@component('mail::panel')
**Operation:** {{ ucfirst($operation) }}  
**Items Processed:** {{ $count }}
@endcomponent

@component('mail::button', ['url' => route('receipts.index')])
View Receipts
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent