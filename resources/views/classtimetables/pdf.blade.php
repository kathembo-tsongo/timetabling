
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>

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
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            background-color: #f0f8ff;
            color: #333;
        }
        .container {
            margin: 20px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid #0047AB;
            padding-bottom: 10px;
            margin-bottom: 20px;
            background-color: #0047AB;
            color: #ffffff;
            border-radius: 10px 10px 0 0;
            padding: 15px;
        }
        .header .logo {
            width: 80px;
            height: auto;
        }
        .header .title {
            text-align: center;
            flex-grow: 1;
        }
        .header .title h1 {
            font-size: 24px;
            margin: 0;
            font-weight: bold;
        }
        .header .title p {
            font-size: 14px;
            margin: 0;
        }
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #ffcccb;
            font-weight: bold;
            color: #333;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
            padding: 10px;
            background-color: #0047AB;
            color: #ffffff;
            border-radius: 0 0 10px 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ public_path('images/strathmore.png') }}" alt="Strathmore University Logo" class="logo">
            <div class="title">
                <h1>Strathmore University</h1>
                <p>{{ $title }}</p>
            </div>
        </div>

        <div class="header-info">
            <p>Generated at: {{ $generatedAt }}</p>
        </div>

       

        <table>
            <thead>
                <tr>
                    <th class="col-day">Day</th>
                    <th class="col-time">Time</th>
                    <th class="col-unit">Unit Code</th>
                    <th class="col-unit-name">Unit Name</th>
                    <th class="col-class">Class/Group</th>
                    <th class="col-mode">Mode of Teaching</th>
                    <th class="col-venue">Venue</th>                    
                    <th class="col-lecturer">Lecturer</th>
                    <!-- <th class="col-students">Students</th>
                    <th class="col-status">Status</th> -->
                </tr>
            </thead>
            <tbody>
                @forelse($sortedClassTimetables as $class)
                <tr>
                    <td>{{ $class->day ?? 'N/A' }}</td>
                    <td>
                        @if($class->start_time && $class->end_time)
                            {{ \Carbon\Carbon::parse($class->start_time)->format('H:i') }} - 
                            {{ \Carbon\Carbon::parse($class->end_time)->format('H:i') }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ $class->unit_code ?? 'N/A' }}</td>
                    <td>{{ $class->unit_name ?? 'N/A' }}</td>
                    <td>
                        @if(isset($class->class_name))
                            {{ $class->class_name }}
                            @if(isset($class->class_section))
                                - Section {{ $class->class_section }}
                            @endif
                            @if(isset($class->class_year_level))
                                (Year {{ $class->class_year_level }})
                            @endif
                        @elseif(isset($class->group_name))
                            {{ $class->group_name }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ ucfirst($class->teaching_mode ?? 'N/A') }}</td>
                    <td>{{ $class->venue ?? 'N/A' }}</td>                    
                    <td>{{ $class->lecturer ?? 'N/A' }}</td>
                    <!-- <td>{{ $class->no ?? 'N/A' }}</td>
                    <td>{{ $class->status ?? 'Active' }}</td> -->
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="no-data">No class timetables available for the selected criteria</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($sortedClassTimetables->isNotEmpty())
        <div style="margin-top: 15px; font-size: 9px; color: #666;">
            <strong>Summary:</strong> Total Classes: {{ $sortedClassTimetables->count() }} | 
            Total Students: {{ $sortedClassTimetables->sum('no') }}
        </div>
        @endif

        <div class="footer">
            <p>This is an official document generated by the Timetabling System.</p>
            <p>For any discrepancies, please contact the Academic Office.</p>
        </div>
    </div>
</body>
</html>