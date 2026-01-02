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
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('class_id')
                ->comment('Section/group identifier from class (e.g., A, B, C)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->dropColumn('group_name');
        });
    }
};