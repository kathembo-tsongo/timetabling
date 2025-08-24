<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>{{ $title }}</title>
<style>
body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    margin: 20px;
    background: #f9f9f9;
}

h1 {
    text-align: center;
    font-size: 24px;
    margin-bottom: 10px;
    color: #333;
    font-weight: bold;
}

h2 {
    text-align: center;
    font-size: 18px;
    margin-bottom: 20px;
    color: #666;
}

.header-info {
    text-align: center;
    margin-bottom: 20px;
    font-size: 14px;
    padding: 15px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 5px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

th, td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    font-size: 11px;
}

th {
    background-color: #f4f4f4;
    font-weight: bold;
    text-align: center;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

tr:hover {
    background-color: #f5f5f5;
}

/* Column specific styling */
td:nth-child(1) { /* Date */
    font-weight: bold;
    text-align: center;
    font-family: monospace;
}

td:nth-child(2) { /* Day */
    text-align: center;
    font-weight: bold;
    color: #0066cc;
}

td:nth-child(3) { /* Time */
    text-align: center;
    font-family: monospace;
    font-weight: bold;
    color: #cc0000;
}

td:nth-child(4) { /* Unit Code */
    text-align: center;
    font-weight: bold;
    color: #cc6600;
}

td:nth-child(5) { /* Unit Name */
    color: #333;
}

td:nth-child(6) { /* Semester */
    text-align: center;
    font-weight: bold;
    color: #009900;
}

td:nth-child(7) { /* Venue */
    color: #660066;
    font-weight: bold;
}

td:nth-child(8) { /* Chief Invigilator */
    color: #333;
}

.footer {
    margin-top: 20px;
    text-align: center;
    font-size: 10px;
    color: #666;
    padding: 10px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 5px;
}

/* Print styles */
@media print {
    body {
        background: white;
        margin: 10px;
    }
    
    table {
        box-shadow: none;
    }
    
    .header-info, .footer {
        background: white;
        border: 1px solid #ddd;
    }
}
</style>
</head>
<body>
<h1>STRATHMORE UNIVERSITY</h1>
<h1>{{ $title }}</h1>
<div class="header-info">
<p>Generated at: {{ $generatedAt }}</p>
</div>
<table>
<thead>
<tr>
<th>Date</th>
<th>Day</th>
<th>Time</th>
<th>Unit Code</th>
<th>Unit Name</th>
<th>Semester</th>
<th>Venue</th>
<th>Chief Invigilator</th>
</tr>
</thead>
<tbody>
 @forelse($examTimetables as $exam)
<tr>
<td>{{ date('Y:m:d', strtotime($exam->date)) }}</td>
<td>{{ $exam->day }}</td>
<td>{{ date('H:i', strtotime($exam->start_time)) }} - {{ date('H:i', strtotime($exam->end_time)) }}</td>
<td>{{ $exam->unit_code }}</td>
<td>{{ $exam->unit_name }}</td>
<td>{{ $exam->semester_name }}</td>
<td>{{ $exam->venue }} ({{ $exam->location }})</td>
<td>{{ $exam->chief_invigilator }}</td>
</tr>
 @empty
<tr>
<td colspan="8" style="text-align: center; padding: 20px; color: #999; font-style: italic;">No exam timetables available</td>
</tr>
 @endforelse
</tbody>
</table>
<div class="footer">
<p>This is an official document. Please keep it for your records.</p>
</div>
</body>
</html>