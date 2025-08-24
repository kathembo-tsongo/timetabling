<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['semester_id']); // Drop foreign key constraint
            $table->dropColumn('semester_id'); // Remove the semester_id column
        });
    }

    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->constrained()->onDelete('cascade'); // Re-add semester_id
        });
    }
};
