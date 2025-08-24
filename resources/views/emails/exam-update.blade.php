@component('mail::message')
# {{ $subject }}

{{ $greeting }}

{{ $message }}

@if(!empty($exam_details))
## Exam Details
{!! nl2br(e($exam_details)) !!}
@endif

@if(!empty($changes))
## Changes Made
{!! nl2br(e($changes)) !!}
@endif

{{ $closing }}

<!-- @component('mail::button', ['url' => $url])
{{ isset($is_lecturer) && $is_lecturer ? 'View Exam Schedule' : 'View Updated Exam Schedule' }}
@endcomponent -->

{{ __('Regards,') }}<br>
{{ __('Examination Office') }}
@endcomponent
