<?php
namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::with('class')->paginate(10);
        $classes = ClassModel::all();

        return inertia('Groups/Index', [
            'groups' => $groups,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'required|exists:classes,id',
            'capacity' => 'required|integer|min:1',
        ]);

        Group::create($validated);

        return redirect()->back()->with('success', 'Group created successfully!');
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'required|exists:classes,id',
            'capacity' => 'required|integer|min:1',
        ]);

        $group->update($validated);

        return redirect()->back()->with('success', 'Group updated successfully!');
    }

    public function destroy(Group $group)
    {
        try {
            $group->delete();

            // Return JSON response for AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Group deleted successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Group deleted successfully!');
        } catch (\Exception $e) {
            // Return JSON error response for AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete group. Please try again.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete group. Please try again.');
        }
    }
}
