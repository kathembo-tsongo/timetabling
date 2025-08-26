<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // Add new columns for program-based functionality
            $table->integer('year_level')->nullable()->after('program_id')->comment('Academic year level (1, 2, 3, 4)');
            $table->string('section', 10)->nullable()->after('year_level')->comment('Class section (A, B, C, etc.)');
            $table->integer('capacity')->nullable()->after('section')->comment('Maximum number of students');
            $table->integer('students_count')->default(0)->after('capacity')->comment('Current number of enrolled students');
            $table->boolean('is_active')->default(true)->after('students_count')->comment('Whether class is active');
            
            // Add indexes for better query performance
            $table->index(['program_id', 'year_level', 'section'], 'idx_program_year_section');
            $table->index(['semester_id', 'program_id'], 'idx_semester_program');
            $table->index(['is_active'], 'idx_is_active');
            $table->index(['year_level'], 'idx_year_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_program_year_section');
            $table->dropIndex('idx_semester_program');
            $table->dropIndex('idx_is_active');
            $table->dropIndex('idx_year_level');
            
            // Drop columns
            $table->dropColumn([
                'year_level',
                'section',
                'capacity',
                'students_count',
                'is_active'
            ]);
        });
    }
};