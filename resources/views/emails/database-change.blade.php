@component('mail::message')
# {{ $subject }}

{{ $greeting }}

{{ $message }}

@if(!empty($additional_lines))
@foreach($additional_lines as $line)
{{ $line }}
@endforeach
@endif

@if(!empty($action_url))
@component('mail::button', ['url' => $action_url])
{{ $action_text }}
@endcomponent
@endif


