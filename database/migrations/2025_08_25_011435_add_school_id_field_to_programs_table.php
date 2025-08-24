<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (!Schema::hasColumn('programs', 'school_id')) {
                $table->foreignId('school_id')
                      ->after('id')
                      ->constrained('schools')
                      ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            }
        });
    }
};
