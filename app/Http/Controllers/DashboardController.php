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
use App\Models\ClassModel;
use App\Models\School;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
                    'SCES' => 'schoolAdmin.dashboard',
                    'SBS' => 'schoolAdmin.dashboard',
                    'SET' => 'schoolAdmin.dashboard',
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
     * Enhanced admin dashboard with real data from your database
     */
    public function adminDashboard(Request $request)
    {
        $user = $request->user();

        // Ensure permissions are loaded
        $user->load('roles.permissions', 'permissions');
        $allPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

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

            // Date calculations for comparisons
            $now = Carbon::now();
            $lastWeek = $now->copy()->subWeek();
            $lastMonth = $now->copy()->subMonth();

            // ===== REAL STATISTICS FROM YOUR DATABASE =====
            
            // 1. Total Users Statistics (from users table)
            $totalUsers = User::count();
            $usersLastMonth = User::whereBetween('created_at', [$lastMonth, $now])->count();
            $usersPreviousMonth = User::whereBetween('created_at', 
                [$lastMonth->copy()->subMonth(), $lastMonth])->count();
            $usersGrowthRate = $this->calculateGrowthRate($usersLastMonth, $usersPreviousMonth);

            // 2. Active Enrollments Statistics (from enrollments table)
            $activeEnrollments = DB::table('enrollments')
                ->where('status', 'enrolled')
                ->count();

            $enrollmentsLastWeek = DB::table('enrollments')
                ->where('status', 'enrolled')
                ->whereBetween('created_at', [$lastWeek, $now])
                ->count();

            $enrollmentsPreviousWeek = DB::table('enrollments')
                ->where('status', 'enrolled')
                ->whereBetween('created_at', [$lastWeek->copy()->subWeek(), $lastWeek])
                ->count();

            $enrollmentsGrowthRate = $this->calculateGrowthRate($enrollmentsLastWeek, $enrollmentsPreviousWeek);

            // 3. Active Classes Statistics (from classes table)
            $activeClasses = DB::table('classes')
                ->where('is_active', 1)
                ->count();

            $classesLastMonth = DB::table('classes')
                ->whereBetween('created_at', [$lastMonth, $now])
                ->count();

            $classesPreviousMonth = DB::table('classes')
                ->whereBetween('created_at', [$lastMonth->copy()->subMonth(), $lastMonth])
                ->count();

            $classesGrowthRate = $this->calculateGrowthRate($classesLastMonth, $classesPreviousMonth);

            // 4. Get actual classrooms count
            $totalClassrooms = DB::table('classrooms')->count();
            
            // 5. Exam Sessions Statistics (using actual exam-related data if available)
            $examSessions = 0;
            $examsGrowthRate = 0;
            
            try {
                // Try different possible exam table names
                $examSessions = DB::table('exam_timetables')->count();
            } catch (\Exception $e) {
                try {
                    $examSessions = DB::table('examrooms')->count();
                } catch (\Exception $e2) {
                    // Use unit_assignments as a proxy for exam sessions
                    $examSessions = DB::table('unit_assignments')->count();
                }
            }

            // ===== SYSTEM INFO FROM YOUR DATABASE =====
            $systemInfo = [
                'totalSchools' => DB::table('schools')->count(), // 3 schools from your DB
                'totalSemesters' => DB::table('semesters')->count(), // 2 semesters from your DB
                'totalBuildings' => DB::table('building')->count(), // 6 buildings from your DB
                'totalClassrooms' => $totalClassrooms, // 7 classrooms from your DB
                'totalPrograms' => DB::table('programs')->count(), // 9 programs from your DB
                'activeUsers' => User::where('created_at', '>=', $now->subDays(30))->count(),
            ];

            // ===== REAL ROLE STATISTICS FROM YOUR DATABASE =====
            $roleStats = [
                'admins' => User::whereHas('roles', function($query) {
                    $query->where('name', 'Admin');
                })->count(),
                'students' => User::whereHas('roles', function($query) {
                    $query->where('name', 'Student');
                })->count(),
                'lecturers' => User::whereHas('roles', function($query) {
                    $query->where('name', 'Lecturer');
                })->count(),
                'faculty_admins' => User::whereHas('roles', function($query) {
                    $query->where('name', 'like', 'Faculty Admin%');
                })->count(),
                'exam_office' => User::whereHas('roles', function($query) {
                    $query->where('name', 'Exam Office');
                })->count(),
            ];

            // ===== RECENT ACTIVITIES FROM YOUR DATABASE =====
            $recentEnrollments = DB::table('enrollments')
                ->join('users', 'enrollments.student_code', '=', 'users.code')
                ->join('classes', 'enrollments.class_id', '=', 'classes.id')
                ->select(
                    'enrollments.*',
                    'classes.name as unit_name',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as student_name"),
                    'enrollments.created_at'
                )
                ->where('enrollments.status', 'enrolled')
                ->orderBy('enrollments.created_at', 'desc')
                ->limit(5)
                ->get();

            // ===== REAL CHART DATA FROM DATABASE =====
            
            // Get real enrollment trends (last 6 months)
            $enrollmentTrends = $this->getEnrollmentTrends($currentSemester, 6);
            
            // Get real weekly activity (last 7 days)
            $weeklyActivity = $this->getWeeklyActivity($currentSemester);
            
            // Get real infrastructure stats
            $infrastructureStats = $this->getInfrastructureStats();

            // ===== PREPARE DASHBOARD DATA =====
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
                'systemInfo' => $systemInfo,
                'roleStats' => $roleStats,
                'recentEnrollments' => $recentEnrollments,
                'enrollmentTrends' => $enrollmentTrends,
                'weeklyActivity' => $weeklyActivity,
                'infrastructureStats' => $infrastructureStats,
                'userPermissions' => $allPermissions,
                'userRoles' => $roles,
                'isAdmin' => true
            ];

            Log::info('Real admin dashboard data generated successfully', [
                'total_users' => $totalUsers,
                'active_enrollments' => $activeEnrollments,
                'active_classes' => $activeClasses,
                'exam_sessions' => $examSessions,
                'total_schools' => $systemInfo['totalSchools'],
                'total_buildings' => $systemInfo['totalBuildings'],
                'total_classrooms' => $systemInfo['totalClassrooms'],
                'current_semester' => $currentSemester ? $currentSemester->name : 'None',
                'user_id' => $user->id
            ]);

            return Inertia::render('Admin/Dashboard', $dashboardData);

        } catch (\Exception $e) {
            Log::error('Error in enhanced admin dashboard', [
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
                'systemInfo' => ['totalSchools' => 0, 'totalSemesters' => 0, 'totalBuildings' => 0, 'totalClassrooms' => 0, 'activeUsers' => 0],
                'roleStats' => ['admins' => 0, 'students' => 0, 'lecturers' => 0, 'faculty_admins' => 0, 'exam_office' => 0],
                'recentEnrollments' => [],
                'enrollmentTrends' => [],
                'weeklyActivity' => [],
                'infrastructureStats' => [],
                'userPermissions' => [],
                'userRoles' => [],
                'isAdmin' => true,
                'error' => 'Unable to load dashboard data'
            ]);
        }
    }

    /**
     * Calculate growth rate percentage
     */
    private function calculateGrowthRate($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get enrollment trends for charts (last N months) - REAL DATA
     */
    private function getEnrollmentTrends($currentSemester, $months = 6)
    {
        $trends = [];
        
        try {
            $startDate = Carbon::now()->subMonths($months);
            
            for ($i = 0; $i < $months; $i++) {
                $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $monthName = $monthStart->format('M');
                
                // Get REAL enrollments for this month from your enrollments table
                $enrollments = DB::table('enrollments')
                    ->where('status', 'enrolled')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
                
                // Get REAL classes created this month
                $classes = DB::table('classes')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
                
                $trends[] = [
                    'name' => $monthName,
                    'enrollments' => $enrollments
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Error generating real enrollment trends', ['error' => $e->getMessage()]);
            // Return empty array if fails
            $trends = [];
        }
        
        return $trends;
    }

    /**
     * Get weekly activity data (last 7 days) - REAL DATA
     */
    private function getWeeklyActivity($currentSemester)
    {
        $activity = [];
        
        try {
            $startDate = Carbon::now()->subDays(6); // Last 7 days including today
            
            for ($i = 0; $i < 7; $i++) {
                $date = $startDate->copy()->addDays($i);
                $dayName = $date->format('D');
                
                // Count REAL enrollments created this day
                $enrollments = DB::table('enrollments')
                    ->where('status', 'enrolled')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->count();
                
                $activity[] = [
                    'day' => $dayName,
                    'enrollments' => $enrollments
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Error generating real weekly activity', ['error' => $e->getMessage()]);
            // Return empty array if fails
            $activity = [];
        }
        
        return $activity;
    }

    /**
     * Get infrastructure statistics (buildings, classrooms, etc.) - REAL DATA
     */
    private function getInfrastructureStats()
    {
        $stats = [];
        
        try {
            // REAL Buildings statistics from your 'building' table
            $totalBuildings = DB::table('building')->count();
            $activeBuildings = DB::table('building')->where('is_active', true)->count();
            
            // REAL Classrooms statistics from your 'classrooms' table
            $totalClassrooms = DB::table('classrooms')->count();
            $availableClassrooms = DB::table('classrooms')->where('is_active', 1)->count();
            
            // REAL Building utilization
            $buildingsWithClassrooms = DB::table('building')
                ->join('classrooms', 'building.id', '=', 'classrooms.building_id')
                ->distinct('building.id')
                ->count();
            
            $stats = [
                'buildings' => [
                    'total' => $totalBuildings,
                    'active' => $activeBuildings,
                    'with_classrooms' => $buildingsWithClassrooms,
                    'utilization_rate' => $totalBuildings > 0 ? round(($buildingsWithClassrooms / $totalBuildings) * 100, 1) : 0
                ],
                'classrooms' => [
                    'total' => $totalClassrooms,
                    'available' => $availableClassrooms,
                    'utilization_rate' => $totalClassrooms > 0 ? round(($availableClassrooms / $totalClassrooms) * 100, 1) : 0
                ]
            ];
        } catch (\Exception $e) {
            Log::warning('Error generating real infrastructure stats', ['error' => $e->getMessage()]);
            $stats = [
                'buildings' => ['total' => 0, 'active' => 0, 'with_classrooms' => 0, 'utilization_rate' => 0],
                'classrooms' => ['total' => 0, 'available' => 0, 'utilization_rate' => 0]
            ];
        }
        
        return $stats;
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
 * SCES Faculty Dashboard - FIXED to use unified units table
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

    // Get SCES school
    $scesSchool = School::where('code', 'SCES')->first();
    
    if (!$scesSchool) {
        Log::error('SCES school not found in database');
        abort(404, 'SCES school not found');
    }

    // Get current semester
    $currentSemester = Semester::where('is_active', true)->first();
    
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }

    try {
        // Get all SCES programs
        $scesPrograms = Program::where('school_id', $scesSchool->id)->get();
        $scesProgramIds = $scesPrograms->pluck('id')->toArray();

        // Get statistics for SCES
        $scesStats = [
            'total_students' => 0,
            'total_lecturers' => 0,
            'total_units' => 0,
            'active_enrollments' => 0,
        ];

        // Get units count for SCES programs
        $scesStats['total_units'] = Unit::whereIn('program_id', $scesProgramIds)
            ->where('is_active', 1)
            ->count();

        // Get active enrollments for current semester
        if ($currentSemester) {
            $scesStats['active_enrollments'] = Enrollment::where('semester_id', $currentSemester->id)
                ->whereHas('unit', function($query) use ($scesProgramIds) {
                    $query->whereIn('program_id', $scesProgramIds);
                })
                ->where('status', 'enrolled')
                ->count();
        }

        // Get total students enrolled in SCES programs
        $scesStats['total_students'] = Enrollment::whereHas('unit', function($query) use ($scesProgramIds) {
                $query->whereIn('program_id', $scesProgramIds);
            })
            ->distinct('student_code')
            ->count('student_code');

        // Get total lecturers assigned to SCES units
        $scesStats['total_lecturers'] = DB::table('lecturer_assignments')
            ->join('units', 'lecturer_assignments.unit_id', '=', 'units.id')
            ->whereIn('units.program_id', $scesProgramIds)
            ->distinct('lecturer_assignments.lecturer_code')
            ->count();

        // Calculate growth rates
        $previousSemester = Semester::where('id', '<', $currentSemester->id ?? 0)
            ->orderBy('id', 'desc')
            ->first();

        $studentsGrowthRate = 0;
        $lecturersGrowthRate = 0;
        $unitsGrowthRate = 0;
        $enrollmentsGrowthRate = 0;

        if ($previousSemester) {
            // Students growth rate
            $previousStudents = Enrollment::where('semester_id', $previousSemester->id)
                ->whereHas('unit', function($query) use ($scesProgramIds) {
                    $query->whereIn('program_id', $scesProgramIds);
                })
                ->distinct('student_code')
                ->count('student_code');
            
            $studentsGrowthRate = $this->calculateGrowthRate($scesStats['total_students'], $previousStudents);

            // Enrollments growth rate
            $previousEnrollments = Enrollment::where('semester_id', $previousSemester->id)
                ->whereHas('unit', function($query) use ($scesProgramIds) {
                    $query->whereIn('program_id', $scesProgramIds);
                })
                ->where('status', 'enrolled')
                ->count();
            
            $enrollmentsGrowthRate = $this->calculateGrowthRate($scesStats['active_enrollments'], $previousEnrollments);

            // Units growth rate
            $previousUnits = Unit::whereIn('program_id', $scesProgramIds)
                ->where('created_at', '<', $currentSemester->created_at)
                ->count();
            
            $unitsGrowthRate = $this->calculateGrowthRate($scesStats['total_units'], $previousUnits);
        }

        // Get recent activities
        $recentActivities = Enrollment::whereHas('unit', function($query) use ($scesProgramIds) {
                $query->whereIn('program_id', $scesProgramIds);
            })
            ->with(['unit', 'student'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'type' => 'enrollment',
                    'description' => ($enrollment->student ? $enrollment->student->first_name . ' ' . $enrollment->student->last_name : 'Student') . 
                                   ' enrolled in ' . ($enrollment->unit ? $enrollment->unit->name : 'Unit'),
                    'created_at' => $enrollment->created_at->toISOString(),
                ];
            });

        // Calculate pending approvals
        $pendingApprovals = [
            'enrollments' => Enrollment::where('semester_id', $currentSemester->id ?? 0)
                ->whereHas('unit', function($query) use ($scesProgramIds) {
                    $query->whereIn('program_id', $scesProgramIds);
                })
                ->where('status', 'pending')
                ->count(),
            'lecturerRequests' => 0, // You can implement this based on your business logic
            'unitChanges' => Unit::whereIn('program_id', $scesProgramIds)
                ->where('is_active', 0)
                ->count(),
        ];

        // Get program-specific unit counts
        $programsWithUnits = $scesPrograms->map(function($program) {
            return [
                'name' => $program->name,
                'code' => $program->code,
                'units' => Unit::where('program_id', $program->id)
                    ->where('is_active', 1)
                    ->count(),
            ];
        });

        Log::info('SCES dashboard data generated', [
            'user_id' => $user->id,
            'stats' => $scesStats,
            'current_semester' => $currentSemester ? $currentSemester->name : 'None',
        ]);
        
        return Inertia::render('SchoolAdmin/Dashboard', [
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
                    'count' => $scesStats['total_units'],
                    'growthRate' => $unitsGrowthRate,
                    'period' => 'vs last semester'
                ],
                'activeEnrollments' => [
                    'count' => $scesStats['active_enrollments'],
                    'growthRate' => $enrollmentsGrowthRate,
                    'period' => 'vs last semester'
                ],
            ],
            'programs' => $programsWithUnits,
            'facultyInfo' => [
                'name' => 'School of Computing and Engineering Sciences',
                'code' => 'SCES',
                'totalPrograms' => $scesPrograms->count(),
                'totalClasses' => ClassModel::whereIn('program_id', $scesProgramIds)
                    ->where('is_active', true)
                    ->count(),
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
        return Inertia::render('SchoolAdmin/Dashboard', [
            'schoolCode' => 'SCES',
            'schoolName' => 'School of Computing and Engineering Sciences',
            'currentSemester' => $currentSemester,
            'statistics' => [
                'totalStudents' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
                'totalLecturers' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
                'totalUnits' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
                'activeEnrollments' => ['count' => 0, 'growthRate' => 0, 'period' => 'vs last semester'],
            ],
            'programs' => [],
            'facultyInfo' => [
                'name' => 'School of Computing and Engineering Sciences',
                'code' => 'SCES',
                'totalPrograms' => 0,
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
            'error' => 'Unable to load dashboard data: ' . $e->getMessage()
        ]);
    }
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

        return Inertia::render('Schools/' . strtolower($schoolCode) . '/Programs/Dashboard', [
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

    
    /**
 * Display the Class Timetable office dashboard.
 */
public function classtimetablesDashboard(Request $request)
{
    $user = $request->user();

    // Check if user has permission to view class timetable office dashboard
    if (!$user->can('view-class-timetables') && !$user->hasRole('Class Timetable office')) {
        Log::warning('Unauthorized access to class timetable office dashboard', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray()
        ]);
        abort(403, 'Unauthorized access to class timetable office dashboard.');
    }

    try {
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }

        // Date calculations
        $now = Carbon::now();
        $lastWeek = $now->copy()->subWeek();
        $lastMonth = $now->copy()->subMonth();

        // ===== TIMETABLE STATISTICS =====
        
        // Total class timetables
        $totalTimetables = ClassTimetable::count();
        $timetablesLastWeek = ClassTimetable::whereBetween('created_at', [$lastWeek, $now])->count();
        $timetablesPreviousWeek = ClassTimetable::whereBetween('created_at', 
            [$lastWeek->copy()->subWeek(), $lastWeek])->count();
        $timetablesGrowthRate = $this->calculateGrowthRate($timetablesLastWeek, $timetablesPreviousWeek);

        // Active classes with timetables
        $activeClasses = ClassTimetable::distinct('class_id')->count('class_id');
        
        // Conflict detection
        $conflicts = $this->detectTimetableConflicts();
        
        // Venues usage
        $totalVenues = DB::table('classrooms')->count();
        $venuesInUse = ClassTimetable::distinct('venue')->count('venue');
        
        // Teaching mode distribution
        $physicalClasses = ClassTimetable::where('teaching_mode', 'physical')->count();
        $onlineClasses = ClassTimetable::where('teaching_mode', 'online')->count();

        // ===== SEMESTER-SPECIFIC DATA =====
        $semesterTimetables = 0;
        $semesterConflicts = 0;
        
        if ($currentSemester) {
            $semesterTimetables = ClassTimetable::where('semester_id', $currentSemester->id)->count();
            $semesterConflicts = count($this->detectSemesterConflicts($currentSemester->id));
        }

        // ===== RECENT ACTIVITIES =====
        $recentTimetables = ClassTimetable::with(['unit', 'class'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($timetable) {
                return [
                    'id' => $timetable->id,
                    'type' => 'timetable_created',
                    'description' => 'Timetable created for ' . 
                                   ($timetable->unit ? $timetable->unit->name : 'Unit') . 
                                   ' - ' . $timetable->day . ' ' . 
                                   $timetable->start_time . '-' . $timetable->end_time,
                    'created_at' => $timetable->created_at->toISOString(),
                ];
            });

        // ===== DAILY SCHEDULE OVERVIEW =====
        $dailySchedule = $this->getDailyScheduleOverview($currentSemester);

        // ===== VENUE UTILIZATION =====
        $venueUtilization = $this->getVenueUtilization();

        Log::info('Class Timetable office dashboard data generated', [
            'user_id' => $user->id,
            'total_timetables' => $totalTimetables,
            'semester_timetables' => $semesterTimetables,
            'conflicts_found' => $conflicts['total_conflicts'],
            'current_semester' => $currentSemester ? $currentSemester->name : 'None'
        ]);

        return Inertia::render('ClassTimetables/Dashboard', [
            'statistics' => [
                'totalTimetables' => [
                    'count' => $totalTimetables,
                    'growthRate' => $timetablesGrowthRate,
                    'period' => 'from last week'
                ],
                'activeClasses' => [
                    'count' => $activeClasses,
                    'growthRate' => 0,
                    'period' => 'current semester'
                ],
                'conflicts' => [
                    'count' => $conflicts['total_conflicts'],
                    'severity' => $conflicts['severity'],
                    'period' => 'requires attention'
                ],
                'venueUtilization' => [
                    'count' => $venuesInUse,
                    'total' => $totalVenues,
                    'percentage' => $totalVenues > 0 ? round(($venuesInUse / $totalVenues) * 100, 1) : 0,
                    'period' => 'venues in use'
                ]
            ],
            'currentSemester' => $currentSemester,
            'teachingModeDistribution' => [
                'physical' => $physicalClasses,
                'online' => $onlineClasses,
                'total' => $physicalClasses + $onlineClasses
            ],
            'conflictDetails' => $conflicts['conflicts'],
            'recentActivities' => $recentTimetables,
            'dailySchedule' => $dailySchedule,
            'venueUtilization' => $venueUtilization,
            'quickActions' => [
                [
                    'title' => 'Create Timetable',
                    'description' => 'Add new class timetable',
                    'route' => 'classtimetables.create',
                    'icon' => 'plus'
                ],
                [
                    'title' => 'View Conflicts',
                    'description' => 'Resolve scheduling conflicts',
                    'route' => 'classtimetables.conflicts',
                    'icon' => 'alert'
                ],
                [
                    'title' => 'Manage Venues',
                    'description' => 'Configure classrooms',
                    'route' => 'admin.classrooms.index',
                    'icon' => 'building'
                ]
            ],
            // ADD PERMISSIONS FOR CLASS TIMETABLE OFFICE
            'can' => [
                'create' => $user->can('create-class-timetables') || $user->hasRole('Class Timetable office'),
                'edit' => $user->can('edit-class-timetables') || $user->hasRole('Class Timetable office'),
                'delete' => $user->can('delete-class-timetables') || $user->hasRole('Class Timetable office'),
                'download' => $user->can('download-class-timetables') || $user->hasRole('Class Timetable office'),
                'solve_conflicts' => $user->can('solve-conflicts') || $user->hasRole('Class Timetable office'),
            ],
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);

    } catch (\Exception $e) {
        Log::error('Error in Class Timetable office dashboard', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Return safe defaults with permissions
        return Inertia::render('ClassTimetables/Dashboard', [
            'statistics' => [
                'totalTimetables' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last week'],
                'activeClasses' => ['count' => 0, 'growthRate' => 0, 'period' => 'current semester'],
                'conflicts' => ['count' => 0, 'severity' => 'none', 'period' => 'requires attention'],
                'venueUtilization' => ['count' => 0, 'total' => 0, 'percentage' => 0, 'period' => 'venues in use']
            ],
            'currentSemester' => null,
            'teachingModeDistribution' => ['physical' => 0, 'online' => 0, 'total' => 0],
            'conflictDetails' => [],
            'recentActivities' => [],
            'dailySchedule' => [],
            'venueUtilization' => [],
            'quickActions' => [],
            'can' => [
                'create' => $user->can('create-class-timetables') || $user->hasRole('Class Timetable office'),
                'edit' => $user->can('edit-class-timetables') || $user->hasRole('Class Timetable office'),
                'delete' => $user->can('delete-class-timetables') || $user->hasRole('Class Timetable office'),
                'download' => $user->can('download-class-timetables') || $user->hasRole('Class Timetable office'),
                'solve_conflicts' => $user->can('solve-conflicts') || $user->hasRole('Class Timetable office'),
            ],
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames(),
            'error' => 'Unable to load dashboard data'
        ]);
    }
}

/**
 * Detect timetable conflicts
 */
private function detectTimetableConflicts()
{
    $conflicts = [];
    $severity = 'none';
    
    try {
        // Lecturer conflicts
        $lecturerConflicts = DB::select("
            SELECT lecturer, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY lecturer, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        // Venue conflicts
        $venueConflicts = DB::select("
            SELECT venue, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            WHERE teaching_mode = 'physical'
            GROUP BY venue, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        foreach ($lecturerConflicts as $conflict) {
            $conflicts[] = [
                'type' => 'lecturer',
                'description' => "Lecturer '{$conflict->lecturer}' has {$conflict->conflict_count} classes at {$conflict->day} {$conflict->start_time}",
                'severity' => 'high'
            ];
        }

        foreach ($venueConflicts as $conflict) {
            $conflicts[] = [
                'type' => 'venue',
                'description' => "Venue '{$conflict->venue}' is double-booked on {$conflict->day} at {$conflict->start_time}",
                'severity' => 'high'
            ];
        }

        $totalConflicts = count($lecturerConflicts) + count($venueConflicts);
        
        if ($totalConflicts > 10) {
            $severity = 'critical';
        } elseif ($totalConflicts > 5) {
            $severity = 'high';
        } elseif ($totalConflicts > 0) {
            $severity = 'medium';
        }

        return [
            'total_conflicts' => $totalConflicts,
            'conflicts' => $conflicts,
            'severity' => $severity
        ];

    } catch (\Exception $e) {
        Log::error('Error detecting conflicts: ' . $e->getMessage());
        return ['total_conflicts' => 0, 'conflicts' => [], 'severity' => 'none'];
    }
}

/**
 * Detect conflicts for a specific semester
 */
private function detectSemesterConflicts($semesterId)
{
    $conflicts = [];
    
    try {
        $lecturerConflicts = DB::select("
            SELECT lecturer, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            WHERE semester_id = ?
            GROUP BY lecturer, day, start_time, end_time
            HAVING COUNT(*) > 1
        ", [$semesterId]);

        return $lecturerConflicts;
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Get daily schedule overview
 */
private function getDailyScheduleOverview($currentSemester)
{
    if (!$currentSemester) {
        return [];
    }

    try {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $schedule = [];

        foreach ($days as $day) {
            $count = ClassTimetable::where('semester_id', $currentSemester->id)
                ->where('day', $day)
                ->count();
            
            $schedule[] = [
                'day' => $day,
                'classes' => $count
            ];
        }

        return $schedule;
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Get venue utilization data
 */
private function getVenueUtilization()
{
    try {
        return ClassTimetable::select('venue', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('venue')
            ->orderBy('usage_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'venue' => $item->venue,
                    'usage' => $item->usage_count
                ];
            })
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}