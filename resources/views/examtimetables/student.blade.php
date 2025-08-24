<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Student Exam Timetable' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #2c3e50;
        }
        .header h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: #34495e;
        }
        .student-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .student-info p {
            margin: 5px 0;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .no-exams {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            font-style: italic;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            border-top: 1px solid #bdc3c7;
            padding-top: 15px;
        }
        .exam-count {
            text-align: right;
            margin-bottom: 10px;
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>STRATHMORE UNIVERSITY</h1>
        <h2>Personal Exam Timetable</h2>
        @if(isset($currentSemester) && $currentSemester)
            <h2>{{ $currentSemester->name }}</h2>
        @else
            <h2>All Semesters</h2>
        @endif
    </div>
    
    <div class="student-info">
        <p><strong>Student:</strong> {{ $studentName ?: 'N/A' }}</p>
        <p><strong>Student ID:</strong> {{ $studentId }}</p>
        <p><strong>Generated:</strong> {{ $generatedAt }}</p>
    </div>

    @if($examTimetables && $examTimetables->isNotEmpty())
        <div class="exam-count">
            Total Exams: {{ $examTimetables->count() }}
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Date</th>
                    <th style="width: 10%;">Day</th>
                    <th style="width: 15%;">Time</th>
                    <th style="width: 25%;">Unit</th>
                    <th style="width: 15%;">Venue</th>
                    <th style="width: 13%;">Location</th>
                    <th style="width: 10%;">Invigilator</th>
                </tr>
            </thead>
            <tbody>
                @foreach($examTimetables as $exam)
                    <tr>
                        <td><strong>{{ \Carbon\Carbon::parse($exam->date)->format('M j, Y') }}</strong></td>
                        <td>{{ $exam->day ?? 'N/A' }}</td>
                        <td>{{ ($exam->start_time ?? 'N/A') . ' - ' . ($exam->end_time ?? 'N/A') }}</td>
                        <td>{{ ($exam->unit_code ?? 'N/A') . ' - ' . ($exam->unit_name ?? 'N/A') }}</td>
                        <td>{{ $exam->venue ?: 'TBA' }}</td>
                        <td>{{ $exam->location ?: 'TBA' }}</td>
                        <td>{{ $exam->chief_invigilator ?: 'TBA' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-exams">
            <p>No exams scheduled for this semester.</p>
            <p>Please contact your academic advisor if you believe this is an error.</p>
        </div>
    @endif

    <div class="footer">
        <p>This is an official exam timetable. Please verify all details and contact the registrar's office for any discrepancies.</p>
        <p>Generated on {{ $generatedAt }} | For academic use only</p>
    </div>
</body>
</html>