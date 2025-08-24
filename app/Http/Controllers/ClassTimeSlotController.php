<?php

namespace App\Http\Controllers;

use App\Models\ClassTimeSlot;
use Illuminate\Http\Request;
use Inertia\Inertia; // Correctly import the Inertia facade

class ClassTimeSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Add debugging to track what's being received
        \Log::debug('ClassTimeSlot Index Request', [
            'search' => $request->search,
            'per_page' => $request->get('per_page', 10)
        ]);

        $query = ClassTimeSlot::query();

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('day', 'like', "%{$search}%")                 
                  ->orWhere('start_time', 'like', "%{$search}%")
                  ->orWhere('end_time', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%"); // Add status to search
            });
        }

        $classtimeSlot = $query->paginate($request->get('per_page', 10));

        // Add debugging to see what's being returned
        \Log::debug('ClassTimeSlot Index Response', [
            'count' => count($classtimeSlot->items()),
            'total' => $classtimeSlot->total()
        ]);

        return Inertia::render('ClassTimeSlot/index', [
            'classtimeSlot' => $classtimeSlot,
            'perPage' => (int)$request->get('per_page', 10),
            'search' => $request->get('search', '')
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'status' => 'required|in:Physical,Online', // Validate status
        ]);

        ClassTimeSlot::create($validated);

        return redirect()->back()->with('success', 'Class Time slot created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClassTimeSlot $classtimeSlot)
    {
        $validated = $request->validate([
            'day' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'status' => 'required|in:Physical,Online', // Corrected validation rule
        ]);

        $classtimeSlot->update($validated);

        return redirect()->back()->with('success', 'Class Time slot updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClassTimeSlot $classtimeSlot)
    {
        // Delete the time slot
        $classtimeSlot->delete();

        return redirect()->back()
            ->with('success', 'Class Time slot deleted successfully.');
    }
}