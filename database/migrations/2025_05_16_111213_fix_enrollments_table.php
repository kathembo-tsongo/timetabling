<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixEnrollmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Only run if the enrollments table exists
        if (Schema::hasTable('enrollments')) {
            Schema::table('enrollments', function (Blueprint $table) {
                // Make sure the student_code column is properly defined
                if (Schema::hasColumn('enrollments', 'student_code')) {
                    $table->string('student_code')->nullable()->change();
                } else {
                    $table->string('student_code')->nullable()->after('id');
                }
                
                // Make sure the group_id column is properly defined
                if (Schema::hasColumn('enrollments', 'group_id')) {
                    $table->string('group_id')->nullable()->change();
                } else {
                    $table->string('group_id')->nullable()->after('lecturer_code');
                }
                
                // Make sure the unit_id column is properly defined
                if (Schema::hasColumn('enrollments', 'unit_id')) {
                    $table->unsignedBigInteger('unit_id')->change();
                } else {
                    $table->unsignedBigInteger('unit_id')->after('group_id');
                }
                
                // Make sure the semester_id column is properly defined
                if (Schema::hasColumn('enrollments', 'semester_id')) {
                    $table->unsignedBigInteger('semester_id')->default(1)->change();
                } else {
                    $table->unsignedBigInteger('semester_id')->default(1)->after('unit_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No need to reverse these changes
    }
}
