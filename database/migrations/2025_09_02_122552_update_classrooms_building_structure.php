<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the building_id column
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('building_id')->nullable()->after('code');
            $table->foreign('building_id')->references('id')->on('buildings');
        });

        // Map existing building names to building IDs
        $buildingMappings = [
            'Main Building' => 1,
            'Science Building' => 2,
            'Arts Building' => 3,
            'Engineering Building' => 4,
            'Library Building' => 5,
        ];

        // Update existing records
        foreach ($buildingMappings as $buildingName => $buildingId) {
            DB::table('classrooms')
                ->where('building', $buildingName)
                ->update(['building_id' => $buildingId]);
        }

        // For any classrooms that don't match, set them to Main Building (ID: 1)
        DB::table('classrooms')
            ->whereNull('building_id')
            ->update(['building_id' => 1]);

        // Make building_id required
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('building_id')->nullable(false)->change();
        });

        // Now drop the old building column
        Schema::table('classrooms', function (Blueprint $table) {
            if (Schema::hasColumn('classrooms', 'building')) {
                $table->dropColumn('building');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the building column
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('building')->after('code');
        });

        // Restore building names from building_id
        $buildings = DB::table('buildings')->get();
        foreach ($buildings as $building) {
            DB::table('classrooms')
                ->where('building_id', $building->id)
                ->update(['building' => $building->name]);
        }

        // Drop the foreign key and building_id column
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['building_id']);
            $table->dropColumn('building_id');
        });
    }
};