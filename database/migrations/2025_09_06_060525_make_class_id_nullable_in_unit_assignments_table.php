<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make class_id nullable to support common units
     */
    public function up(): void
    {
        Schema::table('unit_assignments', function (Blueprint $table) {
            // Make class_id nullable
            $table->unsignedBigInteger('class_id')->nullable()->change();
            
            // Optional: Add index for common units (where class_id is null)
            $table->index(['unit_id', 'semester_id', 'class_id'], 'unit_semester_class_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_assignments', function (Blueprint $table) {
            // Make class_id required again
            $table->unsignedBigInteger('class_id')->nullable(false)->change();
            
            // Drop the index if needed
            $table->dropIndex('unit_semester_class_idx');
        });
    }
};