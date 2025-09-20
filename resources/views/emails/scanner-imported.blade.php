@component('mail::message')
# Scanner Files Imported

New files have been imported from your scanner.

@component('mail::panel')
**Files Imported:** {{ $fileCount }}
@endcomponent

These files are now ready for processing.

@component('mail::button', ['url' => route('pulsedav.index')])
View Scanner Imports
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent