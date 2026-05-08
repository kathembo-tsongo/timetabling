<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
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

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                echo "⊘ Skipped {$table} (table does not exist)\n";
                continue;
            }
            if (Schema::hasColumn($table, 'tenant_id')) {
                echo "⊘ Skipped {$table} (tenant_id already exists)\n";
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $t->foreign('tenant_id')
                  ->references('id')->on('tenants')
                  ->onDelete('cascade');
                $t->index('tenant_id', "idx_{$table}_tenant_id");
            });
            echo "✓ Added tenant_id to {$table}\n";
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                // Safely drop foreign key and index only if they exist
                $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_{$table}_tenant_id'");
                $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' 
                    AND COLUMN_NAME = 'tenant_id' AND REFERENCED_TABLE_NAME IS NOT NULL");

                if (!empty($foreignKeys)) {
                    $t->dropForeign(['tenant_id']);
                }
                if (!empty($indexes)) {
                    $t->dropIndex("idx_{$table}_tenant_id");
                }
                $t->dropColumn('tenant_id');
            });
            echo "✓ Removed tenant_id from {$table}\n";
        }
    }
};
