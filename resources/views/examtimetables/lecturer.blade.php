<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Exam Timetable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        h1, h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Lecturer Exam Timetable</h1>
    <h2>{{ $semester }}</h2>
    <p>Lecturer: {{ $lecturer }}</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Time</th>
                <th>Venue</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($examTimetables as $exam)
                <tr>
                    <td>{{ $exam->date }}</td>
                    <td>{{ $exam->day }}</td>
                    <td>{{ $exam->start_time }} - {{ $exam->end_time }}</td>
                    <td>{{ $exam->venue }}</td>
                    <td>{{ $exam->unit->code }} - {{ $exam->unit->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
