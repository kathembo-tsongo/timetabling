<table>
    <thead>
        <tr>
            <th>Day</th>
            <th>Date</th>
            <th>Unit</th>
            <th>Time</th>
            <th>Venue</th>
            <th>Chief Invigilator</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($timetables as $timetable)
            <tr>
                <td>{{ $timetable->day }}</td>
                <td>{{ $timetable->date }}</td>
                <td>{{ $timetable->unit_name }}</td>
                <td>{{ $timetable->start_time }} - {{ $timetable->end_time }}</td>
                <td>{{ $timetable->venue }}</td>
                <td>{{ $timetable->chief_invigilator }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
