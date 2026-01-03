<?php


use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExamTimetableController;
use App\Http\Controllers\ExamroomController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\LecturerAssignmentController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\ClassTimetableController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\Carbon;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\DynamicRoleController;
use App\Http\Controllers\DynamicPermissionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ClassTimeSlotController;
use App\Http\Controllers\Controller;

// ✅ ADD THIS LINE - Import the ElectiveController
use App\Http\Controllers\ElectiveController;

use App\Models\ClassModel;
use App\Models\Program;
use App\Models\Unit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
// Load module routes
$moduleRoutes = glob(base_path('Modules/*/routes/web.php'));
foreach ($moduleRoutes as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}

require __DIR__.'/auth.php';

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // MAIN DASHBOARD
    Route::get('/dashboard', function () {
    $user = auth()->user();
    
    if ($user->hasRole('Admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('Class Timetable office')) {
        return redirect()->route('classtimetables.dashboard');
    }
    
    if ($user->hasRole('Exam Office')) {
        return redirect()->route('examoffice.dashboard');
    }
    
    // Faculty Admin redirect
    $roles = $user->getRoleNames();
    foreach ($roles as $role) {
        if (str_starts_with($role, 'Faculty Admin - ')) {
            return redirect()->route('schoolAdmin.dashboard');
        }
    }
    
    if ($user->hasRole('Lecturer')) {
        return redirect()->route('lecturer.dashboard');
    }
    
    if ($user->hasRole('Student')) {
        return redirect()->route('student.dashboard');
    }
    
    // No Dashboard.tsx fallback - show error instead
    abort(403, 'No dashboard available for your role.');
    
})->name('dashboard');

   // ============================================
    // GLOBAL API ROUTES
    // ============================================
    Route::prefix('api')->middleware(['auth'])
        ->group(function () {
        
        // ✅ ADD THIS - Schools API for bulk scheduling
        Route::get('/schools', function () {
            try {
                $schools = \App\Models\School::select('id', 'code', 'name')
                    ->orderBy('name')
                    ->get();
                
                \Log::info('Schools API called', ['count' => $schools->count()]);
                
                // ✅ Return array directly, not wrapped
                return response()->json($schools);
            } catch (\Exception $e) {
                \Log::error('Schools API error: ' . $e->getMessage());
                return response()->json([], 500);
            }
        })->name('api.schools');
        
        // ✅ ADD THIS - Programs by school API for bulk scheduling
    Route::get('/programs-by-school', function(Request $request) {
    try {
        $schoolId = $request->input('school_id');
        $semesterId = $request->input('semester_id');
        
        if (!$schoolId || !$semesterId) {
            return response()->json([
                'success' => false,
                'message' => 'school_id and semester_id are required'
            ], 400);
        }
        
        // ✅ FIXED: Check which columns exist in programs table
        $programsTable = \Schema::getColumnListing('programs');
        $selectColumns = ['id', 'code', 'name', 'school_id'];
        
        // ✅ Only select full_name if it exists
        if (in_array('full_name', $programsTable)) {
            $selectColumns[] = 'full_name';
        }
        
        $programs = \App\Models\Program::where('school_id', $schoolId)
            ->with('school:id,code,name')
            ->select($selectColumns)
            ->orderBy('name')
            ->get();
        
        \Log::info('Programs by school API called', [
            'school_id' => $schoolId,
            'semester_id' => $semesterId,
            'programs_count' => $programs->count()
        ]);
        
        return response()->json([
            'success' => true,
            'programs' => $programs
        ]);
    } catch (\Exception $e) {
        \Log::error('Programs by school API error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch programs'
        ], 500);
    }
    })->name('api.programs-by-school');
        
        // ✅ ADD THIS - Classrooms API for bulk scheduling
        Route::get('/classrooms', function() {
            try {
                $classrooms = \App\Models\Examroom::where('is_active', true)
                    ->select('id', 'name', 'capacity', 'location')
                    ->orderBy('capacity')
                    ->get();
                
                \Log::info('Classrooms API called', ['count' => $classrooms->count()]);
                
                return response()->json($classrooms);
            } catch (\Exception $e) {
                \Log::error('Classrooms API error: ' . $e->getMessage());
                return response()->json([], 500);
            }
        })->name('api.classrooms');
        
        // ✅ ADD THIS - Bulk Schedule API endpoint
        // ✅ FIXED:
        Route::post('/exams/bulk-schedule', function(Request $request) {
            try {
                $programId = $request->input('program_id');
                
                if (!$programId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'program_id is required'
                    ], 400);
                }
                
                $program = \App\Models\Program::with('school')->find($programId);
                
                if (!$program) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Program not found'
                    ], 404);
                }
                
                $schoolCode = $program->school->code ?? 'SBS';
                
                \Log::info('Bulk schedule API called', [
                    'program_id' => $programId,
                    'school_code' => $schoolCode,
                    'data' => $request->all()
                ]);
                
                // ✅ PASS REQUEST FIRST!
                return app(ExamTimetableController::class)->bulkScheduleExams($request, $program, $schoolCode);
                
            } catch (\Exception $e) {
                \Log::error('Bulk schedule API error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to schedule exams: ' . $e->getMessage()
                ], 500);
            }
        })->middleware(['permission:create-exam-timetables'])->name('api.exams.bulk-schedule');
        // Exam Timetable APIs (your existing code)
        Route::prefix('exam-timetables')->group(function () {
            Route::get('/classes-by-semester/{semesterId}', 
                [ExamTimetableController::class, 'getClassesBySemester']
            )->middleware(['permission:view-exam-timetables']);
            
            Route::get('/units-by-class', 
                [ExamTimetableController::class, 'getUnitsByClassAndSemesterForExam']
            )->middleware(['permission:view-exam-timetables']);
        });
    });

    Route::prefix('SchoolAdmin')->group(function() {
    Route::get('/dashboard', function() {
        $user = auth()->user();
        $roles = $user->getRoleNames();
        
        \Log::info('SchoolAdmin dashboard access attempt', [
            'user_email' => $user->email,
            'roles' => $roles->toArray()
        ]);
        
        $schoolCode = null;
        
        // Check for Faculty Admin roles (NEW format: Faculty Admin - SHSS)
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $schoolCode = str_replace('Faculty Admin - ', '', $role);
                \Log::info('Faculty Admin role detected', ['school_code' => $schoolCode]);
                break;
            }
        }
        
        // Also check for ExamOff roles (OLD format: ExamOff- SHSS)
        if (!$schoolCode) {
            foreach ($roles as $role) {
                if (str_starts_with($role, 'ExamOff- ')) {
                    $schoolCode = str_replace('ExamOff- ', '', $role);
                    \Log::info('ExamOff role detected', ['school_code' => $schoolCode]);
                    break;
                }
            }
        }
        
        // If we found a school code, route to appropriate dashboard
        if ($schoolCode) {
            \Log::info('Routing to school dashboard', ['school_code' => $schoolCode]);
            
            switch($schoolCode) {
                case 'SCES':
                    return app(\App\Http\Controllers\DashboardController::class)->scesDashboard();
                case 'SBS':
                    return app(\App\Http\Controllers\DashboardController::class)->sbsDashboard();
                case 'SLS':
                    return app(\App\Http\Controllers\DashboardController::class)->slsDashboard();
                case 'SHSS':
                    return app(\App\Http\Controllers\DashboardController::class)->shssDashboard();
                case 'SMS':
                    return app(\App\Http\Controllers\DashboardController::class)->smsDashboard();
                case 'STH':
                    return app(\App\Http\Controllers\DashboardController::class)->sthDashboard();
                case 'SI':
                    return app(\App\Http\Controllers\DashboardController::class)->siDashboard();
                default:
                    \Log::error('Unknown school code', ['school_code' => $schoolCode]);
                    abort(403, 'Unknown school: ' . $schoolCode);
            }
        }
        
        \Log::error('No valid school role found', ['roles' => $roles->toArray()]);
        abort(403, 'You do not have permission to access any school admin dashboard.');
    })
    ->middleware([
        'auth', 
        'role:Faculty Admin - SCES|Faculty Admin - SBS|Faculty Admin - SLS|Faculty Admin - SHSS|Faculty Admin - SMS|Faculty Admin - STH|Faculty Admin - SI|ExamOff- SCES|ExamOff- SBS|ExamOff- SLS|ExamOff- SHSS|ExamOff- SMS|ExamOff- STH|ExamOff- SI|Admin'
    ])
    ->name('schoolAdmin.dashboard');
});


    // ADMIN ROUTES - PERMISSION-BASED
    Route::prefix('admin')->group(function () {
        
        Route::get('/', [DashboardController::class, 'adminDashboard'])
            ->middleware(['role:Admin'])
            ->name('admin.dashboard'); 
            
        // Dynamic Permissions - Admin only
        Route::middleware(['role:Admin'])->group(function () {
            Route::get('/permissions/dynamic', [DynamicPermissionController::class, 'index'])->name('permissions.dynamic.index');
            Route::post('/permissions', [DynamicPermissionController::class, 'store'])->name('permissions.store');
            Route::post('/permissions/bulk', [DynamicPermissionController::class, 'bulkCreate'])->name('permissions.bulk');
            Route::put('/permissions/{permission}', [DynamicPermissionController::class, 'update'])->name('permissions.update');
            Route::delete('/permissions/{permission}', [DynamicPermissionController::class, 'destroy'])->name('permissions.destroy');
        });
        
        // User Role Management - Admin only
        Route::middleware(['role:Admin'])->group(function () {
            Route::get('/users/roles', [RoleManagementController::class, 'index'])->name('users.roles.index');
            Route::put('/users/{user}/roles', [RoleManagementController::class, 'updateUserRole'])->name('users.roles.update');
            Route::delete('/users/{user}/roles', [RoleManagementController::class, 'removeUserRole'])->name('users.roles.remove');
            Route::post('/users/roles/bulk-assign', [RoleManagementController::class, 'bulkAssignRole'])->name('users.roles.bulk');
        });

        // Units - PERMISSION-BASED
        Route::middleware(['permission:view-units'])->group(function () {
            Route::get('/units', [UnitController::class, 'index'])->name('admin.units.index');
            Route::get('/units/{unit}', [UnitController::class, 'Show'])->name('admin.units.show');
        });
        Route::post('/units', [UnitController::class, 'Store'])->middleware(['permission:create-units'])->name('admin.units.store');
        Route::get('/units/create', [UnitController::class, 'Create'])->middleware(['permission:create-units'])->name('admin.units.create');
        Route::get('/units/assign-semesters', [UnitController::class, 'assignSemesters'])->middleware(['permission:edit-units'])->name('admin.units.assign-semesters');
        Route::post('/units/assign-semester', [UnitController::class, 'assignToSemester'])->middleware(['permission:edit-units'])->name('admin.units.assign-semester');
        Route::post('/units/remove-semester', [UnitController::class, 'removeFromSemester'])->middleware(['permission:edit-units'])->name('admin.units.remove-semester');
        Route::get('/units/{unit}/edit', [UnitController::class, 'Edit'])->middleware(['permission:edit-units'])->name('admin.units.edit');
        Route::put('/units/{unit}', [UnitController::class, 'Update'])->middleware(['permission:edit-units'])->name('admin.units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'Destroy'])->middleware(['permission:delete-units'])->name('admin.units.destroy');
        
        // Users - PERMISSION-BASED
        Route::middleware(['permission:view-users'])->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        });
        Route::get('/users/create',[UserController::class, 'create'])->middleware(['permission:create-users'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware(['permission:create-users'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware(['permission:edit-users'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware(['permission:edit-users'])->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware(['permission:delete-users'])->name('admin.users.destroy');
        Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->middleware(['permission:delete-users'])->name('admin.users.bulk-delete');
        
        // Dynamic Roles - PERMISSION-BASED
        Route::prefix('roles')->name('roles.')->middleware(['permission:view-roles'])->group(function () {
            Route::get('/dynamic', [DynamicRoleController::class, 'index'])->name('dynamic');
            Route::post('/', [DynamicRoleController::class, 'store'])->middleware(['permission:create-roles'])->name('store');
            Route::post('/bulk-create', [DynamicRoleController::class, 'bulkCreate'])->middleware(['permission:create-roles'])->name('bulk-create');
            Route::put('/{role}', [DynamicRoleController::class, 'update'])->middleware(['permission:edit-roles'])->name('update');
            Route::put('/{role}/permissions', [DynamicRoleController::class, 'updatePermissions'])->middleware(['permission:edit-roles'])->name('update-permissions');
            Route::delete('/{role}', [DynamicRoleController::class, 'destroy'])->middleware(['permission:delete-roles'])->name('destroy');
        });
        
        // Semesters - PERMISSION-BASED
        Route::middleware(['permission:view-semesters'])->group(function () {
            Route::get('/semesters', [SemesterController::class, 'index'])->name('admin.semesters.index');
            Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('admin.semesters.show');
            Route::get('/api/semesters/active', [SemesterController::class, 'getActiveSemesters'])->name('admin.semesters.api.active');
            Route::get('/api/semesters/all', [SemesterController::class, 'getAllSemesters'])->name('admin.semesters.api.all');
        });
        
        Route::post('/semesters', [SemesterController::class, 'store'])->middleware(['permission:create-semesters'])->name('admin.semesters.store');
        Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->middleware(['permission:edit-semesters'])->name('admin.semesters.update');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->middleware(['permission:delete-semesters'])->name('admin.semesters.destroy');
        Route::put('/semesters/{semester}/activate', [SemesterController::class, 'setActive'])->middleware(['permission:edit-semesters'])->name('admin.semesters.activate');
        Route::post('/semesters/bulk-activate', [SemesterController::class, 'bulkActivate'])->middleware(['permission:edit-semesters'])->name('admin.semesters.bulk-activate');
        Route::post('/semesters/bulk-deactivate', [SemesterController::class, 'bulkDeactivate'])->middleware(['permission:edit-semesters'])->name('admin.semesters.bulk-deactivate');
        Route::post('/semesters/bulk-delete', [SemesterController::class, 'bulkDelete'])->middleware(['permission:delete-semesters'])->name('admin.semesters.bulk-delete');

        // Schools Management - PERMISSION-BASED
        Route::middleware(['permission:view-schools'])->group(function () {
            Route::get('/schools', [SchoolController::class, 'index'])->name('admin.schools.index');  // ← THIS WAS MISSING!
            Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('admin.schools.show');
        });
            Route::get('/schools/create', [SchoolController::class, 'create'])->middleware(['permission:create-schools'])->name('admin.schools.create');
            Route::get('/schools/{school}/edit', [SchoolController::class, 'edit'])->middleware(['permission:edit-schools'])->name('admin.schools.edit');
            Route::post('/schools', [SchoolController::class, 'store'])->middleware(['permission:create-schools'])->name('admin.schools.store');
            Route::put('/schools/{school}', [SchoolController::class, 'update'])->middleware(['permission:edit-schools'])->name('admin.schools.update');
            Route::patch('/schools/{school}', [SchoolController::class, 'update'])->middleware(['permission:edit-schools'])->name('admin.schools.patch');
            Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->middleware(['permission:delete-schools'])->name('admin.schools.destroy');
        // Programs - PERMISSION-BASED
        Route::middleware(['permission:view-programs'])->group(function () {
            Route::get('/programs', [ProgramController::class, 'index'])->name('admin.programs.index');
            Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('admin.programs.show');
        });
        Route::post('/programs', [ProgramController::class, 'store'])->middleware(['permission:create-programs'])->name('admin.programs.store');
        Route::get('/programs/create', [ProgramController::class, 'create'])->middleware(['permission:create-programs'])->name('admin.programs.create');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->middleware(['permission:edit-programs'])->name('admin.programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->middleware(['permission:edit-programs'])->name('admin.programs.update');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->middleware(['permission:delete-programs'])->name('admin.programs.destroy');
        
        // Enrollments - PERMISSION-BASED
        Route::middleware(['permission:view-enrollments'])->group(function () {
            Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('admin.enrollments.index');
            Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('admin.enrollments.show');
        });
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware(['permission:create-enrollments'])->name('admin.enrollments.store');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->middleware(['permission:create-enrollments'])->name('admin.enrollments.create');
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->middleware(['permission:edit-enrollments'])->name('admin.enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->middleware(['permission:edit-enrollments'])->name('admin.enrollments.update');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->middleware(['permission:delete-enrollments'])->name('admin.enrollments.destroy');
        
        // Groups - PERMISSION-BASED
        Route::middleware(['permission:view-groups'])->group(function () {
            Route::get('/groups', [GroupController::class, 'index'])->name('admin.groups.index');
            Route::get('/groups/{group}', [GroupController::class, 'show'])->name('admin.groups.show');
        });
        Route::post('/groups', [GroupController::class, 'store'])->middleware(['permission:create-groups'])->name('admin.groups.store');
        Route::get('/groups/create', [GroupController::class, 'create'])->middleware(['permission:create-groups'])->name('admin.groups.create');
        Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->middleware(['permission:edit-groups'])->name('admin.groups.edit');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->middleware(['permission:edit-groups'])->name('admin.groups.update');
        Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->middleware(['permission:delete-groups'])->name('admin.groups.destroy');
        
        // Classes - PERMISSION-BASED
        Route::middleware(['permission:view-classes'])->group(function () {
            Route::get('/classes', [ClassController::class, 'index'])->name('admin.classes.index');
            Route::get('/classes/{class}', [ClassController::class, 'show'])->name('admin.classes.show');
        });
        Route::post('/classes', [ClassController::class, 'store'])->middleware(['permission:create-classes'])->name('admin.classes.store');
        Route::post('/classes/bulk-store', [ClassController::class, 'bulkStore'])->middleware(['permission:create-classes'])->name('admin.classes.bulk-store');
        Route::get('/classes/create', [ClassController::class, 'create'])->middleware(['permission:create-classes'])->name('admin.classes.create');
        Route::get('/classes/{class}/edit', [ClassController::class, 'edit'])->middleware(['permission:edit-classes'])->name('admin.classes.edit');
        Route::put('/classes/{class}', [ClassController::class, 'update'])->middleware(['permission:edit-classes'])->name('admin.classes.update');
        Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->middleware(['permission:delete-classes'])->name('admin.classes.destroy');
        
        // Classrooms - PERMISSION-BASED
        Route::middleware(['permission:view-classrooms'])->group(function () {
            Route::get('/classrooms', [ClassroomController::class, 'index'])->name('admin.classrooms.index');
            Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('admin.classrooms.show');
        });
        Route::post('/classrooms', [ClassroomController::class, 'store'])->middleware(['permission:create-classrooms'])->name('admin.classrooms.store');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->middleware(['permission:edit-classrooms'])->name('admin.classrooms.update');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->middleware(['permission:delete-classrooms'])->name('admin.classrooms.destroy');

        // Buildings - PERMISSION-BASED
        Route::middleware(['permission:view-buildings'])->group(function () {
            Route::get('/buildings', [BuildingController::class, 'index'])->name('admin.buildings.index');
            Route::get('/buildings/trashed', [BuildingController::class, 'getTrashedBuildings'])->name('admin.buildings.trashed');
            Route::get('/buildings/{building}', [BuildingController::class, 'show'])->name('admin.buildings.show');
        });
        Route::post('/buildings', [BuildingController::class, 'store'])->middleware(['permission:create-buildings'])->name('admin.buildings.store');
        Route::put('/buildings/{id}/restore', [BuildingController::class, 'restore'])->middleware(['permission:edit-buildings'])->name('admin.buildings.restore');
        Route::delete('/buildings/{id}/force-delete', [BuildingController::class, 'forceDelete'])->middleware(['permission:delete-buildings'])->name('admin.buildings.force-delete');
        Route::put('/buildings/{building}', [BuildingController::class, 'update'])->middleware(['permission:edit-buildings'])->name('admin.buildings.update');
        Route::put('/buildings/{building}/toggle-status', [BuildingController::class, 'toggleStatus'])->middleware(['permission:edit-buildings'])->name('admin.buildings.toggle-status');
        Route::delete('/buildings/{building}', [BuildingController::class, 'destroy'])->middleware(['permission:delete-buildings'])->name('admin.buildings.destroy');

        // Lecturer Assignments - PERMISSION-BASED
        Route::prefix('lecturerassignment')->name('lecturerassignment.')->middleware(['permission:view-lecturer-assignments'])->group(function () {
            Route::get('/', [LecturerAssignmentController::class, 'index'])->name('index');
            Route::post('/', [LecturerAssignmentController::class, 'store'])->middleware(['permission:create-lecturer-assignments'])->name('store');
            Route::delete('/{unitId}/{semesterId}', [LecturerAssignmentController::class, 'destroy'])->middleware(['permission:delete-lecturer-assignments'])->name('destroy');
            Route::put('/{unitId}/{semesterId}', [LecturerAssignmentController::class, 'update'])->middleware(['permission:edit-lecturer-assignments'])->name('update');
            Route::get('/available-units', [LecturerAssignmentController::class, 'getAvailableUnits'])->name('available-units');
            Route::get('/programs-by-school', [LecturerAssignmentController::class, 'getProgramsBySchool'])->name('programs-by-school');
            Route::get('/classes-by-program-semester', [LecturerAssignmentController::class, 'getClassesByProgramSemester'])->name('classes-by-program-semester');
            Route::get('/workload', [LecturerAssignmentController::class, 'getLecturerWorkload'])->name('workload');
        });

        // System Settings - Admin only
        Route::middleware(['role:Admin'])->group(function () {
            Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
            Route::put('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
        });
        
        // API Routes
        Route::prefix('api')->group(function () {
            Route::get('/units/by-class', [EnrollmentController::class, 'getUnitsForClass']);
            Route::get('/class-capacity', [EnrollmentController::class, 'getClassCapacityInfo']);
            Route::get('/timetable/units/by-class', [ClassTimetableController::class, 'getUnitsByClass']);
            Route::get('/timetable/groups/by-class', [ClassTimetableController::class, 'getGroupsByClass']);
            Route::get('/timetable/groups/by-class-with-counts', [ClassTimetableController::class, 'getGroupsByClassWithCounts']);
            Route::get('/timetable/lecturer-for-unit/{unitId}/{semesterId}', [ClassTimetableController::class, 'getLecturerForUnit']);
            Route::get('/timetable/debug-class-data', [ClassTimetableController::class, 'debugClassData']);
            Route::get('/timetable/programs/by-school', [ClassTimetableController::class, 'getProgramsBySchool']);
            Route::get('/timetable/classes/by-program', [ClassTimetableController::class, 'getClassesByProgram']);
            Route::get('/classes/by-program-semester', function(Request $request) {
                $request->validate([
                    'program_id' => 'required|exists:programs,id',
                    'semester_id' => 'required|exists:semesters,id',
                ]);
                try {
                    $classes = ClassModel::where('program_id', $request->program_id)
                        ->where('semester_id', $request->semester_id)
                        ->where('is_active', true)
                        ->select('id', 'name', 'section', 'year_level', 'capacity')
                        ->orderBy('name')->orderBy('section')
                        ->get()->map(function($class) {
                            return [
                                'id' => $class->id,
                                'name' => $class->name,
                                'section' => $class->section,
                                'display_name' => "{$class->name} Section {$class->section}",
                                'year_level' => $class->year_level,
                                'capacity' => $class->capacity,
                            ];
                        });
                    return response()->json($classes);
                } catch (\Exception $e) {
                    Log::error('Error fetching classes: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to fetch classes'], 500);
                }
            });
            Route::get('/classes/available-names', [ClassController::class, 'getAvailableClassNames']);
            Route::get('/classes/available-sections-for-class', [ClassController::class, 'getAvailableSectionsForClass']);
            Route::get('/schools/all', [SchoolController::class, 'getAllSchools'])->name('admin.schools.api.all');
            Route::get('/lecturerassignments/lecturers', [LecturerAssignmentController::class, 'getAvailableLecturers']);
            Route::get('/lecturerassignments/workload', [LecturerAssignmentController::class, 'getLecturerWorkload']);
            Route::get('/lecturerassignments/units', [LecturerAssignmentController::class, 'getFilteredUnits']);
            Route::get('/lecturerassignments/available-units', [LecturerAssignmentController::class, 'getAvailableUnits']);

            // ✅ ADD CLASS TIMETABLE CONFLICT RESOLUTION API ROUTES HERE
            Route::post('/classtimetables/resolve-conflict', [ClassTimetableController::class, 'resolveConflict'])
                ->middleware(['permission:solve-class-conflicts'])
                ->name('admin.classtimetables.resolve-conflict');
            
            Route::post('/classtimetables/resolve-all-conflicts', [ClassTimetableController::class, 'resolveAllConflicts'])
                ->middleware(['permission:solve-class-conflicts'])
                ->name('admin.classtimetables.resolve-all');
            
            // ✅ ADD EXAM TIMETABLE CONFLICT RESOLUTION API ROUTES HERE
            Route::post('/examtimetables/resolve-conflict', [ExamTimetableController::class, 'resolveConflict'])
                ->middleware(['permission:solve-exam-conflicts'])
                ->name('admin.examtimetables.resolve-conflict');
            
            Route::post('/examtimetables/resolve-all-conflicts', [ExamTimetableController::class, 'resolveAllConflicts'])
                ->middleware(['permission:solve-exam-conflicts'])
                ->name('admin.examtimetables.resolve-all');
        });    
    });

    // ============================================
    // SCES SCHOOL ROUTES - CORRECTED
    // ============================================
    Route::prefix('schools/sces')->name('schools.sces.')->middleware(['auth'])->group(function () {    
        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'SCES');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('SCES');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'SCES');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('SCES', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('SCES', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SCES', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SCES', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('SCES', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'SCES');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'SCES');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'SCES');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'SCES');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'SCES');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'SCES');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'SCES');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'SCES');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('SCES', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('SCES', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'SCES');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'SCES');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'SCES');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'SCES');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'SCES');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'SCES');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'SCES');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'SCES');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'SCES');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'SCES');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'SCES');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'SCES');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'SCES');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'SCES');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'SCES');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'SCES');
                    })->name('download-pdf');

                    // Inside the class-timetables routes group
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    // ✅ FIXED: CLASS TIMETABLE CONFLICT RESOLUTION FOR SCES
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SCES');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // ✅ EXAM TIMETABLES - COMPLETE WITH BULK SCHEDULING FOR SCES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                // ✅ Get classes for bulk scheduling - MUST BE FIRST
                    Route::get('/classes-with-units', function(Program $program) {
                    return app(ExamTimetableController::class)->getClassesWithUnits($program, 'SCES');  // ✅ FIXED
                    })->name('classes-with-units');
    
                    // Index
                    Route::get('/', function(Program $program, Request $request) {
                    return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SCES');
                    })->name('index');
    
                    // Bulk schedule
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'SCES');  // ✅ FIXED
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    // Create
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SCES');
                    })->name('create');
    
                    // Store
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SCES');
                    })->name('store');
    
                    // Show
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SCES');
                    })->name('show');
    
                    // Edit
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SCES');
                    })->name('edit');
    
                    // Update
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SCES');
                    })->name('update');
    
                    // Delete
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SCES');
                    })->name('destroy');
    
                    // Download PDF
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SCES');
                    })->name('download');
    
                    // Conflict resolution
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SCES');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });

    // ============================================
    // SHSS SCHOOL ROUTES (School of Humanities & Social Sciences)
    // ============================================
    Route::prefix('schools/shss')->name('schools.shss.')->middleware(['auth'])->group(function () {    
        
        Route::prefix('electives')->name('electives.')->group(function () {
            Route::get('/', [ElectiveController::class, 'index'])
                ->middleware(['permission:view-programs'])
                ->name('index');
            
            // ✅ ADD THIS NEW ROUTE
            Route::get('/available', [ElectiveController::class, 'getAvailableElectivesForStudent'])
                ->middleware(['permission:view-programs'])
                ->name('available');
            
            // ✅ ADD THIS NEW ROUTE FOR ENROLLMENT
            Route::post('/enroll', [ElectiveController::class, 'enrollStudentInElectives'])
                ->middleware(['permission:create-enrollments'])
                ->name('enroll');
            
            Route::post('/', [ElectiveController::class, 'store'])
                ->middleware(['permission:create-programs'])
                ->name('store');
            
            Route::get('/{elective}', [ElectiveController::class, 'show'])
                ->middleware(['permission:view-programs'])
                ->name('show');
            
            Route::put('/{elective}', [ElectiveController::class, 'update'])
                ->middleware(['permission:edit-programs'])
                ->name('update');
            
            Route::patch('/{elective}/toggle-status', [ElectiveController::class, 'toggleStatus'])
                ->middleware(['permission:edit-programs'])
                ->name('toggle-status');
            
            Route::delete('/{elective}', [ElectiveController::class, 'destroy'])
                ->middleware(['permission:delete-programs'])
                ->name('destroy');
        });

       

        // PROGRAMS - PERMISSION-BASED

        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'SHSS');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('SHSS');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'SHSS');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('SHSS', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('SHSS', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SHSS', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SHSS', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('SHSS', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');
            

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'SHSS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'SHSS');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'SHSS');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'SHSS');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'SHSS');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'SHSS');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'SHSS');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'SHSS');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('SHSS', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('SHSS', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'SHSS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'SHSS');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'SHSS');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'SHSS');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'SHSS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'SHSS');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'SHSS');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'SHSS');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'SHSS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'SHSS');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'SHSS');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'SHSS');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'SHSS');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'SHSS');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'SHSS');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'SHSS');
                    })->name('download-pdf');

                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SHSS');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // EXAM TIMETABLES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                    Route::get('/classes-with-units', function(Program $program) {
                        return app(ExamTimetableController::class)->getClassesWithUnits($program, 'SHSS');
                    })->name('classes-with-units');
    
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SHSS');
                    })->name('index');
    
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'SHSS');
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SHSS');
                    })->name('create');
    
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SHSS');
                    })->name('store');
    
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SHSS');
                    })->name('show');
    
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SHSS');
                    })->name('edit');
    
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SHSS');
                    })->name('update');
    
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SHSS');
                    })->name('destroy');
    
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SHSS');
                    })->name('download');
    
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SHSS');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });

    // ============================================
    // SMS SCHOOL ROUTES (Strathmore Medical School)
    // ============================================
    Route::prefix('schools/sms')->name('schools.sms.')->middleware(['auth'])->group(function () {    
        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'SMS');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('SMS');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'SMS');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('SMS', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('SMS', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SMS', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SMS', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('SMS', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'SMS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'SMS');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'SMS');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'SMS');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'SMS');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'SMS');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'SMS');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'SMS');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('SMS', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('SMS', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'SMS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'SMS');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'SMS');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'SMS');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'SMS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'SMS');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'SMS');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'SMS');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'SMS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'SMS');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'SMS');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'SMS');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'SMS');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'SMS');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'SMS');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'SMS');
                    })->name('download-pdf');

                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SMS');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // EXAM TIMETABLES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                    Route::get('/classes-with-units', function(Program $program) {
                        return app(ExamTimetableController::class)->getClassesWithUnits($program, 'SMS');
                    })->name('classes-with-units');
    
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SMS');
                    })->name('index');
    
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'SMS');
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SMS');
                    })->name('create');
    
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SMS');
                    })->name('store');
    
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SMS');
                    })->name('show');
    
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SMS');
                    })->name('edit');
    
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SMS');
                    })->name('update');
    
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SMS');
                    })->name('destroy');
    
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SMS');
                    })->name('download');
    
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SMS');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });

    // ============================================
    // STH SCHOOL ROUTES (School of Tourism & Hospitality)
    // ============================================
    Route::prefix('schools/sth')->name('schools.sth.')->middleware(['auth'])->group(function () {    
        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'STH');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('STH');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'STH');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('STH', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('STH', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'STH', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'STH', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('STH', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'STH');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'STH');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'STH');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'STH');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'STH');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'STH');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'STH');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'STH');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('STH', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('STH', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'STH');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'STH');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'STH');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'STH');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'STH');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'STH');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'STH');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'STH');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'STH');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'STH');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'STH');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'STH');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'STH');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'STH');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'STH');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'STH');
                    })->name('download-pdf');

                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'STH');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // EXAM TIMETABLES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                    Route::get('/classes-with-units', function(Program $program) {
                        return app(ExamTimetableController::class)->getClassesWithUnits($program, 'STH');
                    })->name('classes-with-units');
    
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'STH');
                    })->name('index');
    
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'STH');
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'STH');
                    })->name('create');
    
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'STH');
                    })->name('store');
    
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'STH');
                    })->name('show');
    
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'STH');
                    })->name('edit');
    
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'STH');
                    })->name('update');
    
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'STH');
                    })->name('destroy');
    
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'STH');
                    })->name('download');
    
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'STH');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });
    // ============================================
    // SBS SCHOOL ROUTES (Strathmore Business School)
    // ============================================
    Route::prefix('schools/sbs')->name('schools.sbs.')->middleware(['auth'])->group(function () {    
        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'SBS');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('SBS');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'SBS');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('SBS', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('SBS', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SBS', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SBS', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('SBS', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'SBS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'SBS');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'SBS');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'SBS');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'SBS');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'SBS');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'SBS');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'SBS');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('SBS', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('SBS', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'SBS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'SBS');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'SBS');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'SBS');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'SBS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'SBS');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'SBS');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'SBS');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'SBS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'SBS');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'SBS');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'SBS');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'SBS');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'SBS');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'SBS');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'SBS');
                    })->name('download-pdf');

                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SBS');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // EXAM TIMETABLES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                    Route::get('/classes-with-units', function(Program $program) {
                        return app(ExamTimetableController::class)->getClassesWithUnits($program, 'SBS');
                    })->name('classes-with-units');
    
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SBS');
                    })->name('index');
    
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'SBS');
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SBS');
                    })->name('create');
    
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SBS');
                    })->name('store');
    
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SBS');
                    })->name('show');
    
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SBS');
                    })->name('edit');
    
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SBS');
                    })->name('update');
    
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SBS');
                    })->name('destroy');
    
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SBS');
                    })->name('download');
    
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SBS');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });
    // ============================================
    // SLS SCHOOL ROUTES (School of Law Studies)
    // ============================================
    Route::prefix('schools/sls')->name('schools.sls.')->middleware(['auth'])->group(function () {    
        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'SLS');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('SLS');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'SLS');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('SLS', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('SLS', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SLS', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SLS', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('SLS', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'SLS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'SLS');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'SLS');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'SLS');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'SLS');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'SLS');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'SLS');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'SLS');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('SLS', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('SLS', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'SLS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'SLS');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'SLS');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'SLS');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'SLS');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'SLS');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'SLS');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'SLS');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'SLS');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'SLS');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'SLS');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'SLS');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'SLS');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'SLS');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'SLS');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'SLS');
                    })->name('download-pdf');

                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SLS');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // EXAM TIMETABLES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                    Route::get('/classes-with-units', function(Program $program) {
                        return app(ExamTimetableController::class)->getClassesWithUnits($program, 'SLS');
                    })->name('classes-with-units');
    
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SLS');
                    })->name('index');
    
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'SLS');
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SLS');
                    })->name('create');
    
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SLS');
                    })->name('store');
    
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SLS');
                    })->name('show');
    
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SLS');
                    })->name('edit');
    
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SLS');
                    })->name('update');
    
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SLS');
                    })->name('destroy');
    
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SLS');
                    })->name('download');
    
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SLS');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });

    // ============================================
    // SI SCHOOL ROUTES (Strathmore Institute)
    // ============================================
    Route::prefix('schools/si')->name('schools.si.')->middleware(['auth'])->group(function () {    
        Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
            Route::get('/', function(Request $request) {
                return app(ProgramController::class)->index($request, 'SI');
            })->name('index');
        
            Route::get('/create', function() {
                return app(ProgramController::class)->create('SI');
            })->middleware(['permission:create-programs'])->name('create');
        
            Route::post('/', function(Request $request) {
                return app(ProgramController::class)->store($request, 'SI');
            })->middleware(['permission:create-programs'])->name('store');
        
            Route::get('/{program}', function(Program $program) {
                return app(ProgramController::class)->show('SI', $program);
            })->name('show');
        
            Route::get('/{program}/edit', function(Program $program) {
                return app(ProgramController::class)->edit('SI', $program);
            })->middleware(['permission:edit-programs'])->name('edit');
        
            Route::put('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SI', $program);
            })->middleware(['permission:edit-programs'])->name('update');
        
            Route::patch('/{program}', function(Request $request, Program $program) {
                return app(ProgramController::class)->update($request, 'SI', $program);
            })->middleware(['permission:edit-programs'])->name('patch');
        
            Route::delete('/{program}', function(Program $program) {
                return app(ProgramController::class)->destroy('SI', $program);
            })->middleware(['permission:delete-programs'])->name('destroy');

            Route::prefix('{program}')->group(function () {            
                // UNITS
                Route::prefix('units')->name('units.')->middleware(['permission:view-units'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnits($program, $request, 'SI');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(UnitController::class)->createProgramUnit($program, 'SI');
                    })->middleware(['permission:create-units'])->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->storeProgramUnit($program, $request, 'SI');
                    })->middleware(['permission:create-units'])->name('store');
                
                    Route::get('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->showProgramUnit($program, $unit, 'SI');
                    })->name('show');
                
                    Route::get('/{unit}/edit', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->editProgramUnit($program, $unit, 'SI');
                    })->middleware(['permission:edit-units'])->name('edit');
                
                    Route::put('/{unit}', function(Program $program, Unit $unit, Request $request) {
                        return app(UnitController::class)->updateProgramUnit($program, $unit, $request, 'SI');
                    })->middleware(['permission:edit-units'])->name('update');
                
                    Route::delete('/{unit}', function(Program $program, Unit $unit) {
                        return app(UnitController::class)->destroyProgramUnit($program, $unit, 'SI');
                    })->middleware(['permission:delete-units'])->name('destroy');
                });

                Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(UnitController::class)->programUnitAssignments($program, $request, 'SI');
                    })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                    Route::post('/assign', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('edit-units') ||
                          $user->can('assign-units'))) {
                                abort(403, 'Unauthorized to assign units.');
                           }                    
                        return app(UnitController::class)->assignProgramUnitsToSemester('SI', $program, $request);
                    })->middleware(['auth'])->name('assign');
                
                    Route::post('/remove', function(Program $program, Request $request) {
                        $user = auth()->user();
                        if (!($user->hasRole('Admin') || 
                          $user->can('manage-units') || 
                          $user->can('delete-units'))) {
                            abort(403, 'Unauthorized to remove unit assignments.');
                            }
                    
                        return app(UnitController::class)->removeProgramUnitsFromSemester('SI', $program, $request);
                    })->middleware(['auth'])->name('remove');
                });

                // CLASSES
                Route::prefix('classes')->name('classes.')->middleware(['permission:view-classes'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->programClasses($program, $request, 'SI');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassController::class)->storeProgramClass($program, $request, 'SI');
                    })->middleware(['permission:create-classes'])->name('store');
                
                    Route::put('/{class}', function(Program $program, ClassModel $class, Request $request) {
                        return app(ClassController::class)->updateProgramClass($program, $class, $request, 'SI');
                    })->middleware(['permission:edit-classes'])->name('update');
                
                    Route::delete('/{class}', function(Program $program, ClassModel $class) {
                        return app(ClassController::class)->destroyProgramClass($program, $class, 'SI');
                    })->middleware(['permission:delete-classes'])->name('destroy');
                });

                // ENROLLMENTS
                Route::prefix('enrollments')->name('enrollments.')->middleware(['permission:view-enrollments'])->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->programEnrollments($program, $request, 'SI');
                    })->name('index');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(EnrollmentController::class)->storeProgramEnrollment($program, $request, 'SI');
                    })->middleware(['permission:create-enrollments'])->name('store');
                
                    Route::put('/{enrollment}', function(Program $program, $enrollment, Request $request) {
                        return app(EnrollmentController::class)->updateProgramEnrollment($program, $enrollment, $request, 'SI');
                    })->middleware(['permission:edit-enrollments'])->name('update');
                
                    Route::delete('/{enrollment}', function(Program $program, $enrollment) {
                        return app(EnrollmentController::class)->destroyProgramEnrollment($program, $enrollment, 'SI');
                    })->middleware(['permission:delete-enrollments'])->name('destroy');
                });

                // CLASS TIMETABLES
                Route::prefix('class-timetables')->name('class-timetables.')->group(function () {
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->programClassTimetables($program, $request, 'SI');
                    })->name('index');
                
                    Route::get('/create', function(Program $program) {
                        return app(ClassTimetableController::class)->createProgramClassTimetable($program, 'SI');
                    })->name('create');
                
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->storeProgramClassTimetable($program, $request, 'SI');
                    })->name('store');
                
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->showProgramClassTimetable($program, $timetable, 'SI');
                    })->name('show');
                
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->editProgramClassTimetable($program, $timetable, 'SI');
                    })->name('edit');
                
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ClassTimetableController::class)->updateProgramClassTimetable($program, $timetable, $request, 'SI');
                    })->name('update');
                
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ClassTimetableController::class)->destroyProgramClassTimetable($program, $timetable, 'SI');
                    })->name('destroy');
                
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ClassTimetableController::class)->downloadProgramClassTimetablePDF($program, 'SI');
                    })->name('download-pdf');

                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->bulkSchedule($request);
                    })->middleware(['permission:create-class-timetables'])->name('bulk-schedule');

                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-conflict');
                
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ClassTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SI');
                    })->middleware(['permission:solve-class-conflicts'])->name('resolve-all');
                });

                // EXAM TIMETABLES
                Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                    Route::get('/classes-with-units', function(Program $program) {
                        return app(ExamTimetableController::class)->getClassesWithUnits($program, 'SI');
                    })->name('classes-with-units');
    
                    Route::get('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SI');
                    })->name('index');
    
                    Route::post('/bulk-schedule', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->bulkScheduleExams($program, $request, 'SI');
                    })->middleware(['permission:create-exam-timetables'])->name('bulk-schedule');
    
                    Route::get('/create', function(Program $program) {
                        return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SI');
                    })->name('create');
    
                    Route::post('/', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SI');
                    })->name('store');
    
                    Route::get('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SI');
                    })->name('show');
    
                    Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SI');
                    })->name('edit');
    
                    Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                        return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SI');
                    })->name('update');
    
                    Route::delete('/{timetable}', function(Program $program, $timetable) {
                        return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SI');
                    })->name('destroy');
    
                    Route::get('/download/pdf', function(Program $program) {
                        return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SI');
                    })->name('download');
    
                    Route::post('/resolve-conflict', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveConflict($request);
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-conflict');
    
                    Route::post('/resolve-all-conflicts', function(Program $program, Request $request) {
                        return app(ExamTimetableController::class)->resolveAllProgramConflicts($program, $request, 'SI');
                    })->middleware(['permission:solve-exam-conflicts'])->name('resolve-all');
                });
            });
        });
    });

    // STUDENTS ROUTES
    Route::prefix('student')->middleware(['role:Student'])->group(function () {
        Route::get('/', [StudentController::class, 'studentDashboard'])->name('student.dashboard');
        Route::get('/enrollments', [StudentController::class, 'showAvailableUnits'])->name('student.enrollments');
        Route::post('/enrollments', [StudentController::class, 'enrollInUnit'])->name('student.enrollments.store');
        Route::delete('/enrollments/{enrollment}', [StudentController::class, 'dropUnit'])->name('student.enrollments.drop');
        Route::get('/api/units/{unit}/classes', [StudentController::class, 'getAvailableClassesForUnit'])->name('student.units.classes');
        Route::get('/examtimetable', [StudentController::class, 'myExams'])->name('student.examtimetable');
        Route::get('/timetable', [StudentController::class, 'myTimetable'])->name('student.timetable');
        Route::get('/download-classtimetable', [ClassTimetableController::class, 'downloadStudentPDF'])->name('student.classtimetable.download');
        Route::get('/download-examtimetable', [ExamTimetableController::class, 'downloadAllPDF'])->name('student.examtimetable.download');
        Route::get('/profile', [StudentController::class, 'profile'])->name('student.profile');

        // Student elective enrollment
        Route::get('/electives/available', [StudentController::class, 'availableElectives'])
            ->name('student.electives.available');
        
        Route::post('/electives/enroll', [StudentController::class, 'enrollInElective'])
            ->name('student.electives.enroll');
            
        
        // API routes for student
        Route::prefix('api')->group(function () {
            Route::get('/classes/by-program-semester', function(Request $request) {
                $request->validate([
                    'program_id' => 'required|exists:programs,id',
                    'semester_id' => 'required|exists:semesters,id',
                ]);
                try {
                    $classes = ClassModel::where('program_id', $request->program_id)
                        ->where('semester_id', $request->semester_id)
                        ->where('is_active', true)
                        ->select('id', 'name', 'section', 'year_level', 'capacity', 'program_id', 'semester_id')
                        ->orderBy('name')->orderBy('section')
                        ->get()->map(function($class) {
                            return [
                                'id' => $class->id,
                                'name' => $class->name,
                                'section' => $class->section,
                                'display_name' => "{$class->name} Section {$class->section}",
                                'year_level' => $class->year_level,
                                'capacity' => $class->capacity,
                                'program_id' => $class->program_id,
                                'semester_id' => $class->semester_id,
                            ];
                        });
                    return response()->json($classes);
                } catch (\Exception $e) {
                    Log::error('Error fetching classes for student: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to fetch classes'], 500);
                }
            });
            
            Route::get('/units/by-class', function(Request $request) {
                $request->validate([
                    'class_id' => 'required|exists:classes,id',
                    'semester_id' => 'required|exists:semesters,id',
                ]);
                try {
                    $units = Unit::whereHas('assignments', function($query) use ($request) {
                        $query->where('class_id', $request->class_id)
                              ->where('semester_id', $request->semester_id)
                              ->where('is_active', true);
                    })->with(['school', 'program'])->get();
                    return response()->json($units);
                } catch (\Exception $e) {
                    Log::error('Error fetching units for student: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to fetch units'], 500);
                }
            });
        });
    });

    // LECTURER ROUTES
    Route::prefix('lecturer')->middleware(['role:Lecturer'])->group(function () {
        Route::get('/', [DashboardController::class, 'lecturerDashboard'])->name('lecturer.dashboard');
        Route::get('/classes', [LecturerController::class, 'myClasses'])->name('lecturer.classes');
        Route::get('/classes/{unitId}/students', [LecturerController::class, 'classStudents'])->name('lecturer.class.students');
        Route::get('/class-timetable', [LecturerController::class, 'viewClassTimetable'])->name('lecturer.class-timetable');
        Route::get('/exam-supervision', [LecturerController::class, 'examSupervision'])->name('lecturer.exam-supervision');
        Route::get('/download-examtimetable', [ExamTimetableController::class, 'downloadAllPDF'])->name('lecturer.examtimetable.download');
        Route::get('/profile', [LecturerController::class, 'profile'])->name('lecturer.profile');
    });

    // CLASS TIMETABLE OFFICE ROUTES
    Route::prefix('classtimetables')->middleware(['role:Class Timetable office'])->group(function () {
        Route::get('/', [DashboardController::class, 'classtimetablesDashboard'])
            ->name('classtimetables.dashboard');
        
        Route::get('/manage', [ClassTimetableController::class, 'index'])
            ->name('classtimetables.index');
        
        Route::get('/manage/create', [ClassTimetableController::class, 'create'])
            ->name('classtimetables.create');
        
        Route::post('/manage', [ClassTimetableController::class, 'store'])
            ->name('classtimetables.store');
        
        Route::get('/manage/{timetable}', [ClassTimetableController::class, 'show'])
            ->name('classtimetables.show');
        
        Route::get('/manage/{timetable}/edit', [ClassTimetableController::class, 'edit'])
            ->name('classtimetables.edit');
        
        Route::put('/manage/{timetable}', [ClassTimetableController::class, 'update'])
            ->name('classtimetables.update');
        
        Route::delete('/manage/{timetable}', [ClassTimetableController::class, 'destroy'])
            ->name('classtimetables.destroy');
        
        Route::get('/download/pdf', [ClassTimetableController::class, 'downloadPDF'])
            ->name('classtimetables.download-pdf');
        
        Route::get('/conflicts', [ClassTimetableController::class, 'showConflicts'])
            ->name('classtimetables.conflicts');
             // ✅ ADD CLASS TIMETABLE CONFLICT RESOLUTION ROUTES HERE
        Route::post('/resolve-conflict', [ClassTimetableController::class, 'resolveConflict'])
            ->name('classtimetables.resolve-conflict');
    
        Route::post('/resolve-all-conflicts', [ClassTimetableController::class, 'resolveAllConflicts'])
            ->name('classtimetables.resolve-all');

        
    });

    // EXAM TIMETABLE OFFICE ROUTES
