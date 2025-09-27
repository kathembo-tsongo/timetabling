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
        Schema::table('semesters', function (Blueprint $table) {
            // Add intake_type column
            $table->string('intake_type', 20)->default('September')->after('is_active');
            
            // Add academic_year column
            $table->string('academic_year', 10)->default('2024/25')->after('intake_type');
            
            // Add composite index for intake_type and academic_year
            $table->index(['intake_type', 'academic_year'], 'idx_intake_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex('idx_intake_year');
            
            // Drop the columns
            $table->dropColumn(['intake_type', 'academic_year']);
        });
    }
};