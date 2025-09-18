<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'My Class Timetable' }}</title>

@php
    // Define the order of days
    $daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // Sort the classTimetables collection by day and then start_time
    $sortedClassTimetables = collect($classTimetables)->sort(function($a, $b) use ($daysOrder) {
        $dayA = array_search($a->day, $daysOrder);
        $dayB = array_search($b->day, $daysOrder);
        if ($dayA === $dayB) {
            return strcmp($a->start_time, $b->start_time);
        }
        return $dayA <=> $dayB;
    });
@endphp

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            color: #333;
        }
        .container {
            margin: 15px;
            padding: 20px;
            background-color: #ffffff;
        }
        .header {
            border-bottom: 3px solid #0047AB;
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        .header .logo {
            width: 60px;
            height: auto;
            float: left;
            margin-right: 20px;
        }
        .header .title {
            text-align: center;
        }
        .header .title h1 {
            font-size: 50px;
            margin: 0 0 5px 0;
            font-weight: bold;
            color: #0047AB;
        }
        .header .title p {
            font-size: 40px;
            margin: 0;
            color: #666;
        }
        .student-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 30px solid #0047AB;
        }
        .student-info h3 {
            margin: 0 0 10px 0;
            color: #0047AB;
            font-size: 25px;
        }
        .student-info p {
            margin: 5px 0;
            font-size: 20px;
            color: #555;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: #ffffff;
            font-size: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #0047AB;
            color: white;
            font-weight: bold;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 9px;
            color: #666;
            padding: 15px;
            border-top: 1px solid #ddd;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        /* Mode badges */
        .mode-physical {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 15px;
        }
        .mode-online {
            background-color: #cce5ff;
            color: #004085;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 15px;
        }
        /* Column widths */
        .col-day { width: 10%; }
        .col-time { width: 12%; }
        .col-unit { width: 10%; }
        .col-unit-name { width: 25%; }
        .col-class { width: 15%; }
        .col-venue { width: 12%; }
        .col-mode { width: 8%; }
        .col-lecturer { width: 8%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header clearfix">
            {{-- Remove or comment out the logo line if the image doesn't exist --}}
            {{-- <img src="{{ public_path('images/strathmore.png') }}" alt="University Logo" class="logo"> --}}
            <div class="title">
                <h1>University Timetable System</h1>
                <p>{{ $title ?? 'My Class Timetable' }}</p>
            </div>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <h3>Student Information</h3>
            <p><strong>Name:</strong> {{ $student['name'] ?? 'N/A' }}</p>
            <p><strong>Student ID:</strong> {{ $student['code'] ?? 'N/A' }}</p>
            <p><strong>Semester:</strong> {{ $semester['name'] ?? 'N/A' }}</p>
            <p><strong>Generated:</strong> {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</p>
        </div>

         <table>
            <thead>
                <tr>
                    <th class="col-day">Day</th>
                    <th class="col-time">Time</th>
                    <th class="col-unit">Unit Code</th>
                    <th class="col-unit-name">Unit Name</th>
                    <th class="col-class">Class/Section</th>
                    <th class="col-venue">Venue</th>
                    <th class="col-mode">Mode</th>
                    <th class="col-lecturer">Lecturer</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sortedClassTimetables as $class)
                <tr>
                    <td class="day-cell">{{ $class->day ?? 'N/A' }}</td>
                    <td class="time-cell">
                        @if($class->start_time && $class->end_time)
                            {{ \Carbon\Carbon::parse($class->start_time)->format('H:i') }} - 
                            {{ \Carbon\Carbon::parse($class->end_time)->format('H:i') }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td><span class="unit-code">{{ $class->unit_code ?? 'N/A' }}</span></td>
                    <td>{{ $class->unit_name ?? 'N/A' }}</td>
                    <td>
                        @if(isset($class->class_name))
                            {{ $class->class_name }}
                            @if(isset($class->class_section))
                                - Section {{ $class->class_section }}
                            @endif
                        @elseif(isset($class->group_name))
                            {{ $class->group_name }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ $class->venue ?? 'N/A' }}</td>
                    <td>
                        <span class="mode-{{ $class->teaching_mode === 'online' ? 'online' : 'physical' }}">
                            {{ $class->teaching_mode === 'online' ? 'ONLINE' : 'PHYSICAL' }}
                        </span>
                    </td>
                    <td>{{ $class->lecturer_name ?? $class->lecturer ?? 'N/A' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="no-data">No classes found for your enrollment in this semester</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            <p><strong>This is your personalized class timetable.</strong></p>
            <p>Only showing classes for units you are enrolled in for {{ $semester['name'] ?? 'the selected semester' }}.</p>
            <p>For any discrepancies or missing classes, please contact the Academic Office.</p>
        </div>
    </div>
</body>
</html>