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
use App\Models\Program;
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
            
        // Users Management
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('/users/{user}/edit-role', [UserController::class, 'editRole'])->name('admin.users.edit-role');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.update-role');
        Route::get('/users/roles-permissions', [UserController::class, 'getUserRolesAndPermissions'])->name('admin.users.roles-permissions');
        Route::put('/users/{user}/roles-permissions', [UserController::class, 'updateUserRolesAndPermissions'])->name('admin.users.update-roles-permissions');
        Route::get('/users/{user}/permissions', [UserController::class, 'getUserPermissions'])->name('admin.users.permissions');
        
        // Roles Management
        Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
        Route::get('/roles/permissions/grouped', [RoleController::class, 'getGroupedPermissions'])->name('admin.roles.permissions.grouped');
            
        // Semesters
        Route::get('/semesters', [SemesterController::class, 'index'])->name('admin.semesters.index');
        Route::post('/semesters', [SemesterController::class, 'store'])->name('admin.semesters.store');
        Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('admin.semesters.show');
        Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('admin.semesters.update');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('admin.semesters.destroy');
        Route::put('/semesters/{semester}/activate', [SemesterController::class, 'setActive'])->name('admin.semesters.activate');
        
        // Schools
        Route::get('schools', [SchoolController::class, 'index'])->name('admin.schools.index');
        Route::get('schools/create', [SchoolController::class, 'create'])->name('admin.schools.create');
        Route::get('schools/{school}', [SchoolController::class, 'show'])->name('admin.schools.show');
        Route::get('schools/{school}/edit', [SchoolController::class, 'edit'])->name('admin.schools.edit');
        Route::post('schools', [SchoolController::class, 'store'])->name('admin.schools.store');
        Route::put('schools/{school}', [SchoolController::class, 'update'])->name('admin.schools.update');
        Route::patch('schools/{school}', [SchoolController::class, 'update'])->name('admin.schools.patch');
        Route::delete('schools/{school}', [SchoolController::class, 'destroy'])->name('admin.schools.destroy');
        Route::get('schools-api/all', [SchoolController::class, 'getAllSchools'])->name('admin.schools.api.all');
    });

    // ===============================================================
    // PROFILE ROUTES
    // ===============================================================
    
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // ===============================================================
    // GENERIC PROGRAMS REDIRECT ROUTE
    // ===============================================================
    
    Route::get('programs', function() {
        // Redirect based on user's role/permissions - fallback to SCES for now
        return redirect()->route('schools.sces.programs.index');
    })->name('programs.index');

    // ===============================================================
    // UNIFIED FACULTY ADMIN ROUTES
    // ===============================================================
    
    // School-level dashboards
    Route::prefix('facultyadmin')->group(function () {
        
        Route::get('/sces', function () {
            return Inertia::render('FacultyAdmin/SchoolDashboard', [
                'schoolCode' => 'SCES',
                'schoolName' => 'School of Computing and Engineering Sciences',
                'programs' => [
                    'bbit' => 'Bachelor of Business Information Technology',
                    'ics' => 'Information Communication Systems',
                    'cs' => 'Computer Science',
                ]
            ]);
        })->name('facultyadmin.sces.dashboard');
        
        Route::get('/sbs', function () {
            return Inertia::render('FacultyAdmin/SchoolDashboard', [
                'schoolCode' => 'SBS', 
                'schoolName' => 'School of Business Studies',
                'programs' => [
                    'mba' => 'Master of Business Administration',
                    'bba' => 'Bachelor of Business Administration', 
                    'bcom' => 'Bachelor of Commerce',
                ]
            ]);
        })->name('facultyadmin.sbs.dashboard');
    });

    // Unified program-specific routes
    Route::prefix('facultyadmin/{school}/{program}')
        ->middleware(['auth'])
        ->where(['school' => 'sces|sbs', 'program' => 'bbit|ics|cs|mba|bba|bcom'])
        ->group(function () {
            
            // Program Dashboard
            Route::get('/', [DashboardController::class, 'programDashboard'])
                ->name('facultyadmin.program.dashboard');
            
            // Units Management
            Route::get('/units', [UnitController::class, 'index'])
                ->name('facultyadmin.units.index');
            Route::post('/units', [UnitController::class, 'store'])
                ->name('facultyadmin.units.store');
            Route::put('/units/{unit}', [UnitController::class, 'update'])
                ->name('facultyadmin.units.update');
            Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
                ->name('facultyadmin.units.destroy');
            
            // Unit API endpoints
            Route::get('/units/by-semester/{semesterId}', [UnitController::class, 'getUnitsBySemester'])
                ->name('facultyadmin.units.by-semester');
            Route::post('/units/assign-semester', [UnitController::class, 'bulkAssignToSemester'])
                ->name('facultyadmin.units.assign-semester');
            
            // Enrollments Management
            Route::get('/enrollments', [EnrollmentController::class, 'index'])
                ->name('facultyadmin.enrollments.index');
            Route::post('/enrollments', [EnrollmentController::class, 'store'])
                ->name('facultyadmin.enrollments.store');
            Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])
                ->name('facultyadmin.enrollments.destroy');
            Route::post('/enrollments/bulk-delete', [EnrollmentController::class, 'bulkDestroy'])
                ->name('facultyadmin.enrollments.bulk-delete');
            
            // Lecturer Assignment
            Route::post('/assign-lecturer', [EnrollmentController::class, 'assignLecturer'])
                ->name('facultyadmin.enrollments.assign-lecturer');
            Route::delete('/lecturer-assignments/{unitId}/{lecturerCode}', [EnrollmentController::class, 'removeLecturerAssignment'])
                ->name('facultyadmin.enrollments.remove-lecturer');
        });

    // ===============================================================
    // SCHOOL PROGRAMS MANAGEMENT
    // ===============================================================

    // SCES Programs Routes
    Route::prefix('schools/sces')->middleware(['auth'])->name('schools.sces.programs.')->group(function () {
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

    // SBS Programs Routes
    Route::prefix('schools/sbs')->middleware(['auth'])->name('schools.sbs.programs.')->group(function () {
        Route::get('programs', function(Request $request) {
            return app(ProgramController::class)->index($request, 'SBS');
        })->name('index');
        
        Route::get('programs/create', function() {
            return app(ProgramController::class)->create('SBS');
        })->name('create');
        
        Route::get('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->show('SBS', $program);
        })->name('show');
        
        Route::get('programs/{program}/edit', function(Program $program) {
            return app(ProgramController::class)->edit('SBS', $program);
        })->name('edit');
        
        Route::post('programs', function(Request $request) {
            return app(ProgramController::class)->store($request, 'SBS');
        })->name('store');
        
        Route::put('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SBS', $program);
        })->name('update');
        
        Route::patch('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SBS', $program);
        })->name('patch');
        
        Route::delete('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->destroy('SBS', $program);
        })->name('destroy');
        
        Route::get('api/programs', function() {
            return app(ProgramController::class)->getAllPrograms('SBS');
        })->name('api.all');
    });

    // SLS Programs Routes
    Route::prefix('schools/sls')->middleware(['auth'])->name('schools.sls.programs.')->group(function () {
        Route::get('programs', function(Request $request) {
            return app(ProgramController::class)->index($request, 'SLS');
        })->name('index');
        
        Route::get('programs/create', function() {
            return app(ProgramController::class)->create('SLS');
        })->name('create');
        
        Route::get('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->show('SLS', $program);
        })->name('show');
        
        Route::get('programs/{program}/edit', function(Program $program) {
            return app(ProgramController::class)->edit('SLS', $program);
        })->name('edit');
        
        Route::post('programs', function(Request $request) {
            return app(ProgramController::class)->store($request, 'SLS');
        })->name('store');
        
        Route::put('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SLS', $program);
        })->name('update');
        
        Route::patch('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SLS', $program);
        })->name('patch');
        
        Route::delete('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->destroy('SLS', $program);
        })->name('destroy');
        
        Route::get('api/programs', function() {
            return app(ProgramController::class)->getAllPrograms('SLS');
        })->name('api.all');
    });

    // TOURISM Programs Routes
    Route::prefix('schools/tourism')->middleware(['auth'])->name('schools.tourism.programs.')->group(function () {
        Route::get('programs', function(Request $request) {
            return app(ProgramController::class)->index($request, 'TOURISM');
        })->name('index');
        
        Route::get('programs/create', function() {
            return app(ProgramController::class)->create('TOURISM');
        })->name('create');
        
        Route::get('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->show('TOURISM', $program);
        })->name('show');
        
        Route::get('programs/{program}/edit', function(Program $program) {
            return app(ProgramController::class)->edit('TOURISM', $program);
        })->name('edit');
        
        Route::post('programs', function(Request $request) {
            return app(ProgramController::class)->store($request, 'TOURISM');
        })->name('store');
        
        Route::put('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'TOURISM', $program);
        })->name('update');
        
        Route::patch('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'TOURISM', $program);
        })->name('patch');
        
        Route::delete('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->destroy('TOURISM', $program);
        })->name('destroy');
        
        Route::get('api/programs', function() {
            return app(ProgramController::class)->getAllPrograms('TOURISM');
        })->name('api.all');
    });

    // SHS Programs Routes
    Route::prefix('schools/shs')->middleware(['auth'])->name('schools.shs.programs.')->group(function () {
        
        Route::get('/debug-programs', function() {
    return [
        'all_programs' => \App\Models\Program::all(),
        'all_schools' => \App\Models\School::all(),
        'user_roles' => auth()->user()->getRoleNames(),
        'current_route' => request()->route()->getName() ?? 'No route name',
        'url' => request()->fullUrl()
    ];
})->middleware('auth');
        
        Route::get('programs', function(Request $request) {
            return app(ProgramController::class)->index($request, 'SHS');
        })->name('index');
        
        
        Route::get('programs/create', function() {
            return app(ProgramController::class)->create('SHS');
        })->name('create');
        
        Route::get('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->show('SHS', $program);
        })->name('show');
        
        Route::get('programs/{program}/edit', function(Program $program) {
            return app(ProgramController::class)->edit('SHS', $program);
        })->name('edit');
        
        Route::post('programs', function(Request $request) {
            return app(ProgramController::class)->store($request, 'SHS');
        })->name('store');
        
        Route::put('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SHS', $program);
        })->name('update');
        
        Route::patch('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SHS', $program);
        })->name('patch');
        
        Route::delete('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->destroy('SHS', $program);
        })->name('destroy');
        
        Route::get('api/programs', function() {
            return app(ProgramController::class)->getAllPrograms('SHS');
        })->name('api.all');
    });

    // SHM Programs Routes
    Route::prefix('schools/shm')->middleware(['auth'])->name('schools.shm.programs.')->group(function () {
        Route::get('programs', function(Request $request) {
            return app(ProgramController::class)->index($request, 'SHM');
        })->name('index');
        
        Route::get('programs/create', function() {
            return app(ProgramController::class)->create('SHM');
        })->name('create');
        
        Route::get('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->show('SHM', $program);
        })->name('show');
        
        Route::get('programs/{program}/edit', function(Program $program) {
            return app(ProgramController::class)->edit('SHM', $program);
        })->name('edit');
        
        Route::post('programs', function(Request $request) {
            return app(ProgramController::class)->store($request, 'SHM');
        })->name('store');
        
        Route::put('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SHM', $program);
        })->name('update');
        
        Route::patch('programs/{program}', function(Request $request, Program $program) {
            return app(ProgramController::class)->update($request, 'SHM', $program);
        })->name('patch');
        
        Route::delete('programs/{program}', function(Program $program) {
            return app(ProgramController::class)->destroy('SHM', $program);
        })->name('destroy');
        
        Route::get('api/programs', function() {
            return app(ProgramController::class)->getAllPrograms('SHM');
        })->name('api.all');
    });

});

// ===============================================================
// CATCH-ALL ROUTE
// ===============================================================

Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');