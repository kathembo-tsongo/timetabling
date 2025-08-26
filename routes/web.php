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
use App\Models\Program;
use App\Models\Unit;
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

    // Admin Units Routes
    Route::get('/units', [UnitController::class, 'index'])->name('admin.units.index');
    Route::post('/units', [UnitController::class, 'Store'])->name('admin.units.store');
    Route::get('/units/create', [UnitController::class, 'Create'])->name('admin.units.create');
    
    // Move these assignment routes BEFORE the {unit} parameter routes
    Route::get('/units/assign-semesters', [UnitController::class, 'assignSemesters'])->name('admin.units.assign-semesters');
    Route::post('/units/assign-semester', [UnitController::class, 'assignToSemester'])->name('admin.units.assign-semester');
    Route::post('/units/remove-semester', [UnitController::class, 'removeFromSemester'])->name('admin.units.remove-semester');
    
    // Keep parameter routes at the end
    Route::get('/units/{unit}', [UnitController::class, 'Show'])->name('admin.units.show');
    Route::get('/units/{unit}/edit', [UnitController::class, 'Edit'])->name('admin.units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'Update'])->name('admin.units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'Destroy'])->name('admin.units.destroy');
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
        Route::get('/api/classes/available-names', [ClassController::class, 'getAvailableClassNames']);
Route::get('/api/classes/available-sections-for-class', [ClassController::class, 'getAvailableSectionsForClass']);

});
   
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

    


    


// ===============================================================
// CATCH-ALL ROUTE
// ===============================================================

Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');