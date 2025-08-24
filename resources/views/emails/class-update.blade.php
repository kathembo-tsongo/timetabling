<!-- resources/views/emails/class-update.blade.php -->
@component('mail::message')
# {{ $subject }}

{{ $greeting }}

{{ $message }}

@component('mail::panel')
## Class Details

**Unit:** {{ $class_details['unit'] }}  
**Day:** {{ $class_details['day'] }}  
**Time:** {{ $class_details['time'] }}  
**Venue:** {{ $class_details['venue'] }}
@endcomponent

@if(!empty($changes))
## Changes Made:

@foreach($changes as $field => $values)
- **{{ ucfirst($field) }}**: Changed from "{{ $values['old'] }}" to "{{ $values['new'] }}"
@endforeach
@endif

<!-- {{ $closing }} -->

<!-- @component('mail::button', ['url' => $url])
View Updated Timetable
@endcomponent -->

<!-- Regards,<br>
Timetabling System Management Office
@endcomponent -->