<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('semester_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->onDelete('cascade'); // Links to semesters
            $table->foreignId('unit_id')->constrained()->onDelete('cascade'); // Links to units
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade'); // Links to classes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_unit');
    }
};
