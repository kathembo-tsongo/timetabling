<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #0047AB;
            color: white;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
        }
        .header p {
            font-size: 12px;
            margin: 5px 0 0;
        }
        .auto-generated-badge {
            background-color: #10B981;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 10px;
            margin-top: 5px;
            display: inline-block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
            font-size: 9px;
        }
        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Strathmore University</h1>
        <p>{{ $title }}</p>
        <span class="auto-generated-badge">AUTO-GENERATED</span>
        <p>Generated at: {{ $generatedAt }}</p>
    </div>
    
    @if (count($classTimetables) === 0)
        <p>No auto-generated class timetables available.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Unit Code</th>
                    <th>Unit Name</th>
                    <th>Semester</th>
                    <th>Class</th>
                    <th>Group</th>
                    <th>Time</th>
                    <th>Venue</th>
                    <th>Lecturer</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($classTimetables as $timetable)
                    <tr>
                        <td>{{ $timetable['day'] }}</td>
                        <td>{{ $timetable['unit_code'] }}</td>
                        <td>{{ $timetable['unit_name'] }}</td>
                        <td>{{ $timetable['semester_name'] }}</td>
                        <td>{{ $timetable['class_name'] }}</td>
                        <td>{{ $timetable['group_name'] }}</td>
                        <td>{{ $timetable['start_time'] }} - {{ $timetable['end_time'] }}</td>
                        <td>{{ $timetable['venue'] }} ({{ $timetable['location'] }})</td>
                        <td>{{ $timetable['lecturer'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <p>This is an automatically generated timetable. Please keep it for your records.</p>
    </div>
</body>
</html>
