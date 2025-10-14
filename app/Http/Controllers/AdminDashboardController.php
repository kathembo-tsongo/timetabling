<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\School;
use App\Models\Program;
use Carbon\Carbon;

/**
 * DEBUG VERSION - School Admin Dashboard
 * This will help us see what data exists and why counts are 0
 */
class DashboardController extends Controller
{
    /**
     * SCES Dashboard - DEBUG VERSION
     */
    public function scesDashboard()
    {
        $user = auth()->user();
        
        if (!$user->can('view-faculty-dashboard-sces') && !$user->hasRole('Faculty Admin - SCES')) {
            abort(403, 'Unauthorized access to SCES faculty dashboard.');
        }

        try {
            // Get SCES school
            $scesSchool = School::where('code', 'SCES')->first();
            
            if (!$scesSchool) {
                Log::error('SCES school not found');
                return $this->returnErrorDashboard('SCES school not found in database');
            }

            Log::info('SCES School found', ['school_id' => $scesSchool->id, 'school_name' => $scesSchool->name]);

            // Get current semester
            $currentSemester = DB::table('semesters')->where('is_active', true)->first();
            if (!$currentSemester) {
                $currentSemester = DB::table('semesters')->orderBy('id', 'desc')->first();
            }

            Log::info('Current Semester', ['semester' => $currentSemester]);

            // Get all SCES programs
            $scesPrograms = DB::table('programs')
                ->where('school_id', $scesSchool->id)
                ->get();
            
            $scesProgramIds = $scesPrograms->pluck('id')->toArray();
            $scesProgramCodes = $scesPrograms->pluck('code')->toArray();

            Log::info('SCES Programs', [
                'count' => count($scesPrograms),
                'program_ids' => $scesProgramIds,
                'program_codes' => $scesProgramCodes
            ]);

            // DEBUG: Check what tables exist
            $tables = DB::select('SHOW TABLES');
            $tableNames = array_map(function($table) {
                return array_values((array)$table)[0];
            }, $tables);
            
            Log::info('Available Tables', ['tables' => $tableNames]);

            // DEBUG: Check different enrollment table possibilities
            $enrollmentTables = [];
            foreach (['enrollments', 'bbit_enrollments', 'unit_assignments'] as $table) {
                if (in_array($table, $tableNames)) {
                    $count = DB::table($table)->count();
                    $enrollmentTables[$table] = $count;
                    Log::info("Table $table", ['count' => $count]);
                    
                    // Get sample records
                    $sample = DB::table($table)->limit(3)->get();
                    Log::info("Sample from $table", ['data' => $sample]);
                }
            }

            // DEBUG: Check units table
            $unitsCount = DB::table('units')->count();
            $scesUnitsCount = DB::table('units')->whereIn('program_id', $scesProgramIds)->count();
            
            Log::info('Units Table', [
                'total_units' => $unitsCount,
                'sces_units' => $scesUnitsCount,
                'sces_program_ids' => $scesProgramIds
            ]);

            // Get sample units
            $sampleUnits = DB::table('units')
                ->whereIn('program_id', $scesProgramIds)
                ->limit(5)
                ->get();
            Log::info('Sample SCES Units', ['units' => $sampleUnits]);

            // DEBUG: Check classes table
            $classesCount = DB::table('classes')->count();
            Log::info('Classes Table', ['total_classes' => $classesCount]);

            // DEBUG: Check lecturer_assignments table
            if (in_array('lecturer_assignments', $tableNames)) {
                $lecturerAssignmentsCount = DB::table('lecturer_assignments')->count();
                Log::info('Lecturer Assignments', ['count' => $lecturerAssignmentsCount]);
                
                $sampleLecturerAssignments = DB::table('lecturer_assignments')->limit(3)->get();
                Log::info('Sample Lecturer Assignments', ['data' => $sampleLecturerAssignments]);
            }

            // Now let's try to get actual statistics using the correct tables
            $stats = $this->getActualStats($scesSchool->id, $scesProgramIds, $currentSemester);

            // Get programs data
            $programsData = [];
            foreach ($scesPrograms as $program) {
                $programsData[] = [
                    'id' => $program->id,
                    'name' => $program->name,
                    'code' => $program->code,
                    'degree' => $program->degree_type ?? 'Bachelor',
                    'duration' => $program->duration ?? 4,
                    'totalUnits' => $this->countUnitsForProgram($program->id),
                    'enrolledStudents' => $this->countStudentsForProgram($program->id, $currentSemester),
                    'capacity' => $program->capacity ?? 100,
                    'growth' => 0,
                    'colorClass' => $this->getProgramColorClass($program->code),
                ];
            }

            Log::info('Programs Data Compiled', ['programs' => $programsData]);

            // Get recent activities
            $recentActivities = $this->getRecentActivitiesDebug($scesProgramIds);

            $dashboardData = [
                'schoolName' => 'School of Computing and Engineering Sciences',
                'schoolCode' => 'SCES',
                'currentSemester' => $currentSemester ? [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name,
                    'is_active' => $currentSemester->is_active,
                ] : null,
                'stats' => [
                    'totalStudents' => $stats['total_students'],
                    'studentsTrend' => '0%',
                    'activePrograms' => count($programsData),
                    'programsTrend' => '0%',
                    'totalUnits' => $stats['total_units'],
                    'unitsTrend' => '0%',
                    'totalLecturers' => $stats['total_lecturers'],
                    'lecturersTrend' => '0%',
                    'activeEnrollments' => $stats['active_enrollments'],
                    'enrollmentsTrend' => '0%',
                ],
                'programs' => $programsData,
                'recentActivities' => $recentActivities,
                'upcomingEvents' => [],
                'pendingApprovals' => [],
                'debugInfo' => [
                    'school_id' => $scesSchool->id,
                    'program_count' => count($scesPrograms),
                    'program_ids' => $scesProgramIds,
                    'enrollment_tables' => $enrollmentTables,
                    'semester_id' => $currentSemester->id ?? null,
                ],
                'userPermissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'userRoles' => $user->getRoleNames()->toArray(),
            ];

            Log::info('Final Dashboard Data', $dashboardData);

            return Inertia::render('SchoolAdmin/Dashboard', $dashboardData);

        } catch (\Exception $e) {
            Log::error('Error in SCES dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->returnErrorDashboard('Error: ' . $e->getMessage());
        }
    }

    /**
     * Get actual statistics from the database
     */
    private function getActualStats($schoolId, $programIds, $currentSemester)
    {
        $stats = [
            'total_students' => 0,
            'total_lecturers' => 0,
            'total_units' => 0,
            'active_enrollments' => 0,
        ];

        try {
            // Count units
            $stats['total_units'] = DB::table('units')
                ->whereIn('program_id', $programIds)
                ->where('is_active', 1)
                ->count();

            Log::info('Units count', ['count' => $stats['total_units']]);

            // Try different enrollment tables
            if (DB::getSchemaBuilder()->hasTable('bbit_enrollments')) {
                // Count students from bbit_enrollments
                $stats['total_students'] = DB::table('bbit_enrollments')
                    ->distinct('student_code')
                    ->count('student_code');

                Log::info('Students from bbit_enrollments', ['count' => $stats['total_students']]);

                if ($currentSemester) {
                    $stats['active_enrollments'] = DB::table('bbit_enrollments')
                        ->where('semester_id', $currentSemester->id)
                        ->count();
                }
            } elseif (DB::getSchemaBuilder()->hasTable('enrollments')) {
                // Try standard enrollments table
                $stats['total_students'] = DB::table('enrollments')
                    ->join('units', 'enrollments.unit_id', '=', 'units.id')
                    ->whereIn('units.program_id', $programIds)
                    ->distinct('enrollments.student_code')
                    ->count('enrollments.student_code');

                Log::info('Students from enrollments', ['count' => $stats['total_students']]);

                if ($currentSemester) {
                    $stats['active_enrollments'] = DB::table('enrollments')
                        ->join('units', 'enrollments.unit_id', '=', 'units.id')
                        ->whereIn('units.program_id', $programIds)
                        ->where('enrollments.semester_id', $currentSemester->id)
                        ->where('enrollments.status', 'enrolled')
                        ->count();
                }
            } elseif (DB::getSchemaBuilder()->hasTable('unit_assignments')) {
                // Try unit_assignments table
                $stats['total_students'] = DB::table('unit_assignments')
                    ->join('units', 'unit_assignments.unit_id', '=', 'units.id')
                    ->whereIn('units.program_id', $programIds)
                    ->distinct('unit_assignments.student_code')
                    ->count('unit_assignments.student_code');

                Log::info('Students from unit_assignments', ['count' => $stats['total_students']]);
            }

            // Count lecturers from lecturer_assignments
            if (DB::getSchemaBuilder()->hasTable('lecturer_assignments')) {
                $stats['total_lecturers'] = DB::table('lecturer_assignments')
                    ->join('units', 'lecturer_assignments.unit_id', '=', 'units.id')
                    ->whereIn('units.program_id', $programIds)
                    ->distinct('lecturer_assignments.lecturer_code')
                    ->count('lecturer_assignments.lecturer_code');

                Log::info('Lecturers from lecturer_assignments', ['count' => $stats['total_lecturers']]);
            } elseif (DB::getSchemaBuilder()->hasTable('bbit_enrollments')) {
                // Try getting lecturers from bbit_enrollments
                $stats['total_lecturers'] = DB::table('bbit_enrollments')
                    ->whereNotNull('lecturer_code')
                    ->distinct('lecturer_code')
                    ->count('lecturer_code');

                Log::info('Lecturers from bbit_enrollments', ['count' => $stats['total_lecturers']]);
            }

        } catch (\Exception $e) {
            Log::error('Error getting stats', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * Count units for a specific program
     */
    private function countUnitsForProgram($programId)
    {
        try {
            return DB::table('units')
                ->where('program_id', $programId)
                ->where('is_active', 1)
                ->count();
        } catch (\Exception $e) {
            Log::error('Error counting units for program', ['program_id' => $programId, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Count students for a specific program
     */
    private function countStudentsForProgram($programId, $currentSemester)
    {
        try {
            // Get unit IDs for this program
            $unitIds = DB::table('units')
                ->where('program_id', $programId)
                ->pluck('id');

            if ($unitIds->isEmpty()) {
                return 0;
            }

            // Try different enrollment tables
            if (DB::getSchemaBuilder()->hasTable('bbit_enrollments')) {
                return DB::table('bbit_enrollments')
                    ->whereIn('unit_id', $unitIds)
                    ->where('semester_id', $currentSemester->id ?? 0)
                    ->distinct('student_code')
                    ->count('student_code');
            } elseif (DB::getSchemaBuilder()->hasTable('enrollments')) {
                return DB::table('enrollments')
                    ->whereIn('unit_id', $unitIds)
                    ->where('semester_id', $currentSemester->id ?? 0)
                    ->where('status', 'enrolled')
                    ->distinct('student_code')
                    ->count('student_code');
            } elseif (DB::getSchemaBuilder()->hasTable('unit_assignments')) {
                return DB::table('unit_assignments')
                    ->whereIn('unit_id', $unitIds)
                    ->distinct('student_code')
                    ->count('student_code');
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Error counting students for program', ['program_id' => $programId, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get recent activities with debug info
     */
    private function getRecentActivitiesDebug($programIds)
    {
        $activities = [];

        try {
            // Get unit IDs for these programs
            $unitIds = DB::table('units')
                ->whereIn('program_id', $programIds)
                ->pluck('id');

            if ($unitIds->isEmpty()) {
                Log::info('No units found for programs', ['program_ids' => $programIds]);
                return [];
            }

            // Try to get recent enrollments
            if (DB::getSchemaBuilder()->hasTable('bbit_enrollments')) {
                $recentEnrollments = DB::table('bbit_enrollments')
                    ->whereIn('unit_id', $unitIds)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                foreach ($recentEnrollments as $enrollment) {
                    $unitName = DB::table('bbit_units')->where('id', $enrollment->unit_id)->value('name') ?? 'Unit';
                    
                    $activities[] = [
                        'id' => $enrollment->id,
                        'type' => 'enrollment',
                        'message' => 'Student' . ($enrollment->student_code ?? '') . ' enrolled in ' . $unitName,
                        'description' => 'Enrollment activity',
                        'time' => Carbon::parse($enrollment->created_at)->diffForHumans(),
                        'created_at' => $enrollment->created_at,
                    ];
                }

                Log::info('Recent activities from bbit_enrollments', ['count' => count($activities)]);
            }

        } catch (\Exception $e) {
            Log::error('Error getting recent activities', ['error' => $e->getMessage()]);
        }

        return $activities;
    }

    /**
     * Helper method to return error dashboard
     */
    private function returnErrorDashboard($errorMessage)
    {
        $user = auth()->user();

        return Inertia::render('SchoolAdmin/Dashboard', [
            'schoolName' => 'School of Computing and Engineering Sciences',
            'schoolCode' => 'SCES',
            'currentSemester' => null,
            'stats' => [
                'totalStudents' => 0,
                'studentsTrend' => '0%',
                'activePrograms' => 0,
                'programsTrend' => '0%',
                'totalUnits' => 0,
                'unitsTrend' => '0%',
                'totalLecturers' => 0,
                'lecturersTrend' => '0%',
            ],
            'programs' => [],
            'recentActivities' => [],
            'upcomingEvents' => [],
            'pendingApprovals' => [],
            'userPermissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'userRoles' => $user->getRoleNames()->toArray(),
            'error' => $errorMessage
        ]);
    }

    /**
     * Helper method to get program color class
     */
    private function getProgramColorClass($code)
    {
        $colors = [
            'BBIT' => 'bg-blue-500',
            'ICS' => 'bg-purple-500',
            'SEEE' => 'bg-green-500',
            'BCS' => 'bg-indigo-500',
            'SE' => 'bg-pink-500',
        ];

        return $colors[$code] ?? 'bg-gray-500';
    }
}