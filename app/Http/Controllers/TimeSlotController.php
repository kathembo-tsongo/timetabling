<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Inertia\Inertia; // Correctly import the Inertia facade

class TimeSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Add debugging to track what's being received
        \Log::debug('TimeSlot Index Request', [
            'search' => $request->search,
            'per_page' => $request->get('per_page', 10)
        ]);

        $query = TimeSlot::query();

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('day', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhere('start_time', 'like', "%{$search}%")
                  ->orWhere('end_time', 'like', "%{$search}%");
            });
        }

        $timeSlots = $query->paginate($request->get('per_page', 10));

        // Add debugging to see what's being returned
        \Log::debug('TimeSlot Index Response', [
            'count' => count($timeSlots->items()),
            'total' => $timeSlots->total()
        ]);

        return Inertia::render('TimeSlots/index', [
            'timeSlots' => $timeSlots,
            'perPage' => (int)$request->get('per_page', 10),
            'search' => $request->get('search', '')
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'day' => 'required',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        // Create the time slot
        TimeSlot::create($validated);

        return redirect()->back()
            ->with('success', 'Time slot created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeSlot $timeSlot)
    {
        // Validate the request
        $validated = $request->validate([
            'day' => 'required',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        // Update the time slot
        $timeSlot->update($validated);

        return redirect()->back()
            ->with('success', 'Time slot updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeSlot $timeSlot)
    {
        // Delete the time slot
        $timeSlot->delete();

        return redirect()->back()
            ->with('success', 'Time slot deleted successfully.');
    }
}