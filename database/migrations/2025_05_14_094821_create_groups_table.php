<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->string('group', 1); // A, B, C, D
            $table->integer('capacity')->default(60);
            $table->integer('current_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['program_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_groups');
    }
};