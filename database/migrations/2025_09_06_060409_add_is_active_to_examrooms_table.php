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
        Schema::table('examrooms', function (Blueprint $table) {
            // Add is_active column (default to true/1 so existing rooms are active)
            $table->boolean('is_active')->default(1)->after('location');
        });
        
        // Optional: Set all existing exam rooms to active
        DB::table('examrooms')->update(['is_active' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examrooms', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};