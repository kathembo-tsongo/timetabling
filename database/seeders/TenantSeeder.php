<?php
namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(['slug' => 'strathmore'], [
            'name'            => 'Strathmore University',
            'slug'            => 'strathmore',
            'domain'          => 'localhost',
            'primary_color'   => '#1a56db',
            'secondary_color' => '#7e3af2',
            'timezone'        => 'Africa/Nairobi',
            'locale'          => 'en',
            'currency'        => 'KES',
            'plan'            => 'enterprise',
            'is_active'       => true,
            'settings'        => [
                'university_name'    => 'Strathmore University',
                'university_motto'   => 'Integrity · Commitment · Excellence',
                'academic_year'      => '2025/2026',
                'max_credit_hours'   => 21,
                'grading_system'     => 'GPA',
                'credit_hour_system' => true,
            ],
        ]);

        $this->command->info("Tenant created: {$tenant->name} (ID: {$tenant->id})");

        // Assign all existing data to Strathmore
        $tables = [
            'users', 'schools', 'programs', 'units', 'classes',
            'semesters', 'enrollments', 'classrooms', 'buildings',
            'program_groups', 'unit_assignments', 'lecturer_assignments',
            'lecturer_specializations', 'lecturer_workload_limits',
            'class_timetable', 'class_time_slots', 'exam_timetables',
            'exam_scheduling_failures', 'failed_exam_schedules',
            'academic_years', 'intake', 'time_slots', 'semester_unit',
            'user_school_assignments', 'assignment_history',
            'notification_logs', 'notification_preferences',
            'permission_metas', 'role_metas',
        ];

        foreach ($tables as $table) {
            $updated = DB::table($table)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenant->id]);
            $this->command->info("✓ {$table}: {$updated} rows assigned to tenant #{$tenant->id}");
        }

        $this->command->info('✅ Strathmore University is now Tenant #1!');
    }
}
