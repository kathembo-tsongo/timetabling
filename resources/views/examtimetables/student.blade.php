<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Exam Timetable' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
        }

        .logo-container {
            margin-bottom: 10px;
        }

        .logo {
            max-height: 60px;
            width: auto;
        }

        .university-name {
            font-size: 20pt;
            font-weight: bold;
            color: #1a252f;
            margin: 8px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .document-title {
            font-size: 16pt;
            font-weight: 600;
            color: #34495e;
            margin: 5px 0;
            text-transform: uppercase;
        }

        .student-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .info-value {
            color: #212529;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 9pt;
        }

        thead {
            background: #2c3e50;
            color: white;
        }

        th {
            padding: 10px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 9pt;
            border: 1px solid #1a252f;
        }

        td {
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tbody tr:hover {
            background-color: #e9ecef;
        }

        .unit-code {
            font-weight: 600;
            color: #2c3e50;
        }

        .unit-name {
            color: #495057;
            font-size: 8.5pt;
        }

        .section-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: 600;
            margin-left: 5px;
        }

        .venue {
            font-weight: 500;
            color: #e74c3c;
        }

        .time {
            color: #27ae60;
            font-weight: 500;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            font-size: 8pt;
            color: #6c757d;
        }

        .generated-date {
            margin-top: 5px;
            font-style: italic;
        }

        .no-exams {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        .page-break {
            page-break-after: always;
        }

        /* Print-specific styles */
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        @if(!empty($logoBase64))
        <div class="logo-container">
            <img src="{{ $logoBase64 }}" alt="University Logo" class="logo">
        </div>
        @endif
        <div class="university-name">Strathmore University</div>
        <div class="document-title">{{ $title ?? 'Examination Timetable' }}</div>
    </div>

    <!-- Student Information -->
    @if(isset($student))
    <div class="student-info">
        <div class="info-row">
            <span class="info-label">Student Name:</span>
            <span class="info-value">{{ $studentName ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Student ID:</span>
            <span class="info-value">{{ $studentId ?? 'N/A' }}</span>
        </div>
        @if(isset($currentSemester))
        <div class="info-row">
            <span class="info-label">Semester:</span>
            <span class="info-value">{{ $currentSemester->name ?? 'N/A' }}</span>
        </div>
        @endif
    </div>
    @endif

    <!-- Exam Timetable Table -->
    @if(isset($examTimetables) && count($examTimetables) > 0)
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Date</th>
                <th style="width: 8%;">Day</th>
                <th style="width: 10%;">Time</th>
                <th style="width: 25%;">Unit</th>
                <th style="width: 15%;">Class/Section</th>
                <th style="width: 17%;">Venue</th>
                <th style="width: 15%;">Invigilator</th>
            </tr>
        </thead>
        <tbody>
            @foreach($examTimetables as $exam)
            <tr>
                <td>{{ \Carbon\Carbon::parse($exam->date)->format('d M Y') }}</td>
                <td>{{ $exam->day }}</td>
                <td class="time">
                    {{ \Carbon\Carbon::parse($exam->start_time)->format('H:i') }} - 
                    {{ \Carbon\Carbon::parse($exam->end_time)->format('H:i') }}
                </td>
                <td>
                    <div class="unit-code">{{ $exam->unit_code }}</div>
                    <div class="unit-name">{{ $exam->unit_name }}</div>
                </td>
                <td>
                    {{ $exam->class_name ?? 'N/A' }}
                    @if(!empty($exam->group_name) || !empty($exam->class_section))
                    <span class="section-badge">
                        Sec {{ $exam->group_name ?? $exam->class_section }}
                    </span>
                    @endif
                </td>
                <td class="venue">
                    {{ $exam->venue }}
                    @if(!empty($exam->location))
                    <br><small style="color: #6c757d;">{{ $exam->location }}</small>
                    @endif
                </td>
                <td>{{ $exam->chief_invigilator }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-exams">
        <p>No exam timetable entries found.</p>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div>
            <strong>Important Notes:</strong><br>
            Students must arrive 15 minutes before the exam start time.<br>
            Valid student ID is required for entry.<br>
            Mobile phones and electronic devices are strictly prohibited.
        </div>
        <div class="generated-date">
            Generated on: {{ $generatedAt ?? now()->format('F j, Y \a\t g:i A') }}
        </div>
    </div>
</body>
</html>