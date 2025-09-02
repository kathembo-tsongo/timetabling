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
        Schema::table('classrooms', function (Blueprint $table) {
            // Check and add missing columns
            if (!Schema::hasColumn('classrooms', 'code')) {
                $table->string('code')->unique()->after('name')->nullable();
            }
            
            if (!Schema::hasColumn('classrooms', 'building')) {
                $table->string('building')->after('code')->nullable();
            }
            
            if (!Schema::hasColumn('classrooms', 'floor')) {
                $table->string('floor')->nullable()->after('building');
            }
            
            if (!Schema::hasColumn('classrooms', 'type')) {
                $table->string('type')->default('lecture_hall')->after('capacity');
            }
            
            if (!Schema::hasColumn('classrooms', 'facilities')) {
                $table->json('facilities')->nullable()->after('type');
            }
            
            if (!Schema::hasColumn('classrooms', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('facilities');
            }
            
            if (!Schema::hasColumn('classrooms', 'description')) {
                $table->text('description')->nullable()->after('location');
            }
        });

        // Update existing records to have default values
        DB::table('classrooms')->update([
            'code' => DB::raw("CONCAT('CR-', id)"), // Generate codes like CR-1, CR-2, etc.
            'building' => 'Main Building', // Default building
            'type' => 'lecture_hall',
            'is_active' => true,
            'facilities' => json_encode([]) // Empty facilities array
        ]);

        // Now make required fields non-nullable
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
            $table->string('building')->nullable(false)->change();
        });

        // Add indexes for better performance
        Schema::table('classrooms', function (Blueprint $table) {
            try {
                $table->index(['building', 'is_active']);
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
            
            try {
                $table->index('type');
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            // Drop indexes first
            try {
                $table->dropIndex(['building', 'is_active']);
            } catch (\Exception $e) {
                // Index might not exist, ignore error
            }
            
            try {
                $table->dropIndex(['type']);
            } catch (\Exception $e) {
                // Index might not exist, ignore error
            }
            
            // Drop added columns
            $columnsToCheck = ['code', 'building', 'floor', 'type', 'facilities', 'is_active', 'description'];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('classrooms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};