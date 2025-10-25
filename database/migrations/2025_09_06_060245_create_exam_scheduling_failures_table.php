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
        Schema::create('exam_scheduling_failures', function (Blueprint $table) {
            $table->id();
            
            // Batch tracking - group failures from same bulk schedule attempt
            $table->string('batch_id')->index();
            
            // Academic context
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('school_id')->nullable();
            
            // Unit information
            $table->unsignedBigInteger('unit_id');
            $table->string('unit_code', 50);
            $table->string('unit_name');
            
            // Class information (can be multiple classes taking same unit)
            $table->json('class_ids'); // Array of class IDs
            $table->text('class_names'); // Comma-separated or JSON array
            $table->integer('student_count')->default(0);
            
            // Attempted scheduling details
            $table->date('attempted_date')->nullable();
            $table->time('attempted_start_time')->nullable();
            $table->time('attempted_end_time')->nullable();
            $table->integer('assigned_slot_number')->nullable();
            
            // Failure details
            $table->text('failure_reason');
            $table->json('conflict_details')->nullable(); // Additional debug info
            
            // Resolution tracking
            $table->enum('status', ['pending', 'resolved', 'retried', 'ignored'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable(); // User ID who resolved it
            $table->text('resolution_notes')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for fast queries
            $table->index(['batch_id', 'status']);
            $table->index(['program_id', 'semester_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_scheduling_failures');
    }
};