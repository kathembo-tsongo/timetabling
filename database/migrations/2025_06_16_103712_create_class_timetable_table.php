<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_timetable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('set null');
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('teaching_mode')->deafault('Physical');
            $table->string('venue')->nullable();
            $table->string('location')->nullable();
            $table->integer('no')->nullable(); // Number of students
            $table->string('lecturer')->nullable();
            $table->string('group', 1)->nullable(); // Added group field to match with enrollments
            $table->foreignId('program_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('school_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('group_id')->nullable()->constrained()->onDelete('cascade'); // Add group_id
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetable');
    }
};