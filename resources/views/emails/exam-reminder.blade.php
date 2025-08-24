<!-- resources/views/emails/exam-reminder.blade.php -->
@component('mail::message')
# {{ $subject }}

{{ $greeting }}

{{ $message }}

@component('mail::panel')
## Exam Details

**Unit:** {{ $exam_details['unit'] ?? 'N/A' }}  
**Date:** {{ $exam_details['date'] ?? 'N/A' }} {{ isset($exam_details['day']) ? '('.$exam_details['day'].')' : '' }}  
**Time:** {{ $exam_details['time'] ?? 'N/A' }}  
**Venue:** {{ $exam_details['venue'] ?? 'N/A' }}
@endcomponent

<!-- {{ $closing }}

@if($url)
@component('mail::button', ['url' => $url])
View Exam Timetable
@endcomponent
@endif -->

