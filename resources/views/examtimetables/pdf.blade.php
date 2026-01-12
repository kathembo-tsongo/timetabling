<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11px;
            background: #f5f5f5;
            padding: 15px;
        }

        .container {
            max-width: 100%;
            background: white;
            padding: 20px;
        }

        /* University Header - Improved Layout */
        .university-header {
            border: 3px solid #000;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
        }

        .header-content {
            display: flex;
            align-items: flex-start;
            gap: 30px;
        }

        .logo-container {
            flex-shrink: 0;
            width: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .logo-circle {
            width: 120px;
            height: 120px;
            border: 4px solid #FFD700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
        }

        .logo-circle img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .logo-circle svg {
            width: 90px;
            height: 90px;
        }

        .university-text-logo {
            margin-top: 10px;
            text-align: center;
        }

        .university-text-logo h2 {
            font-size: 11px;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .university-text-logo p {
            font-size: 8px;
            color: #666;
            margin: 2px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-text-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .university-title {
            text-align: center;
            margin-bottom: 15px;
        }

        .university-title h1 {
            font-size: 24px;
            font-weight: bold;
            color: #000;
            margin: 0 0 5px 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .schools-list {
            text-align: center;
            margin-bottom: 10px;
        }

        .schools-list p {
            font-size: 9px;
            font-weight: bold;
            color: #000;
            line-height: 1.4;
            margin: 0;
            letter-spacing: 0.3px;
        }

        .programs-list {
            text-align: center;
        }

        .programs-list p {
            font-size: 8px;
            color: #333;
            line-height: 1.5;
            margin: 0;
        }

        /* Document Title */
        .document-title {
            background: #000;
            color: white;
            padding: 15px 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
            border-left: 5px solid #FFD700;
            border-right: 5px solid #FFD700;
        }

        .document-title .exam-period {
            background: white;
            color: #000;
            padding: 3px 10px;
            display: inline-block;
            margin: 0 5px;
            border-radius: 3px;
        }

        .document-title .status {
            background: white;
            color: #000;
            padding: 3px 10px;
            display: inline-block;
            margin: 0 5px;
            border-radius: 3px;
            font-weight: bold;
        }

        /* Header Info Box */
        .header-info {
            background: linear-gradient(to right, #f8f9fa, #fff);
            border-left: 4px solid #FFD700;
            padding: 12px 20px;
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header-info p {
            margin: 0;
            color: #003366;
            font-weight: 600;
            font-size: 11px;
        }

        .info-badge {
            background: #003366;
            color: #FFD700;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 2px solid #000;
        }

        thead {
            background: #003366;
            color: white;
        }

        th {
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #000;
        }

        td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            font-size: 9px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        tbody tr:nth-child(odd) {
            background: white;
        }

        /* Column-specific styling */
        td:nth-child(1) { /* Date */
            font-weight: bold;
            text-align: center;
            color: #003366;
            font-family: 'Courier New', monospace;
        }

        td:nth-child(2) { /* Day */
            text-align: center;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
            font-size: 9px;
        }

        td:nth-child(3) { /* Time */
            text-align: center;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #d32f2f;
        }

        td:nth-child(4) { /* Unit Code */
            text-align: center;
            font-weight: bold;
            color: #FF8C00;
            font-family: 'Courier New', monospace;
        }

        td:nth-child(5) { /* Unit Name */
            color: #333;
            font-weight: 500;
        }

        td:nth-child(6) { /* Group */
            text-align: center;
            font-weight: bold;
            color: #7b1fa2;
            font-size: 10px;
            background: #f3e5f5;
        }

        td:nth-child(7) { /* Students */
            text-align: center;
            font-weight: bold;
            color: #1976d2;
            font-size: 10px;
        }

        td:nth-child(8) { /* Semester */
            text-align: center;
            font-weight: bold;
            color: #00796b;
        }

        td:nth-child(9) { /* Venue */
            color: #6a1b9a;
            font-weight: 600;
        }

        td:nth-child(10) { /* Chief Invigilator */
            color: #333;
            font-weight: 500;
        }

        /* Student count badge */
        .student-count {
            display: inline-block;
            background: #1976d2;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
        }

        /* Group badge */
        .group-badge {
            display: inline-block;
            background: #7b1fa2;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-style: italic;
        }

        /* Footer */
        .footer {
            background: #003366;
            color: white;
            text-align: center;
            padding: 15px 20px;
            margin-top: 30px;
            border-top: 4px solid #FFD700;
        }

        .footer p {
            margin: 5px 0;
            font-size: 10px;
        }

        .footer .tagline {
            color: #FFD700;
            font-weight: bold;
            font-size: 9px;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                padding: 10px;
            }

            .university-header,
            .footer {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- University Header -->
        <div class="university-header">
            <div class="header-content">
                <!-- Logo Section -->
                <div class="logo-container">
                    @if(isset($logoBase64) && $logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Strathmore University Logo">
                    @else
                        <!-- SVG Fallback Logo -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="48" fill="#003366"/>
                            <text x="50" y="65" text-anchor="middle" font-size="45" fill="#FFD700" font-weight="bold" font-family="Arial">SU</text>
                        </svg>
                    @endif                                       
                </div>

                <!-- Text Content -->
                <div class="header-text-content">
                    <div class="university-title">
                        <h1>STRATHMORE UNIVERSITY</h1>
                    </div>

                    <div class="schools-list">
                        <p>
                            STRATHMORE UNIVERSITY BUSINESS SCHOOL, SCHOOL OF COMPUTING AND ENGINEERING SCIENCES,<br>
                            STRATHMORE INSTITUTE OF MATHEMATICAL SCIENCES, SCHOOL OF TOURISM AND HOSPITALITY AND<br>
                            SCHOOL OF HUMANITIES AND SOCIAL SCIENCES.
                        </p>
                    </div>

                    <div class="programs-list">
                        <p>
                            <strong>BACHELOR OF COMMERCE:</strong><br>
                            BACHELOR OF FINANCIAL SERVICES; BACHELOR OF SCIENCE IN SUPPLY CHAIN AND OPERATIONS MANAGEMENT.<br>
                            BACHELOR OF BUSINESS INFORMATION TECHNOLOGY;<br>
                            BACHELOR OF SCIENCE IN INFORMATICS AND COMPUTER SCIENCE; COMPUTER NETWORKS AND CYBERSECURITY; ELECTRICAL AND ELECTRONICS ENGINEERING.<br>
                            BACHELOR OF BUSINESS SCIENCE: ACTUARIAL SCIENCE; FINANCIAL ECONOMICS; FINANCIAL ENGINEERING.<br>
                            BACHELOR OF SCIENCE IN STATISTICS AND DATA SCIENCE.<br>
                            BACHELOR OF SCIENCE IN HOSPITALITY; TOURISM MANAGEMENT.<br>
                            BACHELOR OF ARTS: COMMUNICATION; DEVELOPMENT STUDIES AND PHILOSPOHY; INTERNATIONAL STUDIES.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Title -->
        <div class="document-title">
            <span class="exam-period">DECEMBER 2025: END OF SEMESTER EXAMINATIONS TIMETABLE</span>                       
        </div>

        <!-- Header Info -->
        <div class="header-info">
            <p>📅 Generated on: {{ $generatedAt }}</p>
            <span class="info-badge">OFFICIAL DOCUMENT</span>
        </div>

        <!-- Exam Timetable -->
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Unit Code</th>
                    <th>Unit Name</th>
                    <th>Group</th>
                    <th>Students</th>
                    <th>Semester</th>
                    <th>Venue</th>
                    <th>Chief Invigilator</th>
                </tr>
            </thead>
            <tbody>
                @forelse($examTimetables as $exam)
                <tr>
                    <td>{{ date('Y-m-d', strtotime($exam->date)) }}</td>
                    <td>{{ $exam->day }}</td>
                    <td>{{ date('H:i', strtotime($exam->start_time)) }} - {{ date('H:i', strtotime($exam->end_time)) }}</td>
                    <td>{{ $exam->unit_code }}</td>
                    <td>{{ $exam->unit_name }}</td>
                    <td>
                        <span class="group-badge">{{ $exam->group_name ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <span class="student-count">{{ $exam->no ?? 0 }}</span>
                    </td>
                    <td>{{ $exam->semester_name }}</td>
                    <td>{{ $exam->venue }} ({{ $exam->location }})</td>
                    <td>{{ $exam->chief_invigilator }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="empty-state">
                        No exam timetables available
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>This is an official Strathmore University document</p>
            <p>Please keep this timetable for your records and arrive 30 minutes before exam time</p>
            <p class="tagline">Excellence in Education Since 1961</p>
        </div>
    </div>
</body>
</html>