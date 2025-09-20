@component('mail::message')
# Receipt Processing Failed

Unfortunately, we encountered an error while processing your receipt.

@component('mail::panel')
**Error:** {{ $errorMessage ?? 'An unexpected error occurred during processing.' }}
@endcomponent

Please try uploading the receipt again or contact support if the issue persists.

@component('mail::button', ['url' => route('documents.upload')])
Upload Again
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent