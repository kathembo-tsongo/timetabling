<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\ScesUnit;
use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    // ========================================
    // BBIT PROGRAM METHODS
    // ========================================
    
    /**
     * BBIT Enrollments - Fixed component path and data structure
     */
    public function bbitEnrollments()
    {
        $user = auth()->user();
        
        Log::info('bbitEnrollments method called', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames()
        ]);

        try {
            $currentSemester = Semester::where('is_active', true)->first();
            $enrollments = $this->fetchBbitEnrollments();
            
            // Make sure we're getting BBIT units properly - Handle missing table gracefully
            $bbitUnits = collect([]);
            try {
                $bbitUnits = DB::table('bbit_units')
                    ->where('is_active', 1)
                    ->select('id', 'name', 'code', 'credit_hours', 'is_active')
                    ->orderBy('name')
                    ->get();
            } catch (\Exception $e) {
                Log::warning('BBIT units table not found or accessible', ['error' => $e->getMessage()]);
            }

            // Debug log to check if units are being fetched
            Log::info('BBIT Units fetched', [
                'count' => $bbitUnits->count(),
                'sample_units' => $bbitUnits->take(3)->toArray()
            ]);

            $lecturerAssignments = $this->getBbitLecturerAssignments();
            
            // Handle missing role or students gracefully
            $students = collect([]);
            try {
                $students = User::role('Student')->get(['id', 'code', 'first_name', 'last_name']);
            } catch (\Exception $e) {
                Log::warning('Could not fetch students with role', ['error' => $e->getMessage()]);
                $students = User::where('role', 'Student')
                    ->orWhere('user_type', 'Student')
                    ->get(['id', 'code', 'first_name', 'last_name']);
            }
            
            $semesters = Semester::orderBy('name')->get(['id', 'name', 'is_active']);
            
            // Handle groups with graceful fallback - FIXED
            $groups = collect([]);
            try {
                // Try different possible relationship names
                $groups = Group::with('class:id,name')->orderBy('name')->get(['id', 'name', 'capacity', 'class_id']);
            } catch (\Exception $e) {
                Log::warning('Could not fetch groups with class relationship', ['error' => $e->getMessage()]);
                // Fallback: get groups without relationships and join manually
                try {
                    $groups = DB::table('groups as g')
                        ->leftJoin('bbit_classes as c', 'g.class_id', '=', 'c.id')
                        ->select('g.id', 'g.name', 'g.capacity', 'g.class_id', 'c.name as class_name')
                        ->orderBy('g.name')
                        ->get();
                } catch (\Exception $e2) {
                    Log::warning('Could not fetch groups with manual join', ['error' => $e2->getMessage()]);
                    // Last fallback: just get groups without class info
                    $groups = DB::table('groups')
                        ->select('id', 'name', 'capacity', 'class_id', DB::raw("'No Class Assigned' as class_name"))
                        ->orderBy('name')
                        ->get();
                }
            }

            // Transform groups to match React component expectations with null safety - FIXED
            $groups = $groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'capacity' => $group->capacity,
                    'class_id' => $group->class_id,
                    'class_name' => $group->class_name ?? $group->class?->name ?? 'No Class Assigned',
                    'class' => [
                        'id' => $group->class_id,
                        'name' => $group->class_name ?? $group->class?->name ?? 'No Class Assigned'
                    ]
                ];
            });

            // Handle permissions gracefully
            $userPermissions = [];
            $userRoles = [];
            try {
                $userPermissions = $user->getAllPermissions()->pluck('name');
                $userRoles = $user->getRoleNames();
            } catch (\Exception $e) {
                Log::warning('Could not fetch user permissions/roles', ['error' => $e->getMessage()]);
                $userRoles = collect([$user->role ?? 'Faculty Admin']);
            }

            $data = [
                'enrollments' => $enrollments,
                'lecturerAssignments' => $lecturerAssignments,
                'bbitUnits' => $bbitUnits,
                'students' => $students,
                'semesters' => $semesters,
                'groups' => $groups,
                'schoolCode' => 'SCES',
                'programCode' => 'BBIT',
                'programName' => 'Bachelor of Business Information Technology',
                'currentSemester' => $currentSemester,
                'userPermissions' => $userPermissions,
                'userRoles' => $userRoles,
                'errors' => []
            ];

            Log::info('Data being passed to React component', [
                'enrollments_count' => $enrollments->count(),
                'units_count' => $bbitUnits->count(),
                'students_count' => $students->count(),
                'semesters_count' => $semesters->count(),
                'groups_count' => $groups->count(),
                'current_semester' => $currentSemester ? $currentSemester->name : 'None'
            ]);

            // Render component directly
            Log::info('Attempting to render component', ['path' => 'FacultyAdmin/sces/bbit/Enrollments']);
            return Inertia::render('FacultyAdmin/sces/bbit/Enrollments', $data);

        } catch (\Exception $e) {
            Log::error('Error fetching BBIT enrollments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);
            
            return redirect()->route('faculty.dashboard.sces')->withErrors(['error' => 'Unable to load BBIT enrollments: ' . $e->getMessage()]);
        }
    }

    /**
     * NEW: Public API endpoint to get BBIT enrollments data
     */
    public function getBbitEnrollments()
    {
        try {
            Log::info('API: Fetching BBIT enrollments');
            
            $enrollments = $this->fetchBbitEnrollments();
            
            return response()->json([
                'success' => true,
                'data' => $enrollments,
                'message' => "Found {$enrollments->count()} BBIT enrollments"
            ]);

        } catch (\Exception $e) {
            Log::error('API: Error fetching BBIT enrollments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'data' => [],
                'error' => 'Failed to fetch BBIT enrollments',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NEW: Get BBIT units for a specific semester - API endpoint
     */
    public function getBbitUnitsBySemester($semesterId)
    {
        try {
            Log::info('Fetching BBIT units for semester', ['semester_id' => $semesterId]);

            // Get units that belong to this semester
            // Adjust the relationship based on your database schema
            $units = DB::table('bbit_units')
                ->where('is_active', 1)
                ->where('semester_id', $semesterId) // Adjust this field name if different
                ->select('id', 'name', 'code', 'credit_hours', 'is_active', 'semester_id')
                ->orderBy('name')
                ->get();

            Log::info('Units found for semester', [
                'semester_id' => $semesterId,
                'units_count' => $units->count(),
                'units' => $units->toArray()
            ]);

            return response()->json([
                'success' => true,
                'units' => $units,
                'message' => "Found {$units->count()} units for semester {$semesterId}"
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching BBIT units for semester', [
                'semester_id' => $semesterId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'units' => [],
                'error' => 'Failed to fetch units for selected semester',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new enrollment (generic, not BBIT-specific)
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'student_code' => 'required|string|exists:users,code',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        try {
            // Check for existing enrollment
            $existingEnrollment = Enrollment::where([
                'student_code' => $validated['student_code'],
                'unit_id' => $validated['unit_id'],
                'semester_id' => $validated['semester_id']
            ])->first();

            if ($existingEnrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is already enrolled in this unit for the selected semester.'
                ], 422);
            }

            // Create new enrollment
            Enrollment::create([
                'student_code' => $validated['student_code'],
                'unit_id' => $validated['unit_id'],
                'semester_id' => $validated['semester_id'],
                'group_id' => $validated['group_id'],
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Enrollment created successfully!'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating enrollment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollment: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Delete BBIT enrollment
     */
    public function destroyBbitEnrollment($enrollmentId)
    {
        $user = auth()->user();
        
        Log::info('BBIT enrollment deletion attempt', [
            'enrollment_id' => $enrollmentId,
            'user_id' => $user->id
        ]);

        try {
            $deleted = DB::table('bbit_enrollments')->where('id', $enrollmentId)->delete();
            
            if ($deleted) {
                Log::info('BBIT enrollment deleted successfully', ['enrollment_id' => $enrollmentId]);
                return response()->json(['success' => true, 'message' => 'BBIT enrollment deleted successfully!']);
            } else {
                return response()->json(['success' => false, 'message' => 'Enrollment not found'], 404);
            }

        } catch (\Exception $e) {
            Log::error('BBIT enrollment deletion failed', [
                'enrollment_id' => $enrollmentId,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false, 
                'message' => 'Failed to delete enrollment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete BBIT enrollments
     */
    public function bulkDestroyBbitEnrollments(Request $request)
    {
        $user = auth()->user();
        
        $enrollmentIds = $request->input('enrollment_ids', []);
        
        Log::info('BBIT bulk enrollment deletion attempt', [
            'enrollment_ids' => $enrollmentIds,
            'user_id' => $user->id
        ]);

        if (empty($enrollmentIds)) {
            return response()->json(['success' => false, 'message' => 'No enrollments selected'], 422);
        }

        try {
            $deleted = DB::table('bbit_enrollments')->whereIn('id', $enrollmentIds)->delete();
            
            Log::info('BBIT enrollments bulk deleted', [
                'enrollment_ids' => $enrollmentIds,
                'deleted_rows' => $deleted,
                'deleted_by' => $user->code
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($enrollmentIds) . " BBIT enrollments deleted successfully!",
                'deleted_count' => $deleted
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error bulk deleting BBIT enrollments', [
                'enrollment_ids' => $enrollmentIds,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete enrollments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign lecturer to BBIT unit
     */
    public function assignBbitLecturer(Request $request)
    {
        $user = auth()->user();
        
        Log::info('BBIT lecturer assignment attempt', [
            'request_data' => $request->all(),
            'user_id' => $user->id
        ]);

        $validated = $request->validate([
            'unit_id' => 'required|exists:bbit_units,id',
            'lecturer_code' => 'required|string|exists:users,code',
        ]);

        try {
            // Verify the lecturer has the correct role
            $lecturer = User::where('code', $validated['lecturer_code'])->first();
            if (!$lecturer || !$lecturer->hasRole('Lecturer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The specified user is not a lecturer.'
                ], 422);
            }

            // Update all enrollments for this unit to assign the lecturer
            $updated = DB::table('bbit_enrollments')
                ->where('unit_id', $validated['unit_id'])
                ->update([
                    'lecturer_code' => $validated['lecturer_code'],
                    'updated_at' => now()
                ]);

            Log::info('BBIT unit assigned to lecturer', [
                'unit_id' => $validated['unit_id'],
                'lecturer_code' => $validated['lecturer_code'],
                'enrollments_updated' => $updated
            ]);

            return response()->json([
                'success' => true,
                'message' => "BBIT unit successfully assigned to lecturer {$validated['lecturer_code']}!",
                'enrollments_updated' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign BBIT unit to lecturer', [
                'error' => $e->getMessage(),
                'unit_id' => $validated['unit_id'] ?? 'unknown',
                'lecturer_code' => $validated['lecturer_code'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign unit to lecturer: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // HELPER METHODS FOR DATA RETRIEVAL
    // ========================================
    
    /**
     * Get BBIT enrollments with relationships (renamed from getBbitEnrollments to avoid conflict)
     */
    private function fetchBbitEnrollments()
    {
        try {
            return DB::table('bbit_enrollments as be')
                ->join('bbit_units as bu', 'be.unit_id', '=', 'bu.id')
                ->leftJoin('users as students', 'be.student_code', '=', 'students.code')
                ->leftJoin('users as lecturers', 'be.lecturer_code', '=', 'lecturers.code')
                ->leftJoin('groups as g', 'be.group_id', '=', 'g.id')
                ->leftJoin('bbit_classes as c', 'g.class_id', '=', 'c.id') // Fixed table name - changed from bbit_classes to classes
                ->select(
                    'be.id',
                    'be.student_code',
                    'be.unit_id',
                    'be.semester_id',
                    'be.group_id',
                    'be.lecturer_code',
                    'be.created_at',
                    'be.updated_at',
                    'bu.name as unit_name',
                    'bu.code as unit_code',
                    'bu.credit_hours',
                    'students.first_name as student_first_name',
                    'students.last_name as student_last_name',
                    'lecturers.first_name as lecturer_first_name',
                    'lecturers.last_name as lecturer_last_name',
                    'g.name as group_name',
                    'g.capacity as group_capacity',
                    'c.name as class_name'
                )
                ->orderBy('be.created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in fetchBbitEnrollments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty collection if there's an error
            return collect([]);
        }
    }

    /**
     * Get BBIT lecturer assignments
     */
    private function getBbitLecturerAssignments()
    {
        try {
            return DB::table('bbit_enrollments')
                ->select([
                    'bbit_enrollments.unit_id',
                    'bbit_units.name as unit_name',
                    'bbit_units.code as unit_code',
                    'bbit_enrollments.lecturer_code',
                    'users.first_name',
                    'users.last_name',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as lecturer_name")
                ])
                ->join('bbit_units', 'bbit_enrollments.unit_id', '=', 'bbit_units.id')
                ->leftJoin('users', 'bbit_enrollments.lecturer_code', '=', 'users.code')
                ->whereNotNull('bbit_enrollments.lecturer_code')
                ->where('bbit_enrollments.lecturer_code', '!=', '')
                ->groupBy([
                    'bbit_enrollments.unit_id', 'bbit_units.name', 'bbit_units.code',
                    'bbit_enrollments.lecturer_code', 'users.first_name', 'users.last_name'
                ])
                ->orderBy('bbit_units.name')
                ->get();
        } catch (\Exception $e) {
            Log::error('Error fetching BBIT lecturer assignments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return collect([]);
        }
    }

    /**
     * Remove lecturer assignment
     */
    public function removeLecturerAssignment($unitId, $lecturerCode)
    {
        $user = auth()->user();
        
        Log::info('Remove lecturer assignment attempt', [
            'unit_id' => $unitId,
            'lecturer_code' => $lecturerCode,
            'user_id' => $user->id
        ]);

        try {
            // Check if assignment exists
            $assignmentExists = DB::table('bbit_enrollments')
                ->where('unit_id', $unitId)
                ->where('lecturer_code', $lecturerCode)
                ->exists();

            if (!$assignmentExists) {
                return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
            }

            // Remove lecturer from all enrollments for this unit
            $updated = DB::table('bbit_enrollments')
                ->where('unit_id', $unitId)
                ->where('lecturer_code', $lecturerCode)
                ->update([
                    'lecturer_code' => null,
                    'updated_at' => now()
                ]);

            Log::info('Lecturer removed from BBIT enrollments', [
                'unit_id' => $unitId,
                'lecturer_code' => $lecturerCode,
                'updated_rows' => $updated
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lecturer assignment removed successfully!',
                'updated_rows' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing lecturer assignment', [
                'unit_id' => $unitId,
                'lecturer_code' => $lecturerCode,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove lecturer assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug method to check database tables and data
     */
    public function debugBbitData()
    {
        try {
            $debug = [
                'tables_exist' => [],
                'record_counts' => [],
                'sample_data' => []
            ];

            // Check if tables exist and get counts
            $tables = ['bbit_enrollments', 'bbit_units', 'users', 'groups', 'semesters'];
            
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $debug['tables_exist'][$table] = true;
                    $debug['record_counts'][$table] = $count;
                    
                    if ($count > 0) {
                        $debug['sample_data'][$table] = DB::table($table)->limit(3)->get();
                    }
                } catch (\Exception $e) {
                    $debug['tables_exist'][$table] = false;
                    $debug['record_counts'][$table] = 'Error: ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'debug_info' => $debug
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}