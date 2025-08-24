<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Schools</th> <!-- Add Schools column -->
            <th>Programs</th> <!-- Add Programs column -->
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->schools }}</td> <!-- Display Schools -->
            <td>{{ $user->programs }}</td> <!-- Display Programs -->
        </tr>
        @endforeach
    </tbody>
</table>
