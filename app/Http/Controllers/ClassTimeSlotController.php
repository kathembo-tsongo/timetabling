<?php

namespace App\Http\Controllers;

use App\Models\ClassTimeSlot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassTimeSlotController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        $classtimeSlot = ClassTimeSlot::query()
            ->when($search, function ($query, $search) {
                $query->where('day', 'like', "%{$search}%")
                    ->orWhere('start_time', 'like', "%{$search}%")
                    ->orWhere('end_time', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            })
            ->orderBy('day', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ClassTimeSlot/index', [
            'classtimeSlot' => $classtimeSlot,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|string|in:Physical,Online',
        ]);

        ClassTimeSlot::create($validated);

        return redirect()->back()->with('success', 'Class time slot created successfully!');
    }

    public function update(Request $request, $id)
    {
        $classTimeSlot = ClassTimeSlot::findOrFail($id);

        $validated = $request->validate([
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|string|in:Physical,Online',
        ]);

        $classTimeSlot->update($validated);

        return redirect()->back()->with('success', 'Class time slot updated successfully!');
    }

    public function destroy($id)
    {
        $classTimeSlot = ClassTimeSlot::findOrFail($id);
        $classTimeSlot->delete();

        return redirect()->back()->with('success', 'Class time slot deleted successfully!');
    }

    public function show($id)
    {
        $classTimeSlot = ClassTimeSlot::findOrFail($id);
        
        return Inertia::render('ClassTimeSlot/Show', [
            'classTimeSlot' => $classTimeSlot,
        ]);
    }
}