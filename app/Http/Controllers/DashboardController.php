<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\ClassTimetable;
use App\Models\School;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Main dashboard entry point - redirects based on user role
     */
    public function index()
    {
        $user = auth()->user();

        // Enhanced debugging
        Log::info('Dashboard index accessed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames()->toArray(),
            'has_admin_role' => $user->hasRole('Admin'),
            'can_view_admin_dashboard' => $user->can('view admin dashboard'),
        ]);

        // Check for Faculty Admin roles first (school-specific)
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $faculty = str_replace('Faculty Admin - ', '', $role);
                $schoolRoute = match($faculty) {
                    'SCES' => 'faculty.dashboard.sces',
                    'SBS' => 'faculty.dashboard.sbs',
                    'SLS' => 'faculty.dashboard.sls',
                    'TOURISM' => 'faculty.dashboard.tourism',
                    'SHM' => 'faculty.dashboard.shm',
                    'SHS' => 'faculty.dashboard.shs',
                    default => null
                };
                
                if ($schoolRoute) {
                    Log::info('Redirecting to school-specific dashboard', [
                        'user_id' => $user->id,
                        'faculty' => $faculty,
                        'route' => $schoolRoute
                    ]);
                    return redirect()->route($schoolRoute);
                }
            }
        }

        // Automatic role-based dashboard redirection
        if ($user->hasRole('Admin')) {
            Log::info('Redirecting to admin dashboard', ['user_id' => $user->id]);
            return $this->adminDashboard(request());
        } elseif ($user->hasRole('Student')) {
            Log::info('Redirecting to student dashboard', ['user_id' => $user->id]);
            return $this->studentDashboard(request());
        } elseif ($user->hasRole('Lecturer')) {
            Log::info('Redirecting to lecturer dashboard', ['user_id' => $user->id]);
            return $this->lecturerDashboard(request());
        } elseif ($user->hasRole('Exam Office')) {
            Log::info('Redirecting to exam office dashboard', ['user_id' => $user->id]);
            return $this->examOfficeDashboard(request());
        } elseif ($user->hasRole('Faculty Admin')) {
            Log::info('Redirecting to generic faculty admin dashboard', ['user_id' => $user->id]);
            return $this->facultyAdminDashboard(request());
        }

        Log::info('No specific role found, showing default dashboard', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray()
        ]);

        // Default dashboard for users without specific roles
        return $this->defaultDashboard();
    }

    /**
     * Display the admin dashboard with real statistics.
     */
    public function adminDashboard(Request $request)
    {
        $user = $request->user();

        // Ensure permissions are loaded
        $user->load('roles.permissions', 'permissions');
        $allPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        Log::info('Admin dashboard permissions check', [
            'user_id' => $user->id,
            'roles' => $roles,
            'all_permissions_count' => count($allPermissions),
            'all_permissions' => $allPermissions,
            'has_admin_role' => $user->hasRole('Admin'),
            'can_view_admin_dashboard' => $user->can('view admin dashboard'),
        ]);

        // Check if user has permission to view admin dashboard
        if (!$user->can('view admin dashboard') && !$user->hasRole('Admin')) {
            Log::warning('Unauthorized access to admin dashboard', [
                'user_id' => $user->id,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ]);
            abort(403, 'Unauthorized access to admin dashboard.');
        }

        try {
            // Get current semester
            $currentSemester = Semester::where('is_active', true)->first();
            if (!$currentSemester) {
                $currentSemester = Semester::latest()->first();
            }

            // Get previous month for comparison
            $currentMonth = now();
            $previousMonth = now()->subMonth();
            $previousWeek = now()->subWeek();

            // Total Users Statistics
            $totalUsers = User::count();
            $usersLastMonth = User::where('created_at', '>=', $previousMonth)
                                         ->where('created_at', '<', $currentMonth)
                                         ->count();
            $usersPreviousMonth = User::where('created_at', '>=', $previousMonth->copy()->subMonth())
                                             ->where('created_at', '<', $previousMonth)
                                             ->count();
            $usersGrowthRate = $usersPreviousMonth > 0
                         ? round((($usersLastMonth - $usersPreviousMonth) / $usersPreviousMonth) * 100, 1)
                        : ($usersLastMonth > 0 ? 100 : 0);

            // Active Enrollments Statistics - FIXED to use DB::table
            $activeEnrollments = DB::table('bbit_enrollments')->whereNotNull('student_code')->count();
            $enrollmentsLastWeek = DB::table('bbit_enrollments')
                                        ->where('created_at', '>=', $previousWeek)
                                        ->where('created_at', '<', $currentMonth)
                                        ->whereNotNull('student_code')
                                        ->count();
            $enrollmentsPreviousWeek = DB::table('bbit_enrollments')
                                           ->where('created_at', '>=', $previousWeek->copy()->subWeek())
                                           ->where('created_at', '<', $previousWeek)
                                           ->whereNotNull('student_code')
                                           ->count();

            if ($currentSemester) {
                $activeEnrollments = DB::table('bbit_enrollments')
                                          ->where('semester_id', $currentSemester->id)
                                          ->whereNotNull('student_code')
                                          ->count();
                $enrollmentsLastWeek = DB::table('bbit_enrollments')
                                            ->where('semester_id', $currentSemester->id)
                                            ->where('created_at', '>=', $previousWeek)
                                            ->where('created_at', '<', $currentMonth)
                                            ->whereNotNull('student_code')
                                            ->count();
                $enrollmentsPreviousWeek = DB::table('bbit_enrollments')
                                               ->where('semester_id', $currentSemester->id)
                                               ->where('created_at', '>=', $previousWeek->copy()->subWeek())
                                               ->where('created_at', '<', $previousWeek)
                                               ->whereNotNull('student_code')
                                               ->count();
            }

            $enrollmentsGrowthRate = $enrollmentsPreviousWeek > 0
                         ? round((($enrollmentsLastWeek - $enrollmentsPreviousWeek) / $enrollmentsPreviousWeek) * 100, 1)
                        : ($enrollmentsLastWeek > 0 ? 100 : 0);

            // Active Classes Statistics
            $activeClasses = 0;
            $classesLastMonth = 0;
            $classesPreviousMonth = 0;

            if ($currentSemester) {
                // Count distinct units that have enrollments in current semester
                $activeClasses = Unit::whereHas('bbit_enrollments', function($query) use ($currentSemester) {
                    $query->where('semester_id', $currentSemester->id);
                })->count();

                // Classes added last month
                $classesLastMonth = Unit::where('created_at', '>=', $previousMonth)
                                               ->where('created_at', '<', $currentMonth)
                                               ->count();
                $classesPreviousMonth = Unit::where('created_at', '>=', $previousMonth->copy()->subMonth())
                                                   ->where('created_at', '<', $previousMonth)
                                                   ->count();
            }

            $classesGrowthRate = $classesPreviousMonth > 0
                         ? round((($classesLastMonth - $classesPreviousMonth) / $classesPreviousMonth) * 100, 1)
                        : ($classesLastMonth > 0 ? 100 : 0);

            // Exam Sessions Statistics
            $examSessions = 0;
            $examsLastWeek = 0;
            $examsPreviousWeek = 0;

            if ($currentSemester) {
                $examSessions = ExamTimetable::where('semester_id', $currentSemester->id)
                                                   ->where('date', '>=', now()->format('Y-m-d'))
                                                   ->count();
                $examsLastWeek = ExamTimetable::where('semester_id', $currentSemester->id)
                                                    ->where('created_at', '>=', $previousWeek)
                                                    ->count();
                $examsPreviousWeek = ExamTimetable::where('semester_id', $currentSemester->id)
                                                        ->where('created_at', '>=', $previousWeek->copy()->subWeek())
                                                        ->where('created_at', '<', $previousWeek)
                                                        ->count();
            }

            $examsGrowthRate = $examsPreviousWeek > 0
                         ? round((($examsLastWeek - $examsPreviousWeek) / $examsPreviousWeek) * 100, 1)
                        : 0;

            // Recent Activities using DB::table
            $recentEnrollments = DB::table('bbit_enrollments')
                                  ->join('bbit_units', 'bbit_enrollments.unit_id', '=', 'bbit_units.id')
                                  ->leftJoin('users', 'bbit_enrollments.student_code', '=', 'users.code')
                                  ->select(
                                      'bbit_enrollments.*',
                                      'bbit_units.unit_name',
                                      'bbit_units.unit_code',
                                      'users.name as student_name'
                                  )
                                  ->whereNotNull('bbit_enrollments.student_code')
                                  ->orderBy('bbit_enrollments.created_at', 'desc')
                                  ->limit(5)
                                  ->get();

            // System Statistics
            $totalSchools = School::count();
            $totalSemesters = Semester::count();

            // Role-based statistics
            $roleStats = [
                'admins' => User::role('Admin')->count(),
                'students' => User::role('Student')->count(),
                'lecturers' => User::role('Lecturer')->count(),
                'faculty_admins' => User::role('Faculty Admin')->count(),
                'exam_office' => User::role('Exam Office')->count(),
            ];

            $dashboardData = [
                'statistics' => [
                    'totalUsers' => [
                        'count' => $totalUsers,
                        'growthRate' => $usersGrowthRate,
                        'period' => 'from last month'
                    ],
                    'activeEnrollments' => [
                        'count' => $activeEnrollments,
                        'growthRate' => $enrollmentsGrowthRate,
                        'period' => 'from last week'
                    ],
                    'activeClasses' => [
                        'count' => $activeClasses,
                        'growthRate' => $classesGrowthRate,
                        'period' => 'from last month'
                    ],
                    'examSessions' => [
                        'count' => $examSessions,
                        'growthRate' => $examsGrowthRate,
                        'period' => 'from last week'
                    ]
                ],
                'currentSemester' => $currentSemester,
                'systemInfo' => [
                    'totalSchools' => $totalSchools,
                    'totalSemesters' => $totalSemesters,
                ],
                'roleStats' => $roleStats,
                'recentEnrollments' => $recentEnrollments,
                'userPermissions' => $allPermissions,
                'userRoles' => $roles,
                'isAdmin' => true
            ];

            Log::info('Admin dashboard data generated successfully', [
                'total_users' => $totalUsers,
                'active_enrollments' => $activeEnrollments,
                'active_classes' => $activeClasses,
                'exam_sessions' => $examSessions,
                'current_semester' => $currentSemester ? $currentSemester->name : 'None',
                'user_id' => $user->id
            ]);

            return Inertia::render('Admin/Dashboard', $dashboardData);

        } catch (\Exception $e) {
            Log::error('Error in admin dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);

            // Return safe defaults in case of error
            return Inertia::render('Admin/Dashboard', [
                'statistics' => [
                    'totalUsers' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last month'],
                    'activeEnrollments' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last week'],
                    'activeClasses' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last month'],
                    'examSessions' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last week']
                ],
                'currentSemester' => null,
                'systemInfo' => ['totalSchools' => 0, 'totalSemesters' => 0],
                'roleStats' => [],
                'recentEnrollments' => [],
                'userPermissions' => [],
                'userRoles' => [],
                'isAdmin' => true,
                'error' => 'Unable to load dashboard data'
            ]);
        }
    }

    /**
     * Display the student dashboard.
     */
    public function studentDashboard(Request $request)
    {
        // Check if user has permission to view student dashboard
        if (!$request->user()->can('view student dashboard') && !$request->user()->hasRole('Student')) {
            abort(403, 'Unauthorized access to student dashboard.');
        }

        $user = $request->user();

        if (!$user || !$user->code) {
            Log::error('Student dashboard accessed with invalid user', [
                'user_id' => $user ? $user->id : 'null',
                'has_code' => $user && isset($user->code)
            ]);

            return Inertia::render('Student/Dashboard', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'currentSemester' => null,
                'enrolledUnits' => [],
                'upcomingExams' => [],
                'selectedSemesterId' => null,
                'userPermissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'userRoles' => $user ? $user->getRoleNames() : []
            ]);
        }

        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }

        // Default values in case of errors
        $selectedSemester = $currentSemester;
        $enrolledUnits = collect([]);
        $upcomingExams = collect([]);

        try {
            // Find semesters where the student has enrollments - FIXED to use DB::table
            $studentSemesters = DB::table('bbit_enrollments')
                        ->where('student_code', $user->code)
                        ->distinct()
                        ->join('semesters', 'bbit_enrollments.semester_id', '=', 'semesters.id')
                        ->select('semesters.*')
                        ->get();

            if ($studentSemesters->isEmpty()) {
                $selectedSemester = $currentSemester;
            } else {
                $activeSemester = $studentSemesters->firstWhere('is_active', true);
                $selectedSemester = $activeSemester ?? $studentSemesters->sortByDesc('id')->first();
            }

            if (!$selectedSemester) {
                throw new \Exception('No valid semester found for student');
            }

            // Get enrolled units for the student in the selected semester - FIXED to use DB::table
            $enrolledUnits = DB::table('bbit_enrollments')
                        ->where('student_code', $user->code)
                        ->where('semester_id', $selectedSemester->id)
                        ->join('bbit_units', 'bbit_enrollments.unit_id', '=', 'bbit_units.id')
                        ->leftJoin('schools', 'bbit_units.school_id', '=', 'schools.id')
                        ->select('bbit_units.*', 'schools.name as school_name')
                        ->distinct('bbit_units.id')
                        ->get();

            // Get upcoming exams for the student in the selected semester
            $upcomingExams = ExamTimetable::where('semester_id', $selectedSemester->id)
                        ->whereIn('unit_id', function($query) use ($user) {
                            $query->select('unit_id')
                                  ->from('bbit_enrollments')
                                  ->where('student_code', $user->code);
                        })
                        ->where('date', '>=', now()->format('Y-m-d'))
                        ->orderBy('date')
                        ->orderBy('start_time')
                        ->limit(5)
                        ->get();

        } catch (\Exception $e) {
            Log::error('Error in student dashboard', [
                'student_code' => $user->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::info('Student dashboard data', [
            'student_code' => $user->code,
            'selected_semester_id' => $selectedSemester ? $selectedSemester->id : null,
            'selected_semester_name' => $selectedSemester ? $selectedSemester->name : null,
            'enrolled_units_count' => $enrolledUnits->count(),
            'upcoming_exams_count' => $upcomingExams->count()
        ]);

        return Inertia::render('Student/Dashboard', [
            'currentSemester' => $selectedSemester,
            'enrolledUnits' => $enrolledUnits,
            'upcomingExams' => $upcomingExams,
            'selectedSemesterId' => $selectedSemester ? $selectedSemester->id : null,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Display the lecturer dashboard.
     */
    public function lecturerDashboard(Request $request)
    {
        // Check if user has permission to view lecturer dashboard
        if (!$request->user()->can('view lecturer dashboard') && !$request->user()->hasRole('Lecturer')) {
            abort(403, 'Unauthorized access to lecturer dashboard.');
        }

        $user = $request->user();

        if (!$user) {
            return Inertia::render('Lecturer/Dashboard', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'currentSemester' => null,
                'lecturerSemesters' => [],
                'unitsBySemester' => [],
                'studentCounts' => [],
                'userPermissions' => [],
                'userRoles' => []
            ]);
        }

        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }

        $lecturerSemesters = collect([]);
        $unitsBySemester = [];
        $studentCounts = [];

        try {
            // FIXED: Use DB::table for lecturer enrollments
            $lecturerCode = $user->code;

            // Get all semesters where this lecturer has assignments
            $lecturerSemesters = Semester::whereExists(function($query) use ($lecturerCode) {
                $query->select(DB::raw(1))
                      ->from('bbit_enrollments')
                      ->whereColumn('bbit_enrollments.semester_id', 'semesters.id')
                      ->where('bbit_enrollments.lecturer_code', $lecturerCode);
            })->get();

            // For each semester, get the units assigned to this lecturer
            foreach ($lecturerSemesters as $semester) {
                // Get units for this lecturer in this semester - FIXED to use DB::table
                $units = DB::table('bbit_enrollments')
                          ->where('lecturer_code', $lecturerCode)
                          ->where('semester_id', $semester->id)
                          ->join('bbit_units', 'bbit_enrollments.unit_id', '=', 'bbit_units.id')
                          ->leftJoin('schools', 'bbit_units.school_id', '=', 'schools.id')
                          ->select('bbit_units.*', 'schools.name as school_name')
                          ->distinct('bbit_units.id')
                          ->get();

                if ($units->count() > 0) {
                    $unitsBySemester[$semester->id] = [
                        'semester' => $semester,
                        'units' => $units->toArray()
                    ];

                    // Count students for each unit
                    $studentCounts[$semester->id] = [];
                    foreach ($units as $unit) {
                        $studentCounts[$semester->id][$unit->id] = DB::table('bbit_enrollments')
                                    ->where('unit_id', $unit->id)
                                    ->where('semester_id', $semester->id)
                                    ->whereNotNull('student_code')
                                    ->distinct('student_code')
                                    ->count();
                    }
                }
            }

            // If no results found, log detailed debug info
            if (empty($unitsBySemester)) {
                Log::warning('No units found for lecturer', [
                    'lecturer_code' => $lecturerCode,
                    'lecturer_name' => $user->name,
                    'semesters_checked' => $lecturerSemesters->pluck('id')->toArray(),
                    'enrollment_check' => DB::table('bbit_enrollments')->where('lecturer_code', $lecturerCode)->count(),
                    'all_lecturer_codes' => DB::table('bbit_enrollments')
                                ->whereNotNull('lecturer_code')
                                ->distinct('lecturer_code')
                                ->pluck('lecturer_code')
                                ->toArray()
                    ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in lecturer dashboard', [
                'lecturer_code' => $user->code ?? 'No code',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Enhanced debug logging
        Log::info('Lecturer dashboard debug', [
            'lecturer_id' => $user->id,
            'lecturer_code' => $user->code ?? 'No code',
            'current_semester_id' => $currentSemester ? $currentSemester->id : null,
            'lecturer_semesters_count' => $lecturerSemesters->count(),
            'units_by_semester_keys' => array_keys($unitsBySemester),
            'total_units_count' => array_sum(array_map(function($semester) {
                return count($semester['units']);
            }, $unitsBySemester))
        ]);

        return Inertia::render('Lecturer/Dashboard', [
            'currentSemester' => $currentSemester,
            'lecturerSemesters' => $lecturerSemesters,
            'unitsBySemester' => $unitsBySemester,
            'studentCounts' => $studentCounts,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Display the exam office dashboard.
     */
    public function examOfficeDashboard(Request $request)
    {
        // Check if user has permission to view exam office dashboard
        if (!$request->user()->can('view exam office dashboard') && !$request->user()->hasRole('Exam Office')) {
            abort(403, 'Unauthorized access to exam office dashboard.');
        }

        $user = $request->user();
        $currentSemester = Semester::where('is_active', true)->first();

        $examStats = [];
        if ($currentSemester) {
            $examStats = [
                'total_exams' => ExamTimetable::where('semester_id', $currentSemester->id)->count(),
                'upcoming_exams' => ExamTimetable::where('semester_id', $currentSemester->id)
                        ->where('date', '>=', now()->format('Y-m-d'))
                        ->count(),
                'completed_exams' => ExamTimetable::where('semester_id', $currentSemester->id)
                        ->where('date', '<', now()->format('Y-m-d'))
                        ->count(),
            ];
        }

        return Inertia::render('ExamOffice/Dashboard', [
            'currentSemester' => $currentSemester,
            'examStats' => $examStats,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Display the faculty admin dashboard.
     */
    public function facultyAdminDashboard(Request $request)
    {
        // Check if user has permission to view faculty admin dashboard
        if (!$request->user()->can('view faculty admin dashboard') && !$request->user()->hasRole('Faculty Admin')) {
            abort(403, 'Unauthorized access to faculty admin dashboard.');
        }

        $user = $request->user();
        $currentSemester = Semester::where('is_active', true)->first();

        $facultyStats = [];
        if ($currentSemester) {
            $facultyStats = [
                'total_lecturers' => User::role('Lecturer')->count(),
                'total_students' => User::role('Student')->count(),
                'active_enrollments' => DB::table('bbit_enrollments')
                        ->where('semester_id', $currentSemester->id)
                        ->whereNotNull('student_code')
                        ->count(),
            ];
        }

        return Inertia::render('FacultyAdmin/sces/Dashboard', [
            'currentSemester' => $currentSemester,
            'facultyStats' => $facultyStats,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * SCES Faculty Dashboard - UPDATED METHOD
     */
    /**
     * SCES Faculty Dashboard - FIXED METHOD
     */
    public function scesDashboard()
    {
        $user = auth()->user();
        
        // Check if user has permission for SCES
        if (!$user->can('view-faculty-dashboard-sces') && !$user->hasRole('Faculty Admin - SCES')) {
            Log::warning('Unauthorized access to SCES dashboard', [
                'user_id' => $user->id,
                'user_roles' => $user->getRoleNames()->toArray()
            ]);
            abort(403, 'Unauthorized access to SCES faculty dashboard.');
        }

        // FIXED: Get current semester based on actual unit assignments
        $currentSemester = Semester::whereExists(function($query) {
            $query->select(DB::raw(1))
                  ->from('bbit_units')
                  ->whereColumn('bbit_units.semester_id', 'semesters.id')
                  ->where('bbit_units.is_active', 1);
        })->orderBy('id', 'desc')->first();

        // Fallback to is_active if no units are assigned
        if (!$currentSemester) {
            $currentSemester = Semester::where('is_active', true)->first();
        }

        // Final fallback to latest semester
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        try {
            // Get SCES statistics with proper error handling
            $scesStats = [
                'total_students' => 0,
                'total_lecturers' => 0,
                'bbit_units' => 0,
                'ics_units' => 0,
                'networking_units' => 0,
                'active_enrollments' => 0,
            ];

            // BBIT Units (always available)
            try {
                $scesStats['bbit_units'] = DB::table('bbit_units')->where('is_active', 1)->count();
            } catch (\Exception $e) {
                Log::warning('BBIT units table not accessible', ['error' => $e->getMessage()]);
                $scesStats['bbit_units'] = 0;
            }

            // ICS Units (optional)
            try {
                $scesStats['ics_units'] = DB::table('ics_units')->where('is_active', 1)->count();
            } catch (\Exception $e) {
                $scesStats['ics_units'] = 0;
            }

            // Networking Units (optional)
            try {
                $scesStats['networking_units'] = DB::table('networking_units')->where('is_active', 1)->count();
            } catch (\Exception $e) {
                $scesStats['networking_units'] = 0;
            }

            // Active enrollments - use the current semester from units
            try {
                $scesStats['active_enrollments'] = DB::table('bbit_enrollments')
                    ->where('semester_id', $currentSemester?->id)
                    ->whereNotNull('student_code')
                    ->count();
            } catch (\Exception $e) {
                $scesStats['active_enrollments'] = 0;
            }

            // Total students across SCES programs
            try {
                $scesStats['total_students'] = DB::table('bbit_enrollments')
                    ->whereNotNull('student_code')
                    ->distinct('student_code')
                    ->count();
            } catch (\Exception $e) {
                $scesStats['total_students'] = 0;
            }

            // Total lecturers across SCES programs
            try {
                $scesStats['total_lecturers'] = DB::table('bbit_enrollments')
                    ->whereNotNull('lecturer_code')
                    ->distinct('lecturer_code')
                    ->count();
            } catch (\Exception $e) {
                $scesStats['total_lecturers'] = 0;
            }

            // Get recent activities
            $recentActivities = [];
            try {
                $recentActivities = DB::table('bbit_enrollments')
                    ->join('bbit_units', 'bbit_enrollments.unit_id', '=', 'bbit_units.id')
                    ->leftJoin('users', 'bbit_enrollments.student_code', '=', 'users.code')
                    ->select(
                        'bbit_enrollments.created_at',
                        'bbit_units.name as unit_name',
                        'bbit_units.code as unit_code',
                        'users.first_name',
                        'users.last_name'
                    )
                    ->whereNotNull('bbit_enrollments.student_code')
                    ->orderBy('bbit_enrollments.created_at', 'desc')
                    ->limit(5)
                    ->get();
            } catch (\Exception $e) {
                Log::warning('Error fetching recent activities for SCES dashboard', ['error' => $e->getMessage()]);
                $recentActivities = [];
            }

            Log::info('SCES dashboard data generated', [
                'user_id' => $user->id,
                'stats' => $scesStats,
                'current_semester' => $currentSemester?->name ?? 'None',
                'current_semester_id' => $currentSemester?->id ?? 'None'
            ]);

            // Calculate real growth rates from database
            $previousSemester = Semester::where('id', '<', $currentSemester->id)
                ->orderBy('id', 'desc')
                ->first();

            // Calculate growth rates
            $studentsGrowthRate = 0;
            $lecturersGrowthRate = 0;
            $unitsGrowthRate = 0;
            $enrollmentsGrowthRate = 0;

            if ($previousSemester) {
                // Students growth rate
                try {
                    $previousStudents = DB::table('bbit_enrollments')
                        ->where('semester_id', $previousSemester->id)
                        ->whereNotNull('student_code')
                        ->distinct('student_code')
                        ->count();
                    
                    if ($previousStudents > 0) {
                        $studentsGrowthRate = round((($scesStats['total_students'] - $previousStudents) / $previousStudents) * 100, 1);
                    } else {
                        $studentsGrowthRate = $scesStats['total_students'] > 0 ? 100 : 0;
                    }
                } catch (\Exception $e) {
                    $studentsGrowthRate = 0;
                }

                // Lecturers growth rate
                try {
                    $previousLecturers = DB::table('bbit_enrollments')
                        ->where('semester_id', $previousSemester->id)
                        ->whereNotNull('lecturer_code')
                        ->distinct('lecturer_code')
                        ->count();
                    
                    if ($previousLecturers > 0) {
                        $lecturersGrowthRate = round((($scesStats['total_lecturers'] - $previousLecturers) / $previousLecturers) * 100, 1);
                    } else {
                        $lecturersGrowthRate = $scesStats['total_lecturers'] > 0 ? 100 : 0;
                    }
                } catch (\Exception $e) {
                    $lecturersGrowthRate = 0;
                }

                // Units growth rate
                try {
                    $previousBbitUnits = DB::table('bbit_units')
                        ->where('semester_id', $previousSemester->id)
                        ->where('is_active', 1)
                        ->count();
                    
                    $currentTotalUnits = $scesStats['bbit_units'] + $scesStats['ics_units'] + $scesStats['networking_units'];
                    
                    if ($previousBbitUnits > 0) {
                        $unitsGrowthRate = round((($currentTotalUnits - $previousBbitUnits) / $previousBbitUnits) * 100, 1);
                    } else {
                        $unitsGrowthRate = $currentTotalUnits > 0 ? 100 : 0;
                    }
                } catch (\Exception $e) {
                    $unitsGrowthRate = 0;
                }

                // Enrollments growth rate
                try {
                    $previousEnrollments = DB::table('bbit_enrollments')
                        ->where('semester_id', $previousSemester->id)
                        ->whereNotNull('student_code')
                        ->count();
                    
                    if ($previousEnrollments > 0) {
                        $enrollmentsGrowthRate = round((($scesStats['active_enrollments'] - $previousEnrollments) / $previousEnrollments) * 100, 1);
                    } else {
                        $enrollmentsGrowthRate = $scesStats['active_enrollments'] > 0 ? 100 : 0;
                    }
                } catch (\Exception $e) {
                    $enrollmentsGrowthRate = 0;
                }
            }

            // Calculate real pending approvals from database
            $pendingApprovals = [
                'enrollments' => 0,
                'lecturerRequests' => 0,
                'unitChanges' => 0,
            ];

            try {
                // Count enrollments without student_code (pending approval)
                $pendingApprovals['enrollments'] = DB::table('bbit_enrollments')
                    ->where('semester_id', $currentSemester->id)
                    ->whereNull('student_code')
                    ->count();

                // Count enrollments without lecturer_code (pending lecturer assignment)
                $pendingApprovals['lecturerRequests'] = DB::table('bbit_enrollments')
                    ->where('semester_id', $currentSemester->id)
                    ->whereNull('lecturer_code')
                    ->whereNotNull('student_code')
                    ->count();

                // Count inactive units (pending changes/approval)
                $pendingApprovals['unitChanges'] = DB::table('bbit_units')
                    ->where('is_active', 0)
                    ->count();
            } catch (\Exception $e) {
                Log::warning('Error calculating pending approvals', ['error' => $e->getMessage()]);
            }

            // Calculate real faculty info
            $totalPrograms = 0;
            $totalClasses = 0;

            try {
                // Count distinct programs that have units
                $totalPrograms = collect(['bbit_units', 'ics_units', 'networking_units'])
                    ->filter(function($table) {
                        try {
                            return DB::table($table)->where('is_active', 1)->exists();
                        } catch (\Exception $e) {
                            return false;
                        }
                    })->count();

                // Count total active classes across all programs
                $totalClasses = $scesStats['bbit_units'] + $scesStats['ics_units'] + $scesStats['networking_units'];
            } catch (\Exception $e) {
                Log::warning('Error calculating faculty info', ['error' => $e->getMessage()]);
            }

            return Inertia::render('FacultyAdmin/sces/Dashboard', [
                'schoolCode' => 'SCES',
                'schoolName' => 'School of Computing and Engineering Sciences',
                'currentSemester' => $currentSemester,
                'statistics' => [
                    'totalStudents' => [
                        'count' => $scesStats['total_students'],
                        'growthRate' => $studentsGrowthRate,
                        'period' => 'vs last semester'
                    ],
                    'totalLecturers' => [
                        'count' => $scesStats['total_lecturers'],
                        'growthRate' => $lecturersGrowthRate,
                        'period' => 'vs last semester'
                    ],
                    'totalUnits' => [
                        'count' => $scesStats['bbit_units'] + $scesStats['ics_units'] + $scesStats['networking_units'],
                        'growthRate' => $unitsGrowthRate,
                        'period' => 'vs last semester'
                    ],
                    'activeEnrollments' => [
                        'count' => $scesStats['active_enrollments'],
                        'growthRate' => $enrollmentsGrowthRate,
                        'period' => 'vs last semester'
                    ],
                ],
                'programs' => [
                    ['name' => 'BBIT', 'units' => $scesStats['bbit_units'], 'code' => 'bbit'],
                    ['name' => 'ICS', 'units' => $scesStats['ics_units'], 'code' => 'ics'],
                    ['name' => 'Networking', 'units' => $scesStats['networking_units'], 'code' => 'networking'],
                ],
                'facultyInfo' => [
                    'name' => 'School of Computing and Engineering Sciences',
                    'code' => 'SCES',
                    'totalPrograms' => $totalPrograms,
                    'totalClasses' => $totalClasses,
                ],
                'pendingApprovals' => $pendingApprovals,
                'recentActivities' => $recentActivities,
                'userPermissions' => $user->getAllPermissions()->pluck('name'),
                'userRoles' => $user->getRoleNames()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in SCES dashboard', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return safe defaults in case of error
            return Inertia::render('FacultyAdmin/sces/Dashboard', [
                'schoolCode' => 'SCES',
                'schoolName' => 'School of Computing and Engineering Sciences',
                'currentSemester' => $currentSemester,
                'statistics' => [
                    'totalStudents' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
                    'totalLecturers' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last year'],
                    'totalUnits' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
                    'activeEnrollments' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
                ],
                'programs' => [
                    ['name' => 'BBIT', 'units' => 0, 'code' => 'bbit'],
                    ['name' => 'ICS', 'units' => 0, 'code' => 'ics'],
                    ['name' => 'Networking', 'units' => 0, 'code' => 'networking'],
                ],
                'facultyInfo' => [
                    'name' => 'School of Computing and Engineering Sciences',
                    'code' => 'SCES',
                    'totalPrograms' => 3,
                    'totalClasses' => 0,
                ],
                'pendingApprovals' => [
                    'enrollments' => 0,
                    'lecturerRequests' => 0,
                    'unitChanges' => 0,
                ],
                'recentActivities' => [],
                'userPermissions' => $user->getAllPermissions()->pluck('name'),
                'userRoles' => $user->getRoleNames(),
                'error' => 'Unable to load dashboard data'
            ]);
        }
    }

    /**
     * SBS Faculty Dashboard
     */
    public function sbsDashboard()
    {
        return $this->facultyDashboard('SBS', 'School of Business Studies');
    }

    /**
     * SLS Faculty Dashboard
     */
    public function slsDashboard()
    {
        return $this->facultyDashboard('SLS', 'School of Legal Studies');
    }

    /**
     * TOURISM Faculty Dashboard
     */
    public function tourismDashboard()
    {
        return $this->facultyDashboard('TOURISM', 'School of Tourism and Hospitality');
    }

    /**
     * SHM Faculty Dashboard
     */
    public function shmDashboard()
    {
        return $this->facultyDashboard('SHM', 'School of Humanities');
    }

    /**
     * SHS Faculty Dashboard
     */
    public function shsDashboard()
    {
        return $this->facultyDashboard('SHS', 'School of Health Sciences');
    }

    /**
     * Generic faculty dashboard method for all schools (except SCES which has its own method)
     */
    private function facultyDashboard($schoolCode, $schoolName)
    {
        $user = auth()->user();
        
        // Check if user has permission for this specific school
        $requiredPermission = 'view-faculty-dashboard-' . strtolower($schoolCode);
        if (!$user->can($requiredPermission) && !$user->hasRole('Faculty Admin - ' . $schoolCode)) {
            Log::warning('Unauthorized access to faculty dashboard', [
                'user_id' => $user->id,
                'school_code' => $schoolCode,
                'required_permission' => $requiredPermission,
                'user_roles' => $user->getRoleNames()->toArray()
            ]);
            abort(403, 'Unauthorized access to ' . $schoolCode . ' faculty dashboard.');
        }

        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        
        // Get faculty-specific statistics (generic for non-SCES schools)
        $stats = [
            'totalStudents' => 0,
            'totalLecturers' => 0,
            'totalUnits' => 0,
            'activeEnrollments' => 0,
        ];

        // For other schools, you might need to implement similar logic
        // or use a different enrollment system
        try {
            // Generic stats - you can customize this for each school
            $stats = [
                'totalStudents' => User::role('Student')->count(),
                'totalLecturers' => User::role('Lecturer')->count(),
                'totalUnits' => Unit::count(),
                'activeEnrollments' => 0, // Implement based on your enrollment system
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching faculty dashboard stats', [
                'school_code' => $schoolCode,
                'error' => $e->getMessage()
            ]);
        }

        // Get recent activities (generic placeholder)
        $recentActivities = [
            [
                'id' => 1,
                'type' => 'enrollment',
                'description' => 'New student enrollment approved',
                'created_at' => now()->subHours(2)->toISOString(),
            ],
            [
                'id' => 2,
                'type' => 'lecturer_assignment',
                'description' => 'Lecturer assigned to new unit',
                'created_at' => now()->subHours(5)->toISOString(),
            ],
        ];

        // Get pending approvals (customize based on your approval system)
        $pendingApprovals = [
            'enrollments' => 5,
            'lecturerRequests' => 2,
            'unitChanges' => 3,
        ];

        Log::info('Faculty dashboard accessed', [
            'user_id' => $user->id,
            'school_code' => $schoolCode,
            'school_name' => $schoolName,
            'stats' => $stats
        ]);

        return Inertia::render('FacultyAdmin/' . strtolower($schoolCode) . '/Dashboard', [
            'schoolCode' => $schoolCode,
            'schoolName' => $schoolName,
            'currentSemester' => $currentSemester,
            'statistics' => [
                'totalStudents' => [
                    'count' => $stats['totalStudents'],
                    'growthRate' => 8.5,
                    'period' => 'vs last semester'
                ],
                'totalLecturers' => [
                    'count' => $stats['totalLecturers'],
                    'growthRate' => 12.3,
                    'period' => 'vs last year'
                ],
                'totalUnits' => [
                    'count' => $stats['totalUnits'],
                    'growthRate' => 5.2,
                    'period' => 'vs last semester'
                ],
                'activeEnrollments' => [
                    'count' => $stats['activeEnrollments'],
                    'growthRate' => 15.7,
                    'period' => 'vs last semester'
                ],
            ],
            'facultyInfo' => [
                'name' => $schoolName,
                'code' => $schoolCode,
                'totalPrograms' => 5, // You can make this dynamic
                'totalClasses' => 30,  // You can make this dynamic
            ],
            'pendingApprovals' => $pendingApprovals,
            'recentActivities' => $recentActivities,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Default dashboard for users without specific roles
     */
    private function defaultDashboard()
    {
        $user = auth()->user();

        Log::info('Showing default dashboard', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
        ]);

        return Inertia::render('Dashboard', [
            'message' => 'Welcome to the Timetabling System',
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames(),
            'debugInfo' => [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ]
        ]);
    }

    /**
     * Get user dashboard based on role (API endpoint)
     */
    public function getUserDashboard(Request $request)
    {
        $user = $request->user();
        $dashboardRoute = 'dashboard';

        // Check for Faculty Admin roles first (school-specific)
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $faculty = str_replace('Faculty Admin - ', '', $role);
                $schoolRoute = match($faculty) {
                    'SCES' => 'faculty.dashboard.sces',
                    'SBS' => 'faculty.dashboard.sbs',
                    'SLS' => 'faculty.dashboard.sls',
                    'TOURISM' => 'faculty.dashboard.tourism',
                    'SHM' => 'faculty.dashboard.shm',
                    'SHS' => 'faculty.dashboard.shs',
                    default => null
                };
                
                if ($schoolRoute) {
                    $dashboardRoute = $schoolRoute;
                    break;
                }
            }
        }

        // Fallback to generic role checks
        if ($dashboardRoute === 'dashboard') {
            if ($user->hasRole('Admin')) {
                $dashboardRoute = 'admin.dashboard';
            } elseif ($user->hasRole('Student')) {
                $dashboardRoute = 'student.dashboard';
            } elseif ($user->hasRole('Lecturer')) {
                $dashboardRoute = 'lecturer.dashboard';
            } elseif ($user->hasRole('Exam Office')) {
                $dashboardRoute = 'exam-office.dashboard';
            } elseif ($user->hasRole('Faculty Admin')) {
                $dashboardRoute = 'faculty-admin.dashboard';
            }
        }

        return response()->json([
            'dashboard_route' => $dashboardRoute,
            'user_roles' => $user->getRoleNames(),
            'user_permissions' => $user->getAllPermissions()->pluck('name')
        ]);
    }
}