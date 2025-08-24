<form action="{{ route('units.store') }}" method="POST">
    @csrf
    <div>
        <label for="code">Code</label>
        <input type="text" name="code" id="code" required>
    </div>
    <div>
        <label for="name">Name</label>
        <input type="text" name="name" id="name" required>
    </div>
    <div>
        <label for="credit_hours">Credit Hours</label>
        <input type="number" name="credit_hours" id="credit_hours" required>
    </div>
    <button type="submit">Create Unit</button>
</form>
