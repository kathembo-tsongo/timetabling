<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('student_code');
            $table->string('lecturer_code')->nullable();
            $table->string('group_id', 1)->nullable(); // Group (A, B, C, D)
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('semester_id')->default(1)->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('school_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            
            // Add unique constraint to prevent duplicate enrollments
            $table->unique(['student_code', 'unit_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};