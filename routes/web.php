<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\LecturerAssignmentController;
use App\Http\Controllers\ClassTimetableController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\DynamicRoleController;
use App\Http\Controllers\DynamicPermissionController;
use App\Http\Controllers\SettingsController;
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

// ===================================================================
// PUBLIC ROUTES
// ===================================================================

require __DIR__.'/auth.php';

// ===================================================================
// AUTHENTICATED ROUTES
// ===================================================================

Route::middleware(['auth'])->group(function () {

    // ===============================================================
    // CORE AUTHENTICATION & USER ROUTES
    // ===============================================================
    
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // ===============================================================
    // PROFILE ROUTES
    // ===============================================================
    
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // ===============================================================
    // MAIN DASHBOARD ROUTES
    // ===============================================================
    
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        if ($user->hasRole('Admin')) {
            return redirect()->route('admin.dashboard');
        }
        
        if ($user->hasRole('Exam office')) {
            return redirect()->route('exam-office.dashboard');
        }
        
        // Check for Faculty Admin roles and redirect to appropriate school
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $faculty = str_replace('Faculty Admin - ', '', $role);
                $schoolRoute = match($faculty) {
                    'SCES' => 'facultyadmin.sces.dashboard',
                    'SBS' => 'facultyadmin.sbs.dashboard',
                    default => null
                };
                
                if ($schoolRoute) {
                    return redirect()->route($schoolRoute);
                }
            }
        }
        
        if ($user->hasRole('Lecturer')) {
            return redirect()->route('lecturer.dashboard');
        }
        
        if ($user->hasRole('Student')) {
            return redirect()->route('student.dashboard');
        }
        
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ===============================================================
    // ADMIN ROUTES
    // ===============================================================
    
    Route::prefix('admin')->middleware(['role:Admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'adminDashboard'])
            ->name('admin.dashboard'); 
    
        // Dynamic Permissions
        Route::get('/permissions/dynamic', [DynamicPermissionController::class, 'index'])->name('permissions.dynamic.index');
        Route::post('/permissions', [DynamicPermissionController::class, 'store'])->name('permissions.store');
        Route::post('/permissions/bulk', [DynamicPermissionController::class, 'bulkCreate'])->name('permissions.bulk');
        Route::put('/permissions/{permission}', [DynamicPermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [DynamicPermissionController::class, 'destroy'])->name('permissions.destroy');
    
        // User Role Management - CHANGE THIS ROUTE
        Route::get('/users/roles', [RoleManagementController::class, 'index'])->name('users.roles.index');
        Route::put('/users/{user}/roles', [RoleManagementController::class, 'updateUserRole'])->name('users.roles.update');
        Route::delete('/users/{user}/roles', [RoleManagementController::class, 'removeUserRole'])->name('users.roles.remove');
        Route::post('/users/roles/bulk-assign', [RoleManagementController::class, 'bulkAssignRole'])->name('users.roles.bulk');

        // Admin Units Routes
        Route::get('/units', [UnitController::class, 'index'])->name('admin.units.index');
        Route::post('/units', [UnitController::class, 'Store'])->name('admin.units.store');
        Route::get('/units/create', [UnitController::class, 'Create'])->name('admin.units.create');
        Route::get('/units/assign-semesters', [UnitController::class, 'assignSemesters'])->name('admin.units.assign-semesters');
        Route::post('/units/assign-semester', [UnitController::class, 'assignToSemester'])->name('admin.units.assign-semester');
        Route::post('/units/remove-semester', [UnitController::class, 'removeFromSemester'])->name('admin.units.remove-semester');
        Route::get('/units/{unit}', [UnitController::class, 'Show'])->name('admin.units.show');
        Route::get('/units/{unit}/edit', [UnitController::class, 'Edit'])->name('admin.units.edit');
        Route::put('/units/{unit}', [UnitController::class, 'Update'])->name('admin.units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'Destroy'])->name('admin.units.destroy');
        
        
        // Users Management
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create',[UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->name('admin.users.bulk-delete');
        
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/dynamic', [DynamicRoleController::class, 'index'])->name('dynamic');
            Route::post('/', [DynamicRoleController::class, 'store'])->name('store');
            Route::post('/bulk-create', [DynamicRoleController::class, 'bulkCreate'])->name('bulk-create');
            Route::put('/{role}', [DynamicRoleController::class, 'update'])->name('update');
            Route::put('/{role}/permissions', [DynamicRoleController::class, 'updatePermissions'])->name('update-permissions');
            Route::delete('/{role}', [DynamicRoleController::class, 'destroy'])->name('destroy');
        });
        // Semesters
        Route::get('/semesters', [SemesterController::class, 'index'])->name('admin.semesters.index');
        Route::post('/semesters', [SemesterController::class, 'store'])->name('admin.semesters.store');
        Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('admin.semesters.show');
        Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('admin.semesters.update');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('admin.semesters.destroy');
        Route::put('/semesters/{semester}/activate', [SemesterController::class, 'setActive'])->name('admin.semesters.activate');
        
        // Schools
        Route::get('/schools', [SchoolController::class, 'index'])->name('admin.schools.index');
        Route::get('/schools/create', [SchoolController::class, 'create'])->name('admin.schools.create');
        Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('admin.schools.show');
        Route::get('/schools/{school}/edit', [SchoolController::class, 'edit'])->name('admin.schools.edit');
        Route::post('/schools', [SchoolController::class, 'store'])->name('admin.schools.store');
        Route::put('/schools/{school}', [SchoolController::class, 'update'])->name('admin.schools.update');
        Route::patch('/schools/{school}', [SchoolController::class, 'update'])->name('admin.schools.patch');
        Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->name('admin.schools.destroy');
        
        // Programs
        Route::get('/programs', [ProgramController::class, 'index'])->name('admin.programs.index');
        Route::post('/programs', [ProgramController::class, 'store'])->name('admin.programs.store');
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('admin.programs.create');
        Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('admin.programs.show');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('admin.programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('admin.programs.update');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('admin.programs.destroy');
        
        // Enrollment Routes
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('admin.enrollments.index');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('admin.enrollments.store');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('admin.enrollments.create');
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('admin.enrollments.show');
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('admin.enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('admin.enrollments.update');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('admin.enrollments.destroy');
        
        // Group Routes        
        Route::get('/groups', [GroupController::class, 'index'])->name('admin.groups.index');
        Route::post('/groups', [GroupController::class, 'store'])->name('admin.groups.store');
        Route::get('/groups/create', [GroupController::class, 'create'])->name('admin.groups.create');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->name('admin.groups.show');
        Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->name('admin.groups.edit');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->name('admin.groups.update');
        Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('admin.groups.destroy');
        
        // Class Routes
        Route::get('/classes', [ClassController::class, 'index'])->name('admin.classes.index');
        Route::post('/classes', [ClassController::class, 'store'])->name('admin.classes.store');
        Route::post('/classes/bulk-store', [ClassController::class, 'bulkStore'])->name('admin.classes.bulk-store');
        Route::get('/classes/create', [ClassController::class, 'create'])->name('admin.classes.create');
        Route::get('/classes/{class}', [ClassController::class, 'show'])->name('admin.classes.show');
        Route::get('/classes/{class}/edit', [ClassController::class, 'edit'])->name('admin.classes.edit');
        Route::put('/classes/{class}', [ClassController::class, 'update'])->name('admin.classes.update');
        Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->name('admin.classes.destroy');
        // Classrooms Management Routes
        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('admin.classrooms.index');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('admin.classrooms.store');
        Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('admin.classrooms.show');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('admin.classrooms.update');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('admin.classrooms.destroy');

        // classtimetables
        Route::get('/classtimetable', [ClassTimetableController::class, 'index'])->name('admin.classtimetable.index');
        Route::post('/classtimetable', [ClassTimetableController::class, 'store'])->name('admin.classtimetable.store');
        Route::get('/classtimetable/create', [ClassTimetableController::class, 'create'])->name('admin.classtimetable.create');
        Route::get('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'show'])->name('admin.classtimetable.show');
        Route::get('/classtimetable/{classtimetable}/edit', [ClassTimetableController::class, 'edit'])->name('admin.classtimetable.edit');
        Route::put('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'update'])->name('admin.classtimetable.update');
        Route::delete('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'destroy'])->name('admin.classtimetable.destroy');

            // Lecturer Assignment routes
   // Lecturer Assignment routes (single assignment only)
// Lecturer Assignment routes (single assignment only)
Route::prefix('lecturerassignment')->name('lecturerassignment.')->group(function () {
    Route::get('/', [LecturerAssignmentController::class, 'index'])->name('index');
    Route::post('/', [LecturerAssignmentController::class, 'store'])->name('store');
    Route::put('/{unitId}/{semesterId}', [LecturerAssignmentController::class, 'update'])->name('update');
    Route::delete('/{unitId}/{semesterId}', [LecturerAssignmentController::class, 'destroy'])->name('destroy');
    
    // API endpoints for filtering
    Route::get('/available-units', [LecturerAssignmentController::class, 'getAvailableUnits'])->name('available-units');
    Route::get('/programs-by-school', [LecturerAssignmentController::class, 'getProgramsBySchool'])->name('programs-by-school');
    Route::get('/classes-by-program-semester', [LecturerAssignmentController::class, 'getClassesByProgramSemester'])->name('classes-by-program-semester');
    Route::get('/workload', [LecturerAssignmentController::class, 'getLecturerWorkload'])->name('workload');
});


        // System Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
            
        // API Routes for AJAX calls
        Route::prefix('api')->group(function () {
            Route::get('/units/by-class', [EnrollmentController::class, 'getUnitsForClass']);
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
                        ->orderBy('name')
                        ->orderBy('section')
                        ->get()
                        ->map(function($class) {
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
            // NEW: Class capacity information route
            Route::get('/class-capacity', [EnrollmentController::class, 'getClassCapacityInfo']);
            Route::get('/classes/available-names', [ClassController::class, 'getAvailableClassNames']);
            Route::get('/classes/available-sections-for-class', [ClassController::class, 'getAvailableSectionsForClass']);
            Route::get('/schools/all', [SchoolController::class, 'getAllSchools'])->name('admin.schools.api.all');
            Route::get('/lecturer-assignments/lecturers', [LecturerAssignmentController::class, 'getAvailableLecturers']);
            Route::get('/lecturer-assignments/workload', [LecturerAssignmentController::class, 'getLecturerWorkload']);
            Route::get('/lecturer-assignments/units', [LecturerAssignmentController::class, 'getFilteredUnits']);
            Route::get('/lecturer-assignments/available-units', [LecturerAssignmentController::class, 'getAvailableUnits']);
        });
    });
});

    // ===============================================================
    // SCHOOL PROGRAMS MANAGEMENT
    // ===============================================================

    Route::prefix('schools/sces')->name('schools.sces.programs.')->group(function () {
        Route::get('programs', function(Request $request) {
            return app(ProgramController::class)->index($request, 'SCES');
        })->name('index');
        
        Route::get('programs/create', function() {
            return app(ProgramController::class)->create('SCES');
        })->name('create');
        
        Route::get('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->show('SCES', $program);
        })->name('show');
        
        Route::get('programs/{program}/edit', function(Program $program) {
            return app(ProgramController::class)->edit('SCES', $program);
        })->name('edit');
        
        Route::post('programs', function(Request $request) {
            return app(ProgramController::class)->store($request, 'SCES');
        })->name('store');
        
        Route::put('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SCES', $program);
        })->name('update');
        
        Route::patch('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SCES', $program);
        })->name('patch');
        
        Route::delete('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->destroy('SCES', $program);
        })->name('destroy');
        
        Route::get('api/programs', function() {
            return app(ProgramController::class)->getAllPrograms('SCES');
        })->name('api.all');
    });


// ===============================================================
// CATCH-ALL ROUTE
// ===============================================================

Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');