<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('classes', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('classes', 'school_id')) {
                $table->unsignedBigInteger('school_id')->nullable()->after('id');
                $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('classes', 'program_id')) {
                $table->unsignedBigInteger('program_id')->nullable()->after('school_id');
                $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
            }
        });

        // Also ensure class_timetable table has proper indexes for performance
        Schema::table('class_timetable', function (Blueprint $table) {
            // Add performance indexes if they don't exist
            if (!$this->indexExists('class_timetable', 'idx_class_timetable_lookup')) {
                $table->index(['semester_id', 'class_id', 'group_id'], 'idx_class_timetable_lookup');
            }
            
            if (!$this->indexExists('class_timetable', 'idx_class_timetable_time')) {
                $table->index(['day', 'start_time', 'end_time'], 'idx_class_timetable_time');
            }
            
            if (!$this->indexExists('class_timetable', 'idx_class_timetable_lecturer')) {
                $table->index(['lecturer'], 'idx_class_timetable_lecturer');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['program_id']);
            $table->dropColumn(['school_id', 'program_id']);
        });

        Schema::table('class_timetable', function (Blueprint $table) {
            $table->dropIndex('idx_class_timetable_lookup');
            $table->dropIndex('idx_class_timetable_time');
            $table->dropIndex('idx_class_timetable_lecturer');
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists($table, $indexName)
    {
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()
            ->listTableIndexes($table);
        
        return array_key_exists($indexName, $indexes);
    }
};
