<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->string('school_code', 10)->nullable()->after('is_active');
            $table->index('school_code');
        });
    }

    public function down()
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('school_code');
        });
    }
};