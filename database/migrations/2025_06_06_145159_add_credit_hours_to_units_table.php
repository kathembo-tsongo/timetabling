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
        Schema::table('unit', function (Blueprint $table) {
            // Check if credit_hours column doesn't already exist
            if (!Schema::hasColumn('units', 'credit_hours')) {
                $table->integer('credit_hours')->default(3)->after('school_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'credit_hours')) {
                $table->dropColumn('credit_hours');
            }
        });
    }
};