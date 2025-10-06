<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExamTimetableController;
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
        
        if ($user->hasRole('Exam office')) {
            return redirect()->route('exam-office.dashboard');
        }
        
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
        
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // SCHOOL ADMIN DASHBOARD
    Route::prefix('SchoolAdmin')->group(function() {
        Route::get('/dashboard', [DashboardController::class, 'scesDashboard'])
            ->middleware(['auth', 'role:Faculty Admin - SCES|Faculty Admin - SBS|Faculty Admin - SLS'])
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
        });    
    });

    // SCHOOL-SPECIFIC ROUTES (SCES, SBS, SLS)

   // SCES SCHOOL ROUTES
Route::prefix('schools/sces')->name('schools.sces.')->middleware(['auth'])->group(function () {
    
    // ============================================
    // PROGRAMS ROUTES
    // ============================================
    Route::prefix('programs')->name('programs.')->middleware(['permission:view-programs'])->group(function () {
        // Program CRUD
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

        // ============================================
        // PROGRAM-SPECIFIC NESTED ROUTES
        // ============================================
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

            // UNIT ASSIGNMENT TO SEMESTERS
            Route::prefix('unitassignment')->name('unitassignment.')->group(function () {
                Route::get('/', function(Program $program, Request $request) {
                    return app(UnitController::class)->programUnitAssignments($program, $request, 'SCES');
                })->middleware(['permission:view-units'])->name('AssignSemesters');
                
                Route::post('/assign', function(Program $program, Request $request) {
    return app(UnitController::class)->assignProgramUnitsToSemester('SCES', $program, $request);
})->middleware(['permission:edit-units'])->name('assign');
                
                Route::post('/remove', function(Program $program, Request $request) {
    return app(UnitController::class)->removeProgramUnitsFromSemester('SCES', $program, $request);
})->middleware(['permission:delete-units'])->name('remove');
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

            // ENROLLMENTS (Program-specific)
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
            // ✅ AFTER
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
                })->name('download');
            });

            // EXAM TIMETABLES
            Route::prefix('exam-timetables')->name('exam-timetables.')->group(function () {
                Route::get('/', function(Program $program, Request $request) {
                    return app(ExamTimetableController::class)->programExamTimetables($program, $request, 'SCES');
                })->name('index');
                
                Route::get('/create', function(Program $program) {
                    return app(ExamTimetableController::class)->createProgramExamTimetable($program, 'SCES');
                })->name('create');
                
                Route::post('/', function(Program $program, Request $request) {
                    return app(ExamTimetableController::class)->storeProgramExamTimetable($program, $request, 'SCES');
                })->name('store');
                
                Route::get('/{timetable}', function(Program $program, $timetable) {
                    return app(ExamTimetableController::class)->showProgramExamTimetable($program, $timetable, 'SCES');
                })->name('show');
                
                Route::get('/{timetable}/edit', function(Program $program, $timetable) {
                    return app(ExamTimetableController::class)->editProgramExamTimetable($program, $timetable, 'SCES');
                })->name('edit');
                
                Route::put('/{timetable}', function(Program $program, $timetable, Request $request) {
                    return app(ExamTimetableController::class)->updateProgramExamTimetable($program, $timetable, $request, 'SCES');
                })->name('update');
                
                Route::delete('/{timetable}', function(Program $program, $timetable) {
                    return app(ExamTimetableController::class)->destroyProgramExamTimetable($program, $timetable, 'SCES');
                })->name('destroy');
                
                Route::get('/download/pdf', function(Program $program) {
                    return app(ExamTimetableController::class)->downloadProgramExamTimetablePDF($program, 'SCES');
                })->name('download');
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
        Route::get('/exams', [StudentController::class, 'myExams'])->name('student.exams');
        Route::get('/timetable', [StudentController::class, 'myTimetable'])->name('student.timetable');
        Route::get('/download-classtimetable', [ClassTimetableController::class, 'downloadStudentPDF'])->name('student.classtimetable.download');
        Route::get('/profile', [StudentController::class, 'profile'])->name('student.profile');

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
        Route::get('/profile', [LecturerController::class, 'profile'])->name('lecturer.profile');
    });

    // CLASS TIMETABLE OFFICE ROUTES (after admin routes, before students)
// CLASS TIMETABLE OFFICE ROUTES - FULL MANAGEMENT INTERFACE
Route::prefix('classtimetables')->middleware(['role:Class Timetable office'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'classtimetablesDashboard'])
        ->name('classtimetables.dashboard');
    
    // Timetable Management (All schools)
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
    
    Route::get('/download/pdf', [ClassTimetableController::class, 'downloadAllPDF'])
        ->name('classtimetables.download-pdf');
    
    Route::get('/conflicts', [ClassTimetableController::class, 'showConflicts'])
        ->name('classtimetables.conflicts');
});

// CATCH-ALL ROUTE
Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');
});