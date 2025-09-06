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
        // Update unit_assignments table to include lecturer assignment fields
        Schema::table('unit_assignments', function (Blueprint $table) {
            $table->string('lecturer_code')->nullable()->after('semester_id');
            $table->timestamp('assigned_at')->nullable()->after('lecturer_code');
            $table->text('assignment_notes')->nullable()->after('assigned_at');
            
            // Add index for better query performance
            $table->index(['lecturer_code', 'semester_id']);
            $table->index(['semester_id', 'is_active']);
        });

        // Create lecturer_workload_limits table for managing workload constraints
        Schema::create('lecturer_workload_limits', function (Blueprint $table) {
            $table->id();
            $table->string('lecturer_code');
            $table->unsignedBigInteger('semester_id');
            $table->integer('max_units')->default(10);
            $table->integer('max_credit_hours')->default(18);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            
            // Unique constraint to prevent duplicate limits
            $table->unique(['lecturer_code', 'semester_id']);
            
            // Indexes
            $table->index(['lecturer_code', 'is_active']);
        });

        // Create lecturer_specializations table for tracking lecturer expertise
        Schema::create('lecturer_specializations', function (Blueprint $table) {
            $table->id();
            $table->string('lecturer_code');
            $table->unsignedBigInteger('unit_id');
            $table->enum('proficiency_level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('intermediate');
            $table->boolean('is_preferred')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['lecturer_code', 'unit_id']);
            
            // Indexes
            $table->index(['lecturer_code', 'is_preferred']);
            $table->index(['unit_id', 'proficiency_level']);
        });

        // Create assignment_history table for tracking changes
        Schema::create('assignment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_assignment_id');
            $table->string('action'); // assigned, unassigned, reassigned
            $table->string('previous_lecturer_code')->nullable();
            $table->string('new_lecturer_code')->nullable();
            $table->string('changed_by'); // user who made the change
            $table->text('reason')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('unit_assignment_id')->references('id')->on('unit_assignments')->onDelete('cascade');
            
            // Indexes
            $table->index(['unit_assignment_id', 'created_at']);
            $table->index(['new_lecturer_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_history');
        Schema::dropIfExists('lecturer_specializations');
        Schema::dropIfExists('lecturer_workload_limits');
        
        Schema::table('unit_assignments', function (Blueprint $table) {
            $table->dropIndex(['lecturer_code', 'semester_id']);
            $table->dropIndex(['semester_id', 'is_active']);
            $table->dropColumn(['lecturer_code', 'assigned_at', 'assignment_notes']);
        });
    }
};