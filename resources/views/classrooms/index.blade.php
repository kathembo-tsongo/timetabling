<form action="{{ route('classrooms.destroy', $classroom->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this classroom?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger">Delete</button>
</form>
