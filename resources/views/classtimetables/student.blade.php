<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Student Class Timetable</title>
<style>
body {
font-family: Arial, sans-serif;
font-size: 12px;
margin: 0;
padding: 0;
background-color: #f0f8ff; /* Light blue background */
color: #333;
}
.container {
margin: 20px;
padding: 20px;
background-color: #ffffff; /* White background for content */
border-radius: 10px;
box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
.header {
display: flex;
align-items: center;
justify-content: space-between;
border-bottom: 4px solid #0047AB; /* Blue border */
padding-bottom: 10px;
margin-bottom: 20px;
background-color: #0047AB; /* Blue background */
color: #ffffff; /* White text */
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
.student-info {
margin-bottom: 20px;
padding: 15px;
border: 1px solid #ddd;
background-color: #fff8dc; /* Light yellow background */
border-radius: 10px;
}
table {
width: 100%;
border-collapse: collapse;
margin-top: 20px;
background-color: #ffffff; /* White background for table */
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
background-color: #ffcccb; /* Light red background for header */
font-weight: bold;
color: #333;
}
tr:nth-child(even) {
background-color: #f9f9f9; /* Light gray for even rows */
}
tr:hover {
background-color: #f1f1f1; /* Slightly darker gray on hover */
}
.footer {
margin-top: 20px;
text-align: center;
font-size: 10px;
color: #666;
padding: 10px;
background-color: #0047AB; /* Blue background */
color: #ffffff; /* White text */
border-radius: 0 0 10px 10px;
}
</style>
</head>
<body>
<div class="container">
<div class="header">
<div class="title">
<!-- <img src="/images/strathmore.png" alt="Strathmore University Logo" class="h-8">   -->
<h1>STRATHMORE UNIVERSITY</h1>
<p>My Student Class Timetable</p>
</div>
</div>
<div class="student-info">
<p><strong>Student Name:</strong> {{ $student->first_name }} {{ $student->last_name }}</p>
<p><strong>Student ID:</strong> {{ $student->code }}</p>
<p><strong>Semester:</strong> {{ $currentSemester->name }}</p>
</div>
<table>
<thead>
<tr>
<th>Day</th>
<th>Unit Code</th>
<th>Unit Name</th>
<th>Semester</th>
<th>Classroom</th>
<th>Time</th>
<th>Location</th>
<th>Lecturer</th>
</tr>
</thead>
<tbody>
@forelse($classTimetables as $class)
<tr>
<td>{{ $class->day }}</td>
<td>{{ $class->unit_code }}</td>
<td>{{ $class->unit_name }}</td>
<td>{{ $class->semester_name }}</td>
<td>{{ $class->venue }}</td>
<td>{{ $class->start_time }} - {{ $class->end_time }}</td>
<td>{{ $class->location }}</td>
<td>{{ $class->lecturer }}</td>
</tr>
@empty
<tr>
<td colspan="8" style="text-align: center;">No class timetables available</td>
</tr>
@endforelse
</tbody>
</table>
<div class="footer">
<p>This is an official document. Please keep it for your records.</p>
</div>
</div>
</body>
</html>

