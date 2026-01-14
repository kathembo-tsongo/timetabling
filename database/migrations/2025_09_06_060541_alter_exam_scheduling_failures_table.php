<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Make failure_reason nullable (TEXT columns cannot have default values)
        DB::statement('ALTER TABLE `failed_exam_schedules` MODIFY COLUMN `failure_reason` TEXT NULL');
    }

    public function down()
    {
        // Revert back to NOT NULL
        DB::statement('ALTER TABLE `failed_exam_schedules` MODIFY COLUMN `failure_reason` TEXT NOT NULL');
    }
};