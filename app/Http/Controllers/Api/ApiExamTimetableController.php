<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiExamTimetableController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['message' => 'Exam timetable API']);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Exam timetable ' . $id]);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Created']);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Updated']);
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Deleted']);
    }
}