Route::prefix('examoffice')->middleware(['role:Exam Office'])->group(function () {
    Route::get('/', [DashboardController::class, 'examofficeDashboard'])
        ->name('examoffice.dashboard');

      Route::get('/download/pdf', [ExamTimetableController::class, 'downloadAllPDF'])
        ->name('exam-timetables.pdf');
    
    // ✅ EXAM TIMETABLE ROUTES (you had these before!)
    Route::get('/manage', [ExamTimetableController::class, 'index'])
        ->name('exam-timetables.index');
    Route::get('/manage/create', [ExamTimetableController::class, 'create'])
        ->name('exam-timetables.create');
    Route::post('/manage', [ExamTimetableController::class, 'store'])
        ->name('exam-timetables.store');
    Route::get('/manage/{timetable}', [ExamTimetableController::class, 'show'])
        ->name('exam-timetables.show');
    Route::get('/manage/{timetable}/edit', [ExamTimetableController::class, 'edit'])
        ->name('exam-timetables.edit');
    Route::put('/manage/{timetable}', [ExamTimetableController::class, 'update'])
        ->name('exam-timetables.update');
    Route::delete('/manage/{timetable}', [ExamTimetableController::class, 'destroy'])
        ->name('exam-timetables.destroy');
    
          // Conflict resolution routes
    Route::get('/conflicts', [ExamTimetableController::class, 'showConflicts'])
        ->name('exam-timetables.conflicts'); 
    Route::post('/resolve-conflict', [ExamTimetableController::class, 'resolveConflict'])
        ->name('exam-timetables.resolve-conflict');
    Route::post('/resolve-all-conflicts', [ExamTimetableController::class, 'resolveAllConflicts'])
        ->name('exam-timetables.resolve-all');
    
   // ✅ EXAMROOM ROUTES - CORRECT ORDER
Route::get('/examrooms', [ExamroomController::class, 'index'])
    ->name('examrooms.index');
Route::get('/examrooms/create', [ExamroomController::class, 'create'])  // ← This MUST come before {examroom}
    ->name('examrooms.create');
Route::post('/examrooms', [ExamroomController::class, 'store'])
    ->name('examrooms.store');
Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])  // ← Edit before show
    ->name('examrooms.edit');
Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])  // ← This should be LAST
    ->name('examrooms.show');
Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])
    ->name('examrooms.update');
Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])
    ->name('examrooms.destroy');

     // View all scheduling failures
    Route::get('/exam-scheduling-failures', [ExamTimetableController::class, 'showSchedulingFailures'])
        ->name('exam-scheduling-failures.index')->middleware('permission:view-exam-timetables');

    // Update failure status (resolve, retry, ignore)
    Route::patch('/exam-scheduling-failures/{id}/status', [
        ExamTimetableController::class, 
        'updateFailureStatus'
    ])->name('exam-scheduling-failures.update-status')
      ->middleware('permission:edit-exam-timetables');

    // Delete single failure
    Route::delete('/exam-scheduling-failures/{id}', [
        ExamTimetableController::class, 
        'deleteFailure'
    ])->name('exam-scheduling-failures.destroy')
      ->middleware('permission:delete-exam-timetables');

    // Delete all failures from a batch
    Route::delete('/exam-scheduling-failures/batch/{batchId}', [
        ExamTimetableController::class, 
        'deleteBatchFailures'
    ])->name('exam-scheduling-failures.delete-batch')
      ->middleware('permission:delete-exam-timetables');

    // Export failures to CSV
    Route::get('/exam-scheduling-failures/export', [
        ExamTimetableController::class, 
        'exportFailures'
    ])->name('exam-scheduling-failures.export')
      ->middleware('permission:view-exam-timetables');

});
    Route::prefix('classtimeslot')
        ->middleware(['auth', 'permission:view-classtimeslots'])
        ->group(function () {
            Route::get('/', [ClassTimeSlotController::class, 'index'])
                ->name('classtimeslot.index');
            Route::post('/', [ClassTimeSlotController::class, 'store'])
                ->middleware(['permission:create-classtimeslots'])
                ->name('classtimeslot.store');
            Route::put('/{id}', [ClassTimeSlotController::class, 'update'])
                ->middleware(['permission:edit-classtimeslots'])
                ->name('classtimeslot.update');
            Route::delete('/{id}', [ClassTimeSlotController::class, 'destroy'])
                ->middleware(['permission:delete-classtimeslot'])
                ->name('classtimeslot.destroy');
            Route::get('/{id}', [ClassTimeSlotController::class, 'show'])
                ->name('classtimeslot.show');
        });

        // View all scheduling failures
    Route::get('/exam-scheduling-failures', [ExamTimetableController::class, 'showSchedulingFailures'])
        ->name('exam-scheduling-failures.index')->middleware('permission:view-exam-timetables');

    // Update failure status (resolve, retry, ignore)
    Route::patch('/exam-scheduling-failures/{id}/status', [
        ExamTimetableController::class, 
        'updateFailureStatus'
    ])->name('exam-scheduling-failures.update-status')
      ->middleware('permission:edit-exam-timetables');

    // Delete single failure
    Route::delete('/exam-scheduling-failures/{id}', [
        ExamTimetableController::class, 
        'deleteFailure'
    ])->name('exam-scheduling-failures.destroy')
      ->middleware('permission:delete-exam-timetables');

    // Delete all failures from a batch
    Route::delete('/exam-scheduling-failures/batch/{batchId}', [
        ExamTimetableController::class, 
        'deleteBatchFailures'
    ])->name('exam-scheduling-failures.delete-batch')
      ->middleware('permission:delete-exam-timetables');

    // Export failures to CSV
    Route::get('/exam-scheduling-failures/export', [
        ExamTimetableController::class, 
        'exportFailures'
    ])->name('exam-scheduling-failures.export')
      ->middleware('permission:view-exam-timetables');

    // CATCH-ALL ROUTE
    Route::get('/{any}', function () {
        return Inertia::render('NotFound');
    })->where('any', '.*')->name('not-found');
});