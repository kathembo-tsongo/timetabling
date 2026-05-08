<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        // Add school_id and program_id only if missing
        Schema::table('classes', function (Blueprint $table) {
            if (!Schema::hasColumn('classes', 'school_id')) {
                $table->unsignedBigInteger('school_id')->nullable()->after('id');
                $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');
            }
            if (!Schema::hasColumn('classes', 'program_id')) {
                $table->unsignedBigInteger('program_id')->nullable()->after('school_id');
                $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
            }
        });

        // Add indexes using raw SQL to avoid doctrine/dbal dependency
        $indexes = DB::select("SHOW INDEX FROM class_timetable");
        $existingIndexes = array_column($indexes, 'Key_name');

        if (!in_array('idx_class_timetable_lookup', $existingIndexes)) {
            DB::statement('ALTER TABLE class_timetable ADD INDEX idx_class_timetable_lookup (semester_id, class_id, group_id)');
        }
        if (!in_array('idx_class_timetable_time', $existingIndexes)) {
            DB::statement('ALTER TABLE class_timetable ADD INDEX idx_class_timetable_time (day, start_time, end_time)');
        }
        if (!in_array('idx_class_timetable_lecturer', $existingIndexes)) {
            DB::statement('ALTER TABLE class_timetable ADD INDEX idx_class_timetable_lecturer (lecturer)');
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (Schema::hasColumn('classes', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            }
            if (Schema::hasColumn('classes', 'program_id')) {
                $table->dropForeign(['program_id']);
                $table->dropColumn('program_id');
            }
        });

        DB::statement('ALTER TABLE class_timetable DROP INDEX IF EXISTS idx_class_timetable_lookup');
        DB::statement('ALTER TABLE class_timetable DROP INDEX IF EXISTS idx_class_timetable_time');
        DB::statement('ALTER TABLE class_timetable DROP INDEX IF EXISTS idx_class_timetable_lecturer');
    }
};
