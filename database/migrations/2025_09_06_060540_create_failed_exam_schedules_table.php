<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('class_name');
            $table->string('section')->nullable();
            $table->string('unit_code');
            $table->string('unit_name');
            $table->integer('student_count');
            $table->string('lecturer_name')->nullable();
            $table->json('failure_reasons'); // Array of conflict reasons
            $table->json('attempted_dates')->nullable(); // Dates that were tried
            $table->enum('status', ['pending', 'resolved', 'ignored'])->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index(['program_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_exam_schedules');
    }
};